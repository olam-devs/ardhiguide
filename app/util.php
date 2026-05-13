<?php

declare(strict_types=1);

function h(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void {
  header('Location: ' . APP_BASE_URL . $path);
  exit;
}

function whatsapp_link(string $message, ?string $toNumber = null): string {
  $to = $toNumber ?: WHATSAPP_DEFAULT_NUMBER;
  return 'https://wa.me/' . rawurlencode($to) . '?text=' . rawurlencode($message);
}

function public_file(string $path): string {
  // Backward compatibility for earlier stored paths like "public/uploads/x.jpg"
  if (str_starts_with($path, 'public/')) return substr($path, strlen('public/'));
  return $path;
}

function format_tzs(?string $amount): string {
  if ($amount === null || $amount === '') return 'N/A';
  $n = preg_replace('/[^\d]/', '', $amount);
  if ($n === '') return 'N/A';
  return 'TSh ' . number_format((int)$n, 0, '.', ',');
}

/** Writable dir for non-public uploads (title deeds, PDFs). Not web-accessible directly. */
function storage_private_dir(): string {
  $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private';
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  return $dir;
}

/** @return array<string,string> mime => extension */
function listing_document_mime_map(): array {
  return [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
  ];
}

/**
 * Save one uploaded verification document. Returns null on success, or error message.
 * @param array{name?:string,tmp_name?:string,type?:string,size?:int,error?:int} $file
 */
function save_listing_document_upload(int $listingId, int $uploadedByUserId, array $file): ?string {
  $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($err === UPLOAD_ERR_NO_FILE) {
    return null;
  }
  if ($err !== UPLOAD_ERR_OK) {
    return 'Upload failed.';
  }
  $tmp = (string)($file['tmp_name'] ?? '');
  if ($tmp === '' || !is_uploaded_file($tmp)) {
    return 'Invalid upload.';
  }
  $maxBytes = 5 * 1024 * 1024;
  if ((int)($file['size'] ?? 0) > $maxBytes) {
    return 'File too large (max 5 MB).';
  }
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($tmp) ?: '';
  $map = listing_document_mime_map();
  if (!isset($map[$mime])) {
    return 'Only PDF, JPG, PNG, or WebP allowed.';
  }
  $ext = $map[$mime];
  $stored = bin2hex(random_bytes(16)) . '.' . $ext;
  $dest = storage_private_dir() . DIRECTORY_SEPARATOR . $stored;
  if (!move_uploaded_file($tmp, $dest)) {
    return 'Could not store file.';
  }
  $orig = basename((string)($file['name'] ?? 'document'));
  if (strlen($orig) > 220) {
    $orig = substr($orig, 0, 220);
  }
  $stmt = db()->prepare(
    'INSERT INTO listing_documents (listing_id, uploaded_by_user_id, original_name, stored_name, mime, size_bytes)
     VALUES (?,?,?,?,?,?)'
  );
  $stmt->execute([
    $listingId,
    $uploadedByUserId,
    $orig,
    $stored,
    $mime,
    (int)($file['size'] ?? 0),
  ]);
  return null;
}

function count_listing_documents(int $listingId): int {
  $st = db()->prepare('SELECT COUNT(*) AS c FROM listing_documents WHERE listing_id = ?');
  $st->execute([$listingId]);
  $row = $st->fetch();
  return (int)($row['c'] ?? 0);
}

