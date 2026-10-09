-- LamazonLoads database. The site runs this automatically on first visit;
-- you can also import it in phpMyAdmin.

CREATE TABLE IF NOT EXISTS meta (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  account_type VARCHAR(30) NOT NULL DEFAULT 'driver',
  city VARCHAR(120) NOT NULL DEFAULT '',   -- "Atlanta, GA", picked from assets/data/us-cities.txt
  vehicle VARCHAR(120) NOT NULL DEFAULT '', -- keys of APPLY_VEHICLES, comma-separated, chosen at sign-up
  vehicle_other VARCHAR(80) NOT NULL DEFAULT '', -- typed when "Other" is one of them
  is_admin TINYINT(1) NOT NULL DEFAULT 0,   -- staff (admins and moderators)
  staff_role VARCHAR(20) NOT NULL DEFAULT '', -- staff only: 'admin' = everything, 'moderator' = day-to-day tools
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  email_verified_at DATETIME NULL,
  verify_token CHAR(64) NULL,
  verify_expires DATETIME NULL,
  verify_sent_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_profiles (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  company_name VARCHAR(120) NOT NULL DEFAULT '',
  mc_number VARCHAR(20) NOT NULL DEFAULT '',
  dot_number VARCHAR(20) NOT NULL DEFAULT '',
  equipment VARCHAR(30) NOT NULL DEFAULT '',
  vehicle VARCHAR(120) NOT NULL DEFAULT '',
  home_zip VARCHAR(10) NOT NULL DEFAULT '',
  service_radius VARCHAR(40) NOT NULL DEFAULT '',
  availability VARCHAR(30) NOT NULL DEFAULT '',
  years_experience VARCHAR(10) NOT NULL DEFAULT '',
  insurance_provider VARCHAR(120) NOT NULL DEFAULT '',
  insurance_expires DATE NULL,
  about TEXT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  stored_name VARCHAR(40) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_doc_user (user_id),
  CONSTRAINT fk_doc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  category VARCHAR(30) NOT NULL,
  location VARCHAR(120) NOT NULL DEFAULT '',
  equipment VARCHAR(120) NOT NULL DEFAULT '',
  pay VARCHAR(120) NOT NULL DEFAULT '',
  schedule VARCHAR(120) NOT NULL DEFAULT '',
  summary VARCHAR(300) NOT NULL DEFAULT '',
  description TEXT NULL,
  requirements TEXT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'open',
  hires_needed VARCHAR(10) NOT NULL DEFAULT '1',
  country VARCHAR(40) NOT NULL DEFAULT 'United States',
  language VARCHAR(20) NOT NULL DEFAULT 'English',
  job_types VARCHAR(100) NOT NULL DEFAULT '',
  apply_method VARCHAR(10) NOT NULL DEFAULT 'site',
  apply_url VARCHAR(300) NOT NULL DEFAULT '',
  resume VARCHAR(10) NOT NULL DEFAULT 'optional',
  notify_on TINYINT(1) NOT NULL DEFAULT 1,
  notify_emails VARCHAR(300) NOT NULL DEFAULT '',
  contact_by_email TINYINT(1) NOT NULL DEFAULT 0,
  contact_email VARCHAR(190) NOT NULL DEFAULT '',
  fair_chance TINYINT(1) NOT NULL DEFAULT 0,
  background_check TINYINT(1) NOT NULL DEFAULT 0,
  hiring_timeline VARCHAR(10) NOT NULL DEFAULT '',
  auto_welcome TINYINT(1) NOT NULL DEFAULT 0,
  welcome_message TEXT NULL,
  auto_review TINYINT(1) NOT NULL DEFAULT 0,
  auto_remind TINYINT(1) NOT NULL DEFAULT 0,
  remind_days TINYINT UNSIGNED NOT NULL DEFAULT 2,
  auto_decline TINYINT(1) NOT NULL DEFAULT 0,
  decline_days TINYINT UNSIGNED NOT NULL DEFAULT 5,
  auto_close TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_job_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- job_id 0 = general application to join the driver network
CREATE TABLE IF NOT EXISTS applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  job_id INT UNSIGNED NOT NULL DEFAULT 0,
  message TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  admin_note TEXT NULL,
  resume_doc_id INT UNSIGNED NULL,
  reminded_at DATETIME NULL,
  auto_note VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_app_user_job (user_id, job_id),
  KEY idx_app_status (status),
  CONSTRAINT fk_app_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  topic VARCHAR(40) NOT NULL DEFAULT '',
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_attempt_email (email, created_at),
  KEY idx_attempt_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Live chat (the Chat button and Admin -> Support chats). One conversation per member or visitor.
-- account_id = users.id for members, 0 for visitors (recognised by visitor_hash, a hash of a random cookie).
-- Times are Unix timestamps (seconds).
CREATE TABLE IF NOT EXISTS support_threads (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_type VARCHAR(10) NOT NULL,
  account_id INT UNSIGNED NOT NULL DEFAULT 0,
  visitor_hash CHAR(64) NOT NULL DEFAULT '',
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'open',
  page VARCHAR(80) NOT NULL DEFAULT '',
  last_from VARCHAR(10) NOT NULL DEFAULT 'user',
  admin_unread INT UNSIGNED NOT NULL DEFAULT 0,
  user_unread INT UNSIGNED NOT NULL DEFAULT 0,
  user_seen_at INT UNSIGNED NOT NULL DEFAULT 0,
  admin_notified_at INT UNSIGNED NOT NULL DEFAULT 0,
  user_notified_at INT UNSIGNED NOT NULL DEFAULT 0,
  user_typing_at INT UNSIGNED NOT NULL DEFAULT 0,
  admin_typing_at INT UNSIGNED NOT NULL DEFAULT 0,
  admin_read_id INT UNSIGNED NOT NULL DEFAULT 0,
  user_read_id INT UNSIGNED NOT NULL DEFAULT 0,
  ended_at INT UNSIGNED NOT NULL DEFAULT 0,
  ended_by VARCHAR(10) NOT NULL DEFAULT '',
  rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
  feedback VARCHAR(500) NOT NULL DEFAULT '',
  rated_at INT UNSIGNED NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  KEY idx_chat_owner (account_type, account_id),
  KEY idx_chat_visitor (visitor_hash),
  KEY idx_chat_status (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id INT UNSIGNED NOT NULL,
  sender VARCHAR(10) NOT NULL,
  body TEXT NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_msg_thread (thread_id, id),
  CONSTRAINT fk_msg_thread FOREIGN KEY (thread_id) REFERENCES support_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Simple rate limits (chat messages, new chats per IP)
CREATE TABLE IF NOT EXISTS rate_hits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(20) NOT NULL,
  k VARCHAR(80) NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY idx_hits (kind, k, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs a member saved with the heart on Careers
CREATE TABLE IF NOT EXISTS saved_jobs (
  user_id INT UNSIGNED NOT NULL,
  job_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, job_id),
  CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Partner with us": businesses asking for a call (last mile, healthcare, dedicated fleet…)
CREATE TABLE IF NOT EXISTS partner_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  company VARCHAR(120) NOT NULL,
  job_title VARCHAR(100) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  service VARCHAR(20) NOT NULL,
  location VARCHAR(120) NOT NULL DEFAULT '',
  volume VARCHAR(20) NOT NULL DEFAULT '',
  best_time VARCHAR(20) NOT NULL DEFAULT '',
  message TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  admin_note TEXT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_partner_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site photos uploaded in Admin -> Site photos (vehicle pictures and the "On the road" gallery)
CREATE TABLE IF NOT EXISTS site_photos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slot VARCHAR(20) NOT NULL,
  file VARCHAR(60) NOT NULL,
  caption VARCHAR(160) NOT NULL DEFAULT '',
  sort INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  KEY idx_photo_slot (slot, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Walmart daily route program: cities drivers can choose when they apply (Admin -> Walmart routes)
CREATE TABLE IF NOT EXISTS walmart_routes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  city VARCHAR(80) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  KEY idx_walmart_active (active, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users.reset_token / reset_expires / reset_sent_at (forgot password) are added by includes/db.php on existing sites
-- users.must_change_password / added_by and documents.added_by (members added by staff) are added by includes/db.php too

-- Driver onboarding on the website: upload documents → staff review → sign agreement → Telegram
CREATE TABLE IF NOT EXISTS onboarding (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  track VARCHAR(20) NOT NULL DEFAULT 'dispatch',
  stage VARCHAR(20) NOT NULL DEFAULT 'documents',
  submitted_at DATETIME NULL,
  review_note TEXT NULL,
  review_fix TEXT NULL,             -- the reasons ticked in Request changes, item by item (JSON)
  reviewed_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  contract_version INT UNSIGNED NULL,
  contract_title VARCHAR(190) NULL,
  contract_text MEDIUMTEXT NULL,
  signed_at DATETIME NULL,
  signed_name VARCHAR(120) NULL,
  signed_company VARCHAR(120) NULL,
  signature MEDIUMTEXT NULL,
  signed_ip VARCHAR(45) NULL,
  emergency_name VARCHAR(120) NULL,
  emergency_relation VARCHAR(60) NULL,
  emergency_phone VARCHAR(30) NULL,
  telegram_sent_at DATETIME NULL,
  marked_at DATETIME NULL,          -- marked as onboarded by staff (e.g. a driver onboarded outside the website)
  marked_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_onb_stage (stage),
  CONSTRAINT fk_onb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agreements drivers sign online, one per track (edited in Admin → Contracts)
CREATE TABLE IF NOT EXISTS contracts (
  track VARCHAR(20) NOT NULL PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  required TINYINT(1) NOT NULL DEFAULT 0,
  ask_emergency TINYINT(1) NOT NULL DEFAULT 1,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- driver_profiles.payout_method / payout_name / payout_handle / payout_updated_at are added by includes/db.php
-- onboarding.access_token / opened_at (onboarding opens from the email link) are added by includes/db.php too

-- Onboarding emails staff send by hand (Admin → Send onboarding email). user_id is set when the person already has an
-- account, or once they create one: their onboarding then starts on that email's track (see onboarding_apply_invite()).
CREATE TABLE IF NOT EXISTS onboarding_emails (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  user_id INT UNSIGNED NULL,
  type VARCHAR(20) NOT NULL,
  city VARCHAR(80) NOT NULL DEFAULT '',
  first_name VARCHAR(60) NOT NULL DEFAULT '',
  had_account TINYINT(1) NOT NULL DEFAULT 0,
  sent_by INT UNSIGNED NULL,
  sent_at DATETIME NOT NULL,
  joined_at DATETIME NULL,
  KEY idx_onbmail_email (email),
  KEY idx_onbmail_sent (sent_at),
  CONSTRAINT fk_onbmail_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Automatic follow-ups (Admin → Automatic follow-ups): one row per reminder sent. "anchor" is when the stage began,
-- so a new round (for example a second "changes requested") starts its reminders again.
CREATE TABLE IF NOT EXISTS followup_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind VARCHAR(20) NOT NULL,
  email VARCHAR(190) NOT NULL,
  user_id INT UNSIGNED NULL,
  step TINYINT UNSIGNED NOT NULL,
  anchor DATETIME NOT NULL,
  sent_at DATETIME NOT NULL,
  failed TINYINT(1) NOT NULL DEFAULT 0,  -- the mail server refused it: tried again a day later
  UNIQUE KEY uq_followup (kind, email, anchor, step),
  KEY idx_followup_email (email, sent_at),
  KEY idx_followup_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- People who tapped "Stop reminders" (or were paused by staff): no automatic follow-ups go to these addresses
CREATE TABLE IF NOT EXISTS followup_optout (
  email VARCHAR(190) NOT NULL PRIMARY KEY,
  by_staff INT UNSIGNED NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Records your team adds for drivers who don't have an account yet (Drivers & members → Add a record): no login and
-- no email. When someone signs up with the same email and confirms it, the record moves onto their account
-- (merged_user_id) and stays there as "From your records". Deleted together with that account.
CREATE TABLE IF NOT EXISTS member_records (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  city VARCHAR(120) NOT NULL DEFAULT '',
  account_type VARCHAR(30) NOT NULL DEFAULT 'driver',
  vehicle VARCHAR(120) NOT NULL DEFAULT '',
  vehicle_other VARCHAR(80) NOT NULL DEFAULT '',
  onboarded VARCHAR(20) NOT NULL DEFAULT '',   -- '' = not yet, or the program they finished with our team (dispatch / walmart)
  notes TEXT NULL,                             -- private: staff only
  added_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  merged_user_id INT UNSIGNED NULL,
  merged_at DATETIME NULL,
  UNIQUE KEY uq_record_email (email),
  KEY idx_record_merged (merged_user_id),
  CONSTRAINT fk_record_user FOREIGN KEY (merged_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every email the website sends (only the time): Hostinger limits each mailbox per 24 hours (Business Starter: 1,000),
-- so Send onboarding email stops just before it and automatic reminders leave room. Kept for 3 days.
CREATE TABLE IF NOT EXISTS mail_sent (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sent_at DATETIME NOT NULL,
  KEY idx_mail_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Send onboarding email → Send to a list: the people waiting for their Dispatch or Walmart email. The scheduled task
-- sends them a few at a time, up to the daily amount you choose, between 9 am and 7 pm New York time.
CREATE TABLE IF NOT EXISTS onboarding_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  first_name VARCHAR(60) NOT NULL DEFAULT '',
  type VARCHAR(20) NOT NULL,                     -- dispatch / walmart
  city VARCHAR(80) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'waiting',  -- waiting · sent · skipped · failed · removed
  note VARCHAR(190) NOT NULL DEFAULT '',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  added_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  last_try_at DATETIME NULL,
  sent_at DATETIME NULL,
  KEY idx_queue_status (status, id),
  KEY idx_queue_email (email),
  KEY idx_queue_sent (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
