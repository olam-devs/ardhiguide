<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$listingId = (int)($_GET['listing_id'] ?? 0);
$status = (string)($_GET['status'] ?? '');

if ($listingId <= 0) {
  flash_set('err', 'Invalid payment return.');
  redirect('/my-listings.php');
}

$st = db()->prepare('SELECT id, created_by_user_id, payment_status, title FROM listings WHERE id = ?');
$st->execute([$listingId]);
$listing = $st->fetch();
if (!$listing) {
  flash_set('err', 'Listing not found.');
  redirect('/my-listings.php');
}

$ownerId = (int)($listing['created_by_user_id'] ?? 0);
if ($ownerId !== (int)$u['id'] && ($u['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

if ($status === 'cancel') {
  flash_set('err', 'Card payment was cancelled. You can try again from the pay page.');
  redirect('/pay-listing.php?id=' . $listingId);
}

if (($listing['payment_status'] ?? '') === 'paid') {
  flash_set('ok', 'Payment received. Thank you!');
} else {
  flash_set('ok', 'If you completed card payment, confirmation may take a moment. Refresh My listings shortly.');
}

redirect('/my-listings.php');
