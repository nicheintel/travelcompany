import "server-only";
import { db } from "./db";

export type Role = "customer" | "admin";

export type User = {
  id: number;
  name: string;
  email: string;
  createdAt: number;
  role: Role;
  /** Admin because the email is listed in ADMIN_EMAILS (can't be removed from the dashboard). */
  adminByConfig: boolean;
};

type UserRow = {
  id: number;
  name: string;
  email: string;
  created_at: number;
  password_hash: string;
  role: string;
};

/** Comma-separated emails that are always admins, e.g. ADMIN_EMAILS=owner@example.com */
function configuredAdmins() {
  return (process.env.ADMIN_EMAILS ?? "")
    .split(",")
    .map((e) => normalizeEmail(e))
    .filter(Boolean);
}

function toUser(row: UserRow): User {
  const adminByConfig = configuredAdmins().includes(row.email);
  return {
    id: row.id,
    name: row.name,
    email: row.email,
    createdAt: row.created_at,
    role: adminByConfig || row.role === "admin" ? "admin" : "customer",
    adminByConfig,
  };
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
  return findUserById(Number(info.lastInsertRowid))!;
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

export function setUserRole(id: number, role: Role) {
  db.prepare("UPDATE users SET role = ? WHERE id = ?").run(role, id);
}

export type UserSummary = User & { bookings: number; lastBookingAt: number | null };

const PAGE_SIZE = 50;

/** Admin list: newest first, optional search on name/email. */
export function searchUsers(query: string, page: number) {
  const like = `%${query.trim().toLowerCase()}%`;
  const where = query.trim() ? "WHERE lower(u.name) LIKE ? OR u.email LIKE ?" : "";
  const args = query.trim() ? [like, like] : [];
  const rows = db
    .prepare(
      `SELECT u.*, COUNT(b.id) AS bookings, MAX(b.created_at) AS last_booking_at
       FROM users u LEFT JOIN bookings b ON b.user_id = u.id
       ${where} GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?`,
    )
    .all(...args, PAGE_SIZE + 1, (page - 1) * PAGE_SIZE) as (UserRow & {
    bookings: number;
    last_booking_at: number | null;
  })[];
  return {
    users: rows.slice(0, PAGE_SIZE).map(
      (r): UserSummary => ({ ...toUser(r), bookings: r.bookings, lastBookingAt: r.last_booking_at }),
    ),
    hasMore: rows.length > PAGE_SIZE,
  };
}
