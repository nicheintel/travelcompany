import "server-only";
import fs from "node:fs";
import path from "node:path";
import Database from "better-sqlite3";

/**
 * SQLite database for local development. The file lives in ./data by default;
 * set DATABASE_PATH to put it elsewhere. Before going live on a host without a
 * persistent disk (e.g. Vercel), swap this module for a hosted database such as Postgres.
 */
const DB_PATH = process.env.DATABASE_PATH ?? path.join(process.cwd(), "data", "travelcompany.db");

function open() {
  fs.mkdirSync(path.dirname(DB_PATH), { recursive: true });
  const db = new Database(DB_PATH);
  db.pragma("journal_mode = WAL");
  db.pragma("foreign_keys = ON");
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id            INTEGER PRIMARY KEY AUTOINCREMENT,
      name          TEXT    NOT NULL,
      email         TEXT    NOT NULL UNIQUE,
      password_hash TEXT    NOT NULL,
      created_at    INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sessions (
      token_hash TEXT    PRIMARY KEY,
      user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      expires_at INTEGER NOT NULL,
      created_at INTEGER NOT NULL
    );

    CREATE INDEX IF NOT EXISTS sessions_user_id ON sessions(user_id);

    CREATE TABLE IF NOT EXISTS bookings (
      id             INTEGER PRIMARY KEY AUTOINCREMENT,
      reference      TEXT    NOT NULL UNIQUE,
      user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      kind           TEXT    NOT NULL,
      status         TEXT    NOT NULL DEFAULT 'reserved',
      quote_json     TEXT    NOT NULL,
      travelers_json TEXT    NOT NULL,
      contact_email  TEXT    NOT NULL,
      contact_phone  TEXT    NOT NULL,
      total          INTEGER NOT NULL,
      start_date     TEXT    NOT NULL,
      created_at     INTEGER NOT NULL,
      cancelled_at   INTEGER
    );

    CREATE INDEX IF NOT EXISTS bookings_user_id ON bookings(user_id);

    CREATE TABLE IF NOT EXISTS password_resets (
      token_hash TEXT    PRIMARY KEY,
      user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      expires_at INTEGER NOT NULL,
      used_at    INTEGER,
      created_at INTEGER NOT NULL
    );
  `);
  migrate(db);
  return db;
}

/** Add columns introduced after a database was first created. */
function migrate(db: Database.Database) {
  const has = (table: string, column: string) =>
    (db.prepare(`PRAGMA table_info(${table})`).all() as { name: string }[]).some((c) => c.name === column);
  if (!has("bookings", "paid_at")) db.exec("ALTER TABLE bookings ADD COLUMN paid_at INTEGER");
  if (!has("bookings", "stripe_session_id")) db.exec("ALTER TABLE bookings ADD COLUMN stripe_session_id TEXT");
  if (!has("bookings", "payment_method")) db.exec("ALTER TABLE bookings ADD COLUMN payment_method TEXT");
  if (!has("users", "role")) db.exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'customer'");
  db.exec(`
    CREATE TABLE IF NOT EXISTS booking_events (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      reference  TEXT    NOT NULL REFERENCES bookings(reference) ON DELETE CASCADE,
      actor_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
      type       TEXT    NOT NULL,
      message    TEXT    NOT NULL,
      created_at INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS booking_events_reference ON booking_events(reference);
  `);
}

// Reuse one connection across hot reloads in development.
const globalForDb = globalThis as unknown as { __db?: Database.Database };
export const db = globalForDb.__db ?? open();
if (process.env.NODE_ENV !== "production") globalForDb.__db = db;
