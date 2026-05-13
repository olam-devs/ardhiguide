-- Listing packages, payment tracking, admin internal notes.
-- Run once on existing DBs (or use: php scripts/migrate.php from project root).

ALTER TABLE listings
  ADD COLUMN listing_package ENUM('basic','featured','premium') NOT NULL DEFAULT 'basic' AFTER is_featured,
  ADD COLUMN payment_status ENUM('pending','paid','waived') NOT NULL DEFAULT 'pending' AFTER listing_package,
  ADD COLUMN payment_amount_tzs INT UNSIGNED NOT NULL DEFAULT 5000 AFTER payment_status,
  ADD COLUMN payment_reference VARCHAR(40) NULL AFTER payment_amount_tzs,
  ADD COLUMN paid_at TIMESTAMP NULL AFTER payment_reference,
  ADD COLUMN admin_notes TEXT NULL AFTER paid_at;

ALTER TABLE listings
  ADD UNIQUE KEY uniq_listings_payment_reference (payment_reference);

UPDATE listings
SET payment_status = 'waived',
    payment_reference = CONCAT('AG-', id, '-LEGACY')
WHERE payment_reference IS NULL;
