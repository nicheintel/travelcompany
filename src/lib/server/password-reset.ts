import "server-only";
import { createHash, randomBytes } from "node:crypto";
import { db } from "./db";

const RESET_TTL_MS = 60 * 60 * 1000; // 1 hour

function hashToken(token: string) {
  return createHash("sha256").update(token).digest("hex");
}

/** Creates a single-use reset token (only its hash is stored) and invalidates older ones. */
export function createResetToken(userId: number) {
  const token = randomBytes(32).toString("base64url");
  const now = Date.now();
  db.transaction(() => {
    db.prepare("DELETE FROM password_resets WHERE user_id = ?").run(userId);
    db.prepare(
      "INSERT INTO password_resets (token_hash, user_id, expires_at, created_at) VALUES (?, ?, ?, ?)",
    ).run(hashToken(token), userId, now + RESET_TTL_MS, now);
  })();
  return token;
}

/** The user id for a valid, unused, unexpired token — otherwise null. */
export function findResetUser(token: string): number | null {
  if (!token) return null;
  const row = db
    .prepare(
      "SELECT user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?",
    )
    .get(hashToken(token), Date.now()) as { user_id: number } | undefined;
  return row?.user_id ?? null;
}

/**
 * Atomically: set the new password, burn the token and sign the user out everywhere.
 * Returns the user id, or null if the token was already used or expired.
 */
export function consumeResetToken(token: string, newPasswordHash: string): number | null {
  return db.transaction(() => {
    const userId = findResetUser(token);
    if (!userId) return null;
    db.prepare("UPDATE password_resets SET used_at = ? WHERE token_hash = ?").run(Date.now(), hashToken(token));
    db.prepare("UPDATE users SET password_hash = ? WHERE id = ?").run(newPasswordHash, userId);
    db.prepare("DELETE FROM sessions WHERE user_id = ?").run(userId);
    return userId;
  })();
}
