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
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
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
  stored_name CHAR(36) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime VARCHAR(60) NOT NULL,
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
