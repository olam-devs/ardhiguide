<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid listing']);
  exit;
}

$st = db()->prepare(
  'SELECT id, created_by_user_id, payment_status, payment_amount_tzs, snippe_status, snippe_reference, snippe_last_error
   FROM listings WHERE id = ? LIMIT 1'
);
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
  http_response_code(404);
  echo json_encode(['error' => 'Not found']);
  exit;
}

$ownerId = (int)($row['created_by_user_id'] ?? 0);
$isAdmin = (($u['role'] ?? '') === 'admin');
if (!$isAdmin && $ownerId !== (int)$u['id']) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

echo json_encode([
  'listing_id' => $id,
  'payment_status' => (string)($row['payment_status'] ?? 'pending'),
  'payment_amount_tzs' => (int)($row['payment_amount_tzs'] ?? 0),
  'snippe_status' => (string)($row['snippe_status'] ?? 'none'),
  'snippe_reference' => (string)($row['snippe_reference'] ?? ''),
  'snippe_last_error' => (string)($row['snippe_last_error'] ?? ''),
  'paid' => ($row['payment_status'] ?? '') === 'paid',
]);
