-- Ardhi Guide schema (MySQL).

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  full_name VARCHAR(190) NOT NULL,
  phone VARCHAR(32) NULL,
  role ENUM('buyer','seller','agent','admin') NOT NULL DEFAULT 'buyer',
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  contact_whatsapp VARCHAR(32) NULL,
  verification_status ENUM('submitted','under_review','approved','rejected') NOT NULL DEFAULT 'submitted',
  verification_badge ENUM('none','identity_verified','docs_submitted','docs_reviewed','survey_confirmed') NOT NULL DEFAULT 'none',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  listing_package ENUM('basic','featured','premium') NOT NULL DEFAULT 'basic',
  payment_status ENUM('pending','paid','waived') NOT NULL DEFAULT 'pending',
  payment_amount_tzs INT UNSIGNED NOT NULL DEFAULT 5000,
  payment_reference VARCHAR(40) NULL,
  paid_at TIMESTAMP NULL,
  admin_notes TEXT NULL,
  published_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_listings_payment_reference (payment_reference),
  KEY idx_listings_status (verification_status),
  KEY idx_listings_region (region),
  CONSTRAINT fk_listings_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listing_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_listing_images_listing_id (listing_id),
  CONSTRAINT fk_listing_images_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Seed admin
INSERT INTO users (email, full_name, role, password_hash, is_active)
VALUES (
  'admin@ardhiguide.local',
  'Ardhi Guide Admin',
  'admin',
  '$2y$10$mbAKdvIrXGwui6c53TgVU.fnyhjIo8.8nUmHPWLkkvNtTaWkxf6fK',
  1
)
ON DUPLICATE KEY UPDATE email=email;

-- Seed a sample seller (password: Seller123!)
INSERT INTO users (email, full_name, role, password_hash, is_active)
VALUES (
  'seller@ardhiguide.local',
  'Sample Seller',
  'seller',
  '$2y$10$Jj2MURYTZNEA3bGUaliKg.l6MbfUFJ71YV6f/UkjeOg7vRfl4UAb6',
  1
)
ON DUPLICATE KEY UPDATE email=email;

