<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
$kind = listing_pay_kind_normalize(isset($_GET['for']) ? (string)$_GET['for'] : null) ?? LISTING_PAY_LISTING_FEE;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid listing']);
  exit;
}

$st = db()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
  http_response_code(404);
  echo json_encode(['error' => 'Not found']);
  exit;
}

if (listing_pay_access_error($row, $u, $kind) !== null) {
  http_response_code(403);
  echo json_encode(['error' => 'Forbidden']);
  exit;
}

if ($kind === LISTING_PAY_LAND) {
  echo json_encode([
    'listing_id' => $id,
    'payment_kind' => $kind,
    'payment_status' => (string)($row['land_payment_status'] ?? 'none'),
    'payment_amount_tzs' => (int)($row['land_payment_amount_tzs'] ?? 0),
    'snippe_status' => (string)($row['land_snippe_status'] ?? 'none'),
    'snippe_reference' => (string)($row['land_snippe_reference'] ?? ''),
    'snippe_last_error' => (string)($row['land_snippe_last_error'] ?? ''),
    'paid' => ($row['land_payment_status'] ?? '') === 'paid',
  ]);
  exit;
}

echo json_encode([
  'listing_id' => $id,
  'payment_kind' => $kind,
  'payment_status' => (string)($row['payment_status'] ?? 'pending'),
  'payment_amount_tzs' => (int)($row['payment_amount_tzs'] ?? 0),
  'snippe_status' => (string)($row['snippe_status'] ?? 'none'),
  'snippe_reference' => (string)($row['snippe_reference'] ?? ''),
  'snippe_last_error' => (string)($row['snippe_last_error'] ?? ''),
  'paid' => ($row['payment_status'] ?? '') === 'paid',
]);
