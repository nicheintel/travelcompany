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
  `);
  return db;
}

// Reuse one connection across hot reloads in development.
const globalForDb = globalThis as unknown as { __db?: Database.Database };
export const db = globalForDb.__db ?? open();
if (process.env.NODE_ENV !== "production") globalForDb.__db = db;
