<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$listingId = (int)($_GET['listing_id'] ?? 0);
$status = (string)($_GET['status'] ?? '');
$kind = listing_pay_kind_normalize(isset($_GET['for']) ? (string)$_GET['for'] : null) ?? LISTING_PAY_LISTING_FEE;
$forQs = $kind === LISTING_PAY_LAND ? '&for=land' : '';

if ($listingId <= 0) {
  flash_set('err', 'Invalid payment return.');
  redirect('/index.php');
}

$st = db()->prepare('SELECT * FROM listings WHERE id = ?');
$st->execute([$listingId]);
$listing = $st->fetch();
if (!$listing) {
  flash_set('err', 'Listing not found.');
  redirect('/index.php');
}

if (listing_pay_access_error($listing, $u, $kind) !== null) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$payUrl = '/pay-listing.php?id=' . $listingId . $forQs;
$doneUrl = $kind === LISTING_PAY_LAND
  ? '/listing.php?id=' . $listingId
  : (((int)($listing['created_by_user_id'] ?? 0) === (int)$u['id'] || ($u['role'] ?? '') === 'admin')
    ? '/my-listings.php'
    : '/listing.php?id=' . $listingId);

if ($status === 'cancel') {
  flash_set('err', 'Card payment was cancelled. You can try again from the pay page.');
  redirect($payUrl);
}

if (snippe_enabled()) {
  snippe_sync_listing_payment($listingId, $kind);
  $st->execute([$listingId]);
  $listing = $st->fetch() ?: $listing;
}

$paid = $kind === LISTING_PAY_LAND
  ? (($listing['land_payment_status'] ?? '') === 'paid')
  : (($listing['payment_status'] ?? '') === 'paid');

if ($paid) {
  flash_set('ok', 'Payment received. Thank you!');
} else {
  flash_set('ok', 'If you completed card payment, confirmation may take a moment. Refresh shortly.');
}

redirect($doneUrl);
