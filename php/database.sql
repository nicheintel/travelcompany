-- TravelCompany database. The site creates these tables automatically on first
-- visit; you can also import this file in phpMyAdmin.

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(60)  NOT NULL,
  email           VARCHAR(254) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  role            VARCHAR(20)  NOT NULL DEFAULT 'customer',
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference         VARCHAR(12)  NOT NULL UNIQUE,
  user_id           INT UNSIGNED NOT NULL,
  kind              VARCHAR(10)  NOT NULL,
  status            VARCHAR(10)  NOT NULL DEFAULT 'reserved',
  quote_json        MEDIUMTEXT   NOT NULL,
  travelers_json    TEXT         NOT NULL,
  contact_email     VARCHAR(254) NOT NULL,
  contact_phone     VARCHAR(30)  NOT NULL,
  total             INT UNSIGNED NOT NULL,
  start_date        DATE         NOT NULL,
  created_at        DATETIME     NOT NULL,
  paid_at           DATETIME     NULL,
  cancelled_at      DATETIME     NULL,
  payment_method    VARCHAR(60)  NULL,
  payment_ref       VARCHAR(255) NULL,
  ticketed_at       DATETIME     NULL,
  supplier_ref      VARCHAR(100) NULL,
  ticket_note       TEXT         NULL,
  INDEX bookings_user (user_id),
  CONSTRAINT bookings_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_events (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference  VARCHAR(12)  NOT NULL,
  actor_id   INT UNSIGNED NULL,
  type       VARCHAR(12)  NOT NULL,
  message    TEXT         NOT NULL,
  created_at DATETIME     NOT NULL,
  INDEX events_reference (reference),
  CONSTRAINT events_booking_fk FOREIGN KEY (reference) REFERENCES bookings(reference) ON DELETE CASCADE,
  CONSTRAINT events_actor_fk FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  token_hash CHAR(64)     NOT NULL PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  created_at DATETIME     NOT NULL,
  CONSTRAINT resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  rkey     VARCHAR(191) NOT NULL PRIMARY KEY,
  hits     INT UNSIGNED NOT NULL,
  reset_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  name       VARCHAR(64) NOT NULL PRIMARY KEY,
  value      TEXT        NOT NULL,
  updated_at DATETIME    NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
