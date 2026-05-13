<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  exit('Not found');
}

$stmt = db()->prepare(
  'SELECT d.stored_name, d.original_name, d.mime, l.created_by_user_id
   FROM listing_documents d
   INNER JOIN listings l ON l.id = d.listing_id
   WHERE d.id = ?'
);
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
  http_response_code(404);
  exit('Not found');
}

$isAdmin = (($u['role'] ?? '') === 'admin');
$isOwner = ((int)$row['created_by_user_id'] === (int)$u['id']);
if (!$isAdmin && !$isOwner) {
  http_response_code(403);
  exit('Forbidden');
}

$stored = (string)$row['stored_name'];
$path = storage_private_dir() . DIRECTORY_SEPARATOR . $stored;
if (!is_file($path)) {
  http_response_code(404);
  exit('File missing');
}

$mime = (string)($row['mime'] ?: 'application/octet-stream');
$downloadName = (string)($row['original_name'] ?: 'document');
$downloadName = preg_replace('/[^\pL\pN\.\-_\s]/u', '_', $downloadName) ?? '';
if ($downloadName === '') {
  $downloadName = 'document';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
