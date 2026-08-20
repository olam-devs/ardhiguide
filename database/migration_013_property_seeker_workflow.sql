-- Property-seeker workflow, homepage curation, provider coordination, and listing shares.
SET NAMES utf8mb4;

ALTER TABLE listings
  ADD COLUMN show_on_homepage TINYINT(1) NOT NULL DEFAULT 0,
  ADD KEY idx_listings_homepage (show_on_homepage, verification_status, is_taken, published_at);

ALTER TABLE enquiries
  ADD COLUMN request_type ENUM('information','viewing','contact','match_me') NOT NULL DEFAULT 'information',
  ADD COLUMN provider_preference ENUM('listing_provider','admin_select') NOT NULL DEFAULT 'listing_provider',
  ADD COLUMN assigned_provider_user_id INT UNSIGNED NULL,
  ADD COLUMN assigned_at TIMESTAMP NULL,
  ADD KEY idx_enquiries_provider (assigned_provider_user_id, status, created_at);

ALTER TABLE messages
  ADD COLUMN listing_id INT UNSIGNED NULL,
  ADD KEY idx_messages_listing (listing_id),
  ADD CONSTRAINT fk_messages_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL;
