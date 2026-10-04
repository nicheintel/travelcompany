"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { safeRedirectPath } from "@/lib/server/dal";
import { burnPasswordCheck, hashPassword, verifyPassword } from "@/lib/server/password";
import { appUrl, sendEmail } from "@/lib/server/email";
import { passwordResetEmail } from "@/lib/server/email-templates";
import { consumeResetToken, createResetToken } from "@/lib/server/password-reset";
import { resetRequests, signInFailures } from "@/lib/server/rate-limit";
import { createSession, deleteSession } from "@/lib/server/session";
import { EmailSchema, NameSchema, PasswordSchema } from "@/lib/server/validation";
import {
  createUser,
  emailExists,
  findUserById,
  findUserWithPasswordByEmail,
  normalizeEmail,
} from "@/lib/server/users";

export type AuthFormState =
  | {
      message?: string;
      errors?: Partial<Record<"name" | "email" | "password" | "confirmPassword", string[]>>;
      values?: { name?: string; email?: string };
    }
  | undefined;

const RegisterSchema = z
  .object({
    name: NameSchema,
    email: EmailSchema,
    password: PasswordSchema,
    confirmPassword: z.string(),
  })
  .refine((d) => d.password === d.confirmPassword, {
    path: ["confirmPassword"],
    message: "Passwords don't match.",
  });

const SignInSchema = z.object({
  email: z.email("Please enter a valid email address."),
  password: z.string().min(1, "Please enter your password."),
});

function field(formData: FormData, key: string) {
  const v = formData.get(key);
  return typeof v === "string" ? v : "";
}

export async function register(_prev: AuthFormState, formData: FormData): Promise<AuthFormState> {
  const raw = {
    name: field(formData, "name"),
    email: field(formData, "email").trim(),
    password: field(formData, "password"),
    confirmPassword: field(formData, "confirmPassword"),
  };
  const values = { name: raw.name, email: raw.email };

  const parsed = RegisterSchema.safeParse(raw);
  if (!parsed.success) {
    return { errors: z.flattenError(parsed.error).fieldErrors, values };
  }

  const { name, email, password } = parsed.data;
  const taken = { errors: { email: ["An account with this email already exists. Try signing in."] }, values };
  if (emailExists(email)) return taken;

  let userId: number;
  try {
    userId = createUser(name, email, await hashPassword(password)).id;
  } catch (err) {
    // Two sign-ups with the same email at once: the UNIQUE constraint catches the second.
    if ((err as { code?: string }).code === "SQLITE_CONSTRAINT_UNIQUE") return taken;
    throw err;
  }

  await createSession(userId);
  redirect(safeRedirectPath(field(formData, "next")));
}

export async function signIn(_prev: AuthFormState, formData: FormData): Promise<AuthFormState> {
  const raw = { email: field(formData, "email").trim(), password: field(formData, "password") };
  const values = { email: raw.email };

  const parsed = SignInSchema.safeParse(raw);
  if (!parsed.success) {
    return { errors: z.flattenError(parsed.error).fieldErrors, values };
  }

  const key = normalizeEmail(parsed.data.email);
  if (signInFailures.isLimited(key)) {
    return {
      message: "Too many failed attempts. Please wait 15 minutes and try again.",
      values,
    };
  }

  const found = findUserWithPasswordByEmail(key);
  const ok = found
    ? await verifyPassword(parsed.data.password, found.passwordHash)
    : (await burnPasswordCheck(parsed.data.password), false);

  if (!found || !ok) {
    signInFailures.hit(key);
    return { message: "Incorrect email or password.", values };
  }

  signInFailures.clear(key);
  await createSession(found.user.id);
  redirect(safeRedirectPath(field(formData, "next")));
}

export async function signOut() {
  await deleteSession();
  redirect("/");
}

export type ResetRequestState = { sent?: boolean; email?: string; error?: string } | undefined;

export async function requestPasswordReset(
  _prev: ResetRequestState,
  formData: FormData,
): Promise<ResetRequestState> {
  const email = field(formData, "email").trim();
  if (!z.email().safeParse(email).success) {
    return { error: "Please enter a valid email address.", email };
  }

  // Same response whether or not the account exists, so this can't be used to discover members.
  const sent = { sent: true, email };
  const key = normalizeEmail(email);
  if (resetRequests.isLimited(key)) return sent;
  resetRequests.hit(key);

  const found = findUserWithPasswordByEmail(key);
  if (!found) return sent;

  const link = `${await appUrl()}/reset-password?token=${createResetToken(found.user.id)}`;
  const firstName = found.user.name.split(" ")[0];
  await sendEmail({ to: found.user.email, ...passwordResetEmail(firstName, link) });
  return sent;
}

export type ResetPasswordState =
  | { message?: string; errors?: Partial<Record<"password" | "confirmPassword", string[]>> }
  | undefined;

const ResetSchema = z
  .object({ token: z.string().min(1), password: PasswordSchema, confirmPassword: z.string() })
  .refine((d) => d.password === d.confirmPassword, {
    path: ["confirmPassword"],
    message: "Passwords don't match.",
  });

export async function resetPassword(
  _prev: ResetPasswordState,
  formData: FormData,
): Promise<ResetPasswordState> {
  const parsed = ResetSchema.safeParse({
    token: field(formData, "token"),
    password: field(formData, "password"),
    confirmPassword: field(formData, "confirmPassword"),
  });
  if (!parsed.success) return { errors: z.flattenError(parsed.error).fieldErrors };

  const userId = consumeResetToken(parsed.data.token, await hashPassword(parsed.data.password));
  if (!userId || !findUserById(userId)) {
    return { message: "This reset link is invalid or has expired. Please request a new one." };
  }

  // Every other session was signed out by consumeResetToken; sign in here with the new password.
  await createSession(userId);
  redirect("/account?reset=1");
}
