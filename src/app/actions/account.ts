"use server";

import { refresh } from "next/cache";
import { z } from "zod";
import { requireUser } from "@/lib/server/dal";
import { appUrl, sendEmail } from "@/lib/server/email";
import { emailChangedEmail, passwordChangedEmail } from "@/lib/server/email-templates";
import { hashPassword, verifyPassword } from "@/lib/server/password";
import { signInFailures } from "@/lib/server/rate-limit";
import { deleteOtherSessions } from "@/lib/server/session";
import {
  emailExists,
  getPasswordHash,
  normalizeEmail,
  updateUserEmail,
  updateUserName,
  updateUserPassword,
} from "@/lib/server/users";
import { EmailSchema, NameSchema, PasswordSchema } from "@/lib/server/validation";

export type SettingsState =
  | { ok?: string; message?: string; errors?: Record<string, string[] | undefined> }
  | undefined;

function field(formData: FormData, key: string) {
  const v = formData.get(key);
  return typeof v === "string" ? v : "";
}

/**
 * Checks the current password, sharing the sign-in lockout so settings can't be
 * used to brute-force a password on an unattended signed-in browser.
 */
async function checkCurrentPassword(userId: number, email: string, password: string) {
  const key = normalizeEmail(email);
  if (signInFailures.isLimited(key)) {
    return "Too many incorrect attempts. Please wait 15 minutes and try again.";
  }
  const hash = getPasswordHash(userId);
  if (!hash || !(await verifyPassword(password, hash))) {
    signInFailures.hit(key);
    return "Your current password is incorrect.";
  }
  signInFailures.clear(key);
  return null;
}

export async function updateProfile(_prev: SettingsState, formData: FormData): Promise<SettingsState> {
  const user = await requireUser("/account/settings");
  const parsed = NameSchema.safeParse(field(formData, "name"));
  if (!parsed.success) return { errors: { name: [parsed.error.issues[0].message] } };
  updateUserName(user.id, parsed.data);
  refresh(); // update the name shown in the header
  return { ok: "Your name has been updated." };
}

export async function changeEmail(_prev: SettingsState, formData: FormData): Promise<SettingsState> {
  const user = await requireUser("/account/settings");
  const parsed = EmailSchema.safeParse(field(formData, "email").trim());
  if (!parsed.success) return { errors: { email: [parsed.error.issues[0].message] } };
  const newEmail = normalizeEmail(parsed.data);
  if (newEmail === user.email) return { errors: { email: ["That's already your email address."] } };

  const wrong = await checkCurrentPassword(user.id, user.email, field(formData, "currentPassword"));
  if (wrong) return { errors: { currentPassword: [wrong] } };

  const taken = { errors: { email: ["Another account already uses this email."] } };
  if (emailExists(newEmail)) return taken;
  try {
    updateUserEmail(user.id, newEmail);
  } catch (err) {
    if ((err as { code?: string }).code === "SQLITE_CONSTRAINT_UNIQUE") return taken;
    throw err;
  }

  // Tell the *old* address, so a hijacked account doesn't go unnoticed.
  const base = await appUrl();
  await sendEmail({
    to: user.email,
    ...emailChangedEmail(user.name.split(" ")[0], newEmail, `${base}/forgot-password`),
  });
  refresh();
  return { ok: `Your email is now ${newEmail}.` };
}

const PasswordChangeSchema = z
  .object({ currentPassword: z.string(), password: PasswordSchema, confirmPassword: z.string() })
  .refine((d) => d.password === d.confirmPassword, {
    path: ["confirmPassword"],
    message: "Passwords don't match.",
  });

export async function changePassword(_prev: SettingsState, formData: FormData): Promise<SettingsState> {
  const user = await requireUser("/account/settings");
  const parsed = PasswordChangeSchema.safeParse({
    currentPassword: field(formData, "currentPassword"),
    password: field(formData, "password"),
    confirmPassword: field(formData, "confirmPassword"),
  });
  if (!parsed.success) return { errors: z.flattenError(parsed.error).fieldErrors };

  const wrong = await checkCurrentPassword(user.id, user.email, parsed.data.currentPassword);
  if (wrong) return { errors: { currentPassword: [wrong] } };

  updateUserPassword(user.id, await hashPassword(parsed.data.password));
  await deleteOtherSessions(user.id);
  await sendEmail({
    to: user.email,
    ...passwordChangedEmail(user.name.split(" ")[0], `${await appUrl()}/forgot-password`),
  });
  return { ok: "Password changed. You've been signed out on all other devices." };
}
