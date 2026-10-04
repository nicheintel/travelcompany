"use server";

import { redirect } from "next/navigation";
import { z } from "zod";
import { safeRedirectPath } from "@/lib/server/dal";
import { burnPasswordCheck, hashPassword, verifyPassword } from "@/lib/server/password";
import { clearFailures, isLocked, recordFailure } from "@/lib/server/rate-limit";
import { createSession, deleteSession } from "@/lib/server/session";
import {
  createUser,
  emailExists,
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
    name: z
      .string()
      .trim()
      .min(2, "Please enter your full name.")
      .max(60, "Name must be 60 characters or fewer."),
    email: z.email("Please enter a valid email address.").max(254),
    password: z
      .string()
      .min(8, "Password must be at least 8 characters.")
      .max(128, "Password must be 128 characters or fewer.")
      .regex(/[a-zA-Z]/, "Password must contain at least one letter.")
      .regex(/[0-9]/, "Password must contain at least one number."),
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
  if (isLocked(key)) {
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
    recordFailure(key);
    return { message: "Incorrect email or password.", values };
  }

  clearFailures(key);
  await createSession(found.user.id);
  redirect(safeRedirectPath(field(formData, "next")));
}

export async function signOut() {
  await deleteSession();
  redirect("/");
}
