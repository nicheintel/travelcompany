-- TravelCompany database. The site creates these tables automatically on first
-- visit; you can also import this file in phpMyAdmin.

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(60)  NOT NULL,
  email           VARCHAR(254) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  role            VARCHAR(20)  NOT NULL DEFAULT 'customer',
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  email_verified_at DATETIME   NULL,
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
  contact_name      VARCHAR(100) NULL,
  bag_status        VARCHAR(10)  NULL,
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
  bought_from       VARCHAR(100) NULL,
  bought_cost       DECIMAL(10,2) NULL,
  gcash_ref         VARCHAR(40)  NULL,
  gcash_php         INT UNSIGNED NULL,
  gcash_sent_at     DATETIME     NULL,
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

-- Email confirmation links (only a SHA-256 hash of the token is stored).
CREATE TABLE IF NOT EXISTS email_verifications (
  token_hash CHAR(64)     NOT NULL PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  email      VARCHAR(254) NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  created_at DATETIME     NOT NULL,
  CONSTRAINT verify_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Promo packages, created on Admin → Packages (prices set by the travel company).
CREATE TABLE IF NOT EXISTS packages (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(80)  NOT NULL UNIQUE,
  title        VARCHAR(120) NOT NULL,
  from_code    CHAR(3)      NOT NULL,
  to_code      CHAR(3)      NOT NULL,
  nights       TINYINT UNSIGNED NOT NULL,
  hotel        VARCHAR(120) NOT NULL,
  stars        TINYINT UNSIGNED NULL,
  includes_car TINYINT(1)   NOT NULL DEFAULT 0,
  highlights   TEXT         NOT NULL,
  price        INT UNSIGNED NOT NULL,
  was_price    INT UNSIGNED NULL,
  badge        VARCHAR(30)  NULL,
  valid_from   DATE         NULL,
  valid_to     DATE         NULL,
  image        VARCHAR(100) NULL,
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  sort         INT          NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL,
  updated_at   DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploaded photos for other parts of the site, e.g. 'dest:CDG' for the Paris destination card.
CREATE TABLE IF NOT EXISTS site_images (
  name       VARCHAR(40)  NOT NULL PRIMARY KEY,
  path       VARCHAR(100) NOT NULL,
  updated_at DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LiteAPI flight offers seen in searches (about 30 minutes), so bookings use the
-- price and flights LiteAPI returned rather than anything in the page address.
CREATE TABLE IF NOT EXISTS flight_offers (
  id_hash    CHAR(64)     NOT NULL PRIMARY KEY,
  data       MEDIUMTEXT   NOT NULL,
  expires_at DATETIME     NOT NULL,
  INDEX flight_offers_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live support chat: the Chat button on the website and Admin → Support chats.
-- Times are Unix seconds. Signed-in customers are linked to their account (deleted with it);
-- visitors are recognised by a random cookie, of which only a SHA-256 hash is stored.
CREATE TABLE IF NOT EXISTS support_threads (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_type      VARCHAR(10)  NOT NULL,            -- 'member' or 'visitor'
  user_id           INT UNSIGNED NULL,
  visitor_hash      CHAR(64)     NOT NULL DEFAULT '',
  name              VARCHAR(80)  NOT NULL,
  email             VARCHAR(190) NOT NULL,
  status            VARCHAR(10)  NOT NULL DEFAULT 'open',   -- 'open' or 'done'
  created_at        INT UNSIGNED NOT NULL,
  updated_at        INT UNSIGNED NOT NULL,
  last_from         VARCHAR(10)  NOT NULL DEFAULT 'user',   -- 'user' or 'admin'
  admin_unread      INT UNSIGNED NOT NULL DEFAULT 0,
  user_unread       INT UNSIGNED NOT NULL DEFAULT 0,
  user_seen_at      INT UNSIGNED NOT NULL DEFAULT 0,
  admin_notified_at INT UNSIGNED NOT NULL DEFAULT 0,
  user_notified_at  INT UNSIGNED NOT NULL DEFAULT 0,
  page              VARCHAR(120) NOT NULL DEFAULT '',
  user_typing_at    INT UNSIGNED NOT NULL DEFAULT 0,
  admin_typing_at   INT UNSIGNED NOT NULL DEFAULT 0,
  admin_read_id     INT UNSIGNED NOT NULL DEFAULT 0,      -- last message id each side has seen ("Seen")
  user_read_id      INT UNSIGNED NOT NULL DEFAULT 0,
  ended_at          INT UNSIGNED NOT NULL DEFAULT 0,
  ended_by          VARCHAR(10)  NOT NULL DEFAULT '',
  rating            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  feedback          VARCHAR(500) NOT NULL DEFAULT '',
  rated_at          INT UNSIGNED NOT NULL DEFAULT 0,
  INDEX support_threads_user (user_id),
  INDEX support_threads_visitor (visitor_hash),
  INDEX support_threads_status (status, updated_at),
  CONSTRAINT support_threads_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id  INT UNSIGNED NOT NULL,
  sender     VARCHAR(10)  NOT NULL,                    -- 'user' or 'admin'
  body       TEXT         NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  INDEX support_messages_thread (thread_id, id),
  CONSTRAINT support_messages_thread_fk FOREIGN KEY (thread_id) REFERENCES support_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
