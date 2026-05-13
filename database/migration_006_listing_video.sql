-- Optional short video per listing (max ~1 minute, capped 25 MB).

ALTER TABLE listings
  ADD COLUMN video_path VARCHAR(255) NULL AFTER description,
  ADD COLUMN video_mime VARCHAR(60) NULL AFTER video_path,
  ADD COLUMN video_size_bytes INT UNSIGNED NULL AFTER video_mime;
