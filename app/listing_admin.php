<?php

declare(strict_types=1);

/**
 * Permanently delete a listing and its uploaded media files.
 */
function listing_admin_delete(int $listingId): ?string {
  $st = db()->prepare('SELECT id, video_path FROM listings WHERE id = ? LIMIT 1');
  $st->execute([$listingId]);
  $row = $st->fetch();
  if (!$row) {
    return 'Listing not found.';
  }

  $imgSt = db()->prepare('SELECT file_path FROM listing_images WHERE listing_id = ?');
  $imgSt->execute([$listingId]);
  foreach ($imgSt->fetchAll() as $im) {
    $rel = public_file((string)($im['file_path'] ?? ''));
    $abs = dirname(__DIR__) . '/public/' . ltrim($rel, '/');
    if (is_file($abs)) {
      @unlink($abs);
    }
  }

  listing_video_delete((string)($row['video_path'] ?? ''));

  $docSt = db()->prepare('SELECT stored_name FROM listing_documents WHERE listing_id = ?');
  $docSt->execute([$listingId]);
  $privDir = storage_private_dir();
  foreach ($docSt->fetchAll() as $doc) {
    $abs = $privDir . DIRECTORY_SEPARATOR . (string)$doc['stored_name'];
    if (is_file($abs)) {
      @unlink($abs);
    }
  }

  db()->prepare('DELETE FROM listings WHERE id = ?')->execute([$listingId]);
  return null;
}

/**
 * Update listing core fields (admin). Returns null on success or error message.
 *
 * @param array<string,mixed> $data
 */
function listing_admin_update(int $listingId, array $data): ?string {
  $title = trim((string)($data['title'] ?? ''));
  $region = trim((string)($data['region'] ?? ''));
  if ($title === '' || $region === '') {
    return 'Title and region are required.';
  }

  $category = (string)($data['category'] ?? 'residential');
  if (!in_array($category, ['residential', 'agricultural', 'commercial', 'industrial', 'other'], true)) {
    $category = 'other';
  }

  $locationText = trim((string)($data['location_text'] ?? ''));
  $sizeText = trim((string)($data['size_text'] ?? ''));
  $desc = trim((string)($data['description'] ?? ''));
  $waRaw = trim((string)($data['contact_whatsapp'] ?? ''));
  $wa = $waRaw === '' ? null : normalize_phone($waRaw);
  if ($wa === '') {
    $wa = null;
  }

  $priceRaw = (string)($data['price_tzs'] ?? '');
  $priceDigits = preg_replace('/\D+/', '', $priceRaw);
  $price = $priceDigits !== '' ? (int)$priceDigits : null;

  $stmt = db()->prepare(
    'UPDATE listings SET title = ?, category = ?, region = ?, location_text = ?, size_text = ?,
     price_tzs = ?, description = ?, contact_whatsapp = ? WHERE id = ?'
  );
  $stmt->execute([
    $title,
    $category,
    $region,
    $locationText !== '' ? $locationText : null,
    $sizeText !== '' ? $sizeText : null,
    $price,
    $desc !== '' ? $desc : null,
    $wa,
    $listingId,
  ]);
  return null;
}
