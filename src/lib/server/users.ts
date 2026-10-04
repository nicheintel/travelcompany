import "server-only";
import { db } from "./db";

export type User = {
  id: number;
  name: string;
  email: string;
  createdAt: number;
};

type UserRow = { id: number; name: string; email: string; created_at: number; password_hash: string };

function toUser(row: UserRow): User {
  return { id: row.id, name: row.name, email: row.email, createdAt: row.created_at };
}

export function normalizeEmail(email: string) {
  return email.trim().toLowerCase();
}

export function findUserById(id: number): User | null {
  const row = db.prepare("SELECT * FROM users WHERE id = ?").get(id) as UserRow | undefined;
  return row ? toUser(row) : null;
}

/** Includes the password hash — only for verifying credentials. */
export function findUserWithPasswordByEmail(email: string) {
  const row = db
    .prepare("SELECT * FROM users WHERE email = ?")
    .get(normalizeEmail(email)) as UserRow | undefined;
  return row ? { user: toUser(row), passwordHash: row.password_hash } : null;
}

export function emailExists(email: string) {
  return !!db.prepare("SELECT 1 FROM users WHERE email = ?").get(normalizeEmail(email));
}

export function createUser(name: string, email: string, passwordHash: string): User {
  const now = Date.now();
  const info = db
    .prepare("INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, ?)")
    .run(name.trim(), normalizeEmail(email), passwordHash, now);
  return { id: Number(info.lastInsertRowid), name: name.trim(), email: normalizeEmail(email), createdAt: now };
}

export function updateUserName(id: number, name: string) {
  db.prepare("UPDATE users SET name = ? WHERE id = ?").run(name.trim(), id);
}

/** Throws SQLITE_CONSTRAINT_UNIQUE if another account already uses the email. */
export function updateUserEmail(id: number, email: string) {
  db.prepare("UPDATE users SET email = ? WHERE id = ?").run(normalizeEmail(email), id);
}

export function updateUserPassword(id: number, passwordHash: string) {
  db.prepare("UPDATE users SET password_hash = ? WHERE id = ?").run(passwordHash, id);
}

export function getPasswordHash(id: number) {
  const row = db.prepare("SELECT password_hash FROM users WHERE id = ?").get(id) as
    | { password_hash: string }
    | undefined;
  return row?.password_hash ?? null;
}
