<?php
/** Payment POST actions for admin listing pages. Sets flash and redirects when matched. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  return;
}
$listingId = (int)($listingId ?? 0);
$redirectBase = (string)($paymentRedirectBase ?? '/admin/view-listing.php?id=' . $listingId);
$action = (string)($_POST['action'] ?? '');

if ($action === 'save_payment_settings') {
  $amount = (int)preg_replace('/\D+/', '', (string)($_POST['payment_amount_tzs'] ?? '0'));
  $pushPhoneRaw = isset($_POST['payment_push_phone']) ? (string)$_POST['payment_push_phone'] : null;
  $unassignPhone = isset($_POST['unassign_phone']);
  if ($unassignPhone) {
    $pushPhoneRaw = '';
  }
  $pushEnabled = isset($_POST['payment_push_enabled']) && !$unassignPhone;
  if ($unassignPhone) {
    $pushEnabled = false;
  }
  $err = listing_admin_save_payment_settings($listingId, $amount, $pushPhoneRaw, $pushEnabled);
  if ($err !== null) {
    flash_set('err', $err);
  } else {
    flash_set('ok', $unassignPhone
      ? 'Payment settings saved. Push phone unassigned.'
      : 'Payment settings saved.');
  }
  redirect($redirectBase);
}

if ($action === 'save_land_payment_settings') {
  $amount = (int)preg_replace('/\D+/', '', (string)($_POST['land_payment_amount_tzs'] ?? '0'));
  $userId = (int)($_POST['land_payment_user_id'] ?? 0);
  $userId = $userId > 0 ? $userId : null;
  $pushPhoneRaw = isset($_POST['land_payment_push_phone']) ? (string)$_POST['land_payment_push_phone'] : null;
  $pushEnabled = isset($_POST['land_payment_push_enabled']);
  $openPayment = isset($_POST['land_payment_open']);
  $err = listing_admin_save_land_payment_settings(
    $listingId,
    $amount,
    $userId,
    $pushPhoneRaw,
    $pushEnabled,
    $openPayment
  );
  if ($err !== null) {
    flash_set('err', $err);
  } else {
    flash_set('ok', 'Buyer payment settings saved.');
  }
  redirect($redirectBase);
}

if ($action === 'mark_land_paid') {
  listing_land_mark_paid($listingId);
  flash_set('ok', 'Buyer plot payment marked as received.');
  redirect($redirectBase);
}

if ($action === 'mark_land_waived') {
  listing_land_mark_waived($listingId);
  flash_set('ok', 'Buyer payment cleared.');
  redirect($redirectBase);
}
