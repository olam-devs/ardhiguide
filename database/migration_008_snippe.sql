-- Snippe payment gateway: per-listing push phone, API reference, webhook dedup.

SET NAMES utf8mb4;

ALTER TABLE listings
  ADD COLUMN payment_push_phone VARCHAR(32) NULL AFTER payment_reference,
  ADD COLUMN payment_push_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_push_phone,
  ADD COLUMN snippe_reference VARCHAR(64) NULL AFTER payment_push_enabled,
  ADD COLUMN snippe_status ENUM('none','pending','completed','failed','expired') NOT NULL DEFAULT 'none' AFTER snippe_reference,
  ADD COLUMN snippe_last_error VARCHAR(255) NULL AFTER snippe_status;

ALTER TABLE listings
  ADD KEY idx_listings_snippe_reference (snippe_reference);

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
