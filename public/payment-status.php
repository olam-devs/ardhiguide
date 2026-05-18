<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

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

$snippeStatus = $kind === LISTING_PAY_LAND
  ? (string)($row['land_snippe_status'] ?? 'none')
  : (string)($row['snippe_status'] ?? 'none');
$paid = $kind === LISTING_PAY_LAND
  ? (($row['land_payment_status'] ?? '') === 'paid')
  : (($row['payment_status'] ?? '') === 'paid');

if (!$paid && snippe_enabled()) {
  if ($snippeStatus === 'pending') {
    snippe_sync_listing_payment($id, $kind);
    $st->execute([$id]);
    $row = $st->fetch() ?: $row;
    $snippeStatus = $kind === LISTING_PAY_LAND
      ? (string)($row['land_snippe_status'] ?? 'none')
      : (string)($row['snippe_status'] ?? 'none');
    $paid = $kind === LISTING_PAY_LAND
      ? (($row['land_payment_status'] ?? '') === 'paid')
      : (($row['payment_status'] ?? '') === 'paid');
  }
  if (in_array($snippeStatus, ['expired', 'failed'], true)) {
    snippe_normalize_for_retry($id, $kind);
    $st->execute([$id]);
    $row = $st->fetch() ?: $row;
    $snippeStatus = $kind === LISTING_PAY_LAND
      ? (string)($row['land_snippe_status'] ?? 'none')
      : (string)($row['snippe_status'] ?? 'none');
  }
}

if ($kind === LISTING_PAY_LAND) {
  echo json_encode([
    'listing_id' => $id,
    'payment_kind' => $kind,
    'payment_status' => (string)($row['land_payment_status'] ?? 'none'),
    'payment_amount_tzs' => (int)($row['land_payment_amount_tzs'] ?? 0),
    'snippe_status' => $snippeStatus,
    'snippe_reference' => (string)($row['land_snippe_reference'] ?? ''),
    'snippe_last_error' => (string)($row['land_snippe_last_error'] ?? ''),
    'paid' => $paid,
  ]);
  exit;
}

echo json_encode([
  'listing_id' => $id,
  'payment_kind' => $kind,
  'payment_status' => (string)($row['payment_status'] ?? 'pending'),
  'payment_amount_tzs' => (int)($row['payment_amount_tzs'] ?? 0),
  'snippe_status' => $snippeStatus,
  'snippe_reference' => (string)($row['snippe_reference'] ?? ''),
  'snippe_last_error' => (string)($row['snippe_last_error'] ?? ''),
  'paid' => $paid,
]);
