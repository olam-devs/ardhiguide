-- =========================================================================
-- Ardhi Guide consolidated install script (MySQL / MariaDB).
-- Run this once in phpMyAdmin (or `mysql < install.sql`) on a fresh database.
-- It is safe to re-run: every CREATE uses IF NOT EXISTS and every ALTER is
-- wrapped in a procedure that skips already-existing columns / keys.
-- =========================================================================

SET NAMES utf8mb4;
SET time_zone = '+03:00';

-- ---------- users ----------
-- Phone is the primary identifier (required, unique).
-- Email is optional but unique when supplied.
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NULL,
  full_name VARCHAR(190) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  role ENUM('buyer','seller','agent','admin') NOT NULL DEFAULT 'buyer',
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_email (email),
  UNIQUE KEY uniq_users_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- listings ----------
CREATE TABLE IF NOT EXISTS listings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_by_user_id INT UNSIGNED NULL,
  title VARCHAR(220) NOT NULL,
  category ENUM('residential','agricultural','commercial','industrial','other') NOT NULL DEFAULT 'residential',
  region VARCHAR(120) NOT NULL,
  district VARCHAR(120) NULL,
  ward VARCHAR(120) NULL,
  location_text VARCHAR(220) NULL,
  size_text VARCHAR(80) NULL,
  price_tzs BIGINT UNSIGNED NULL,
  description TEXT NULL,
  video_path VARCHAR(255) NULL,
  video_mime VARCHAR(60) NULL,
  video_size_bytes INT UNSIGNED NULL,
  contact_whatsapp VARCHAR(32) NULL,
  verification_status ENUM('submitted','under_review','approved','rejected') NOT NULL DEFAULT 'submitted',
  verification_badge ENUM('none','identity_verified','docs_submitted','docs_reviewed','survey_confirmed') NOT NULL DEFAULT 'none',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  listing_package ENUM('basic','featured','premium') NOT NULL DEFAULT 'basic',
  payment_status ENUM('pending','paid','waived') NOT NULL DEFAULT 'pending',
  payment_amount_tzs INT UNSIGNED NOT NULL DEFAULT 5000,
  payment_reference VARCHAR(40) NULL,
  payment_push_phone VARCHAR(32) NULL,
  payment_push_enabled TINYINT(1) NOT NULL DEFAULT 0,
  snippe_reference VARCHAR(64) NULL,
  snippe_status ENUM('none','pending','completed','failed','expired') NOT NULL DEFAULT 'none',
  snippe_last_error VARCHAR(255) NULL,
  paid_at TIMESTAMP NULL,
  admin_notes TEXT NULL,
  published_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_listings_payment_reference (payment_reference),
  KEY idx_listings_snippe_reference (snippe_reference),
  KEY idx_listings_status (verification_status),
  KEY idx_listings_region (region),
  CONSTRAINT fk_listings_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- listing_images ----------
CREATE TABLE IF NOT EXISTS listing_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_listing_images_listing_id (listing_id),
  CONSTRAINT fk_listing_images_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- enquiries ----------
CREATE TABLE IF NOT EXISTS enquiries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  name VARCHAR(190) NULL,
  phone VARCHAR(32) NOT NULL,
  interest VARCHAR(120) NULL,
  message TEXT NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enquiries_listing_id (listing_id),
  KEY idx_enquiries_user_id (user_id),
  CONSTRAINT fk_enquiries_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  CONSTRAINT fk_enquiries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- listing_documents ----------
CREATE TABLE IF NOT EXISTS listing_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  uploaded_by_user_id INT UNSIGNED NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(128) NOT NULL,
  mime VARCHAR(127) NULL,
  size_bytes INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_listing_documents_stored (stored_name),
  KEY idx_listing_documents_listing (listing_id),
  CONSTRAINT fk_listing_documents_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_listing_documents_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- payment_categories ----------
CREATE TABLE IF NOT EXISTS payment_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  subtitle VARCHAR(255) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_by_user_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pc_published (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- payment_steps ----------
CREATE TABLE IF NOT EXISTS payment_steps (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ps_category (category_id, sort_order),
  CONSTRAINT fk_ps_category FOREIGN KEY (category_id) REFERENCES payment_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Snippe webhook deduplication ----------
CREATE TABLE IF NOT EXISTS snippe_webhook_events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id VARCHAR(80) NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  snippe_reference VARCHAR(64) NULL,
  listing_id INT UNSIGNED NULL,
  payload_json MEDIUMTEXT NOT NULL,
  processed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_snippe_event_id (event_id),
  KEY idx_snippe_wh_listing (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Seed accounts (no-op if they already exist) ----------
-- admin@ardhiguide.local / 255657925368 -- password: Admin123!
INSERT INTO users (email, full_name, phone, role, password_hash, is_active)
VALUES (
  'admin@ardhiguide.local',
  'Ardhi Guide Admin',
  '255657925368',
  'admin',
  '$2y$10$mbAKdvIrXGwui6c53TgVU.fnyhjIo8.8nUmHPWLkkvNtTaWkxf6fK',
  1
)
ON DUPLICATE KEY UPDATE email = email;

-- seller@ardhiguide.local / 255700000001 -- password: Seller123!
INSERT INTO users (email, full_name, phone, role, password_hash, is_active)
VALUES (
  'seller@ardhiguide.local',
  'Sample Seller',
  '255700000001',
  'seller',
  '$2y$10$Jj2MURYTZNEA3bGUaliKg.l6MbfUFJ71YV6f/UkjeOg7vRfl4UAb6',
  1
)
ON DUPLICATE KEY UPDATE email = email;

-- Backfill payment_reference for any legacy listings created before migration_004
UPDATE listings
SET payment_status = 'waived',
    payment_reference = CONCAT('AG-', id, '-LEGACY')
WHERE payment_reference IS NULL;
