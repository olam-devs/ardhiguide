-- Buyer land payments (separate from seller listing publication fee).
SET NAMES utf8mb4;

ALTER TABLE listings
  ADD COLUMN land_payment_amount_tzs INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN land_payment_status ENUM('none','pending','paid','waived') NOT NULL DEFAULT 'none',
  ADD COLUMN land_payment_user_id INT UNSIGNED NULL,
  ADD COLUMN land_payment_reference VARCHAR(40) NULL,
  ADD COLUMN land_paid_at TIMESTAMP NULL,
  ADD COLUMN land_payment_push_phone VARCHAR(20) NULL,
  ADD COLUMN land_payment_push_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN land_snippe_reference VARCHAR(64) NULL,
  ADD COLUMN land_snippe_status ENUM('none','pending','completed','failed','expired') NOT NULL DEFAULT 'none',
  ADD COLUMN land_snippe_last_error VARCHAR(255) NULL,
  ADD KEY idx_listings_land_payment_status (land_payment_status),
  ADD KEY idx_listings_land_snippe_reference (land_snippe_reference);
