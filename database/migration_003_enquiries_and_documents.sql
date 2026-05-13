-- Run once on existing DB (phpMyAdmin → SQL): links enquiries to users + private listing documents.
-- Backup first.

ALTER TABLE enquiries
  ADD COLUMN user_id INT UNSIGNED NULL AFTER listing_id,
  ADD KEY idx_enquiries_user_id (user_id);

ALTER TABLE enquiries
  ADD CONSTRAINT fk_enquiries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

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
