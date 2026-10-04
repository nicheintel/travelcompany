import "server-only";
import { z } from "zod";

export const NameSchema = z
  .string()
  .trim()
  .min(2, "Please enter your full name.")
  .max(60, "Name must be 60 characters or fewer.");

export const EmailSchema = z.email("Please enter a valid email address.").max(254);

export const PasswordSchema = z
  .string()
  .min(8, "Password must be at least 8 characters.")
  .max(128, "Password must be 128 characters or fewer.")
  .regex(/[a-zA-Z]/, "Password must contain at least one letter.")
  .regex(/[0-9]/, "Password must contain at least one number.");
