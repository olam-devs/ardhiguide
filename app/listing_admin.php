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
  $type = (string)($data['listing_type'] ?? 'plot_for_sale');
  $furnishing = (string)($data['furnishing_status'] ?? 'not_applicable');
  $regionCode = trim((string)($data['region_code'] ?? ''));
  $districtCode = trim((string)($data['district_code'] ?? ''));
  $wardCode = trim((string)($data['ward_code'] ?? ''));
  $location = location_names($regionCode, $districtCode, $wardCode);
  if ($title === '' || !$location) return 'Title and a valid location are required.';
  if (!in_array($type, ['plot_for_sale','house_for_sale','house_for_rent','apartment'], true)) $type = 'plot_for_sale';
  if (!in_array($type, ['house_for_rent','apartment'], true)) $furnishing = 'not_applicable';

  $locationText = trim((string)($data['location_text'] ?? ''));
  $sizeText = trim((string)($data['size_text'] ?? ''));
  $desc = trim((string)($data['description'] ?? ''));
  $minDigits = preg_replace('/\D+/', '', (string)($data['price_min_tzs'] ?? ''));
  $maxDigits = preg_replace('/\D+/', '', (string)($data['price_max_tzs'] ?? ''));
  $min = $minDigits !== '' ? (int)$minDigits : null;
  $max = $maxDigits !== '' ? (int)$maxDigits : null;
  if ($min === null || $max === null || $min > $max) return 'Enter a valid minimum-to-maximum price range.';

  $stmt = db()->prepare(
    'UPDATE listings SET title=?,listing_type=?,furnishing_status=?,region=?,district=?,ward=?,region_code=?,district_code=?,ward_code=?,
     location_text=?,size_text=?,price_tzs=?,price_min_tzs=?,price_max_tzs=?,description=?,contact_whatsapp=NULL WHERE id=?'
  );
  $stmt->execute([
    $title,
    $type,
    $furnishing,
    $location['region'],
    $location['district'],
    $location['ward'],
    $regionCode,
    $districtCode,
    $wardCode,
    $locationText !== '' ? $locationText : null,
    $sizeText !== '' ? $sizeText : null,
    $min,
    $min,
    $max,
    $desc !== '' ? $desc : null,
    $listingId,
  ]);
  return null;
}

/** @return list<array{file_path:string}> */
function listing_admin_images(int $listingId): array {
  $st = db()->prepare('SELECT file_path FROM listing_images WHERE listing_id = ? ORDER BY id ASC');
  $st->execute([$listingId]);
  return $st->fetchAll();
}
