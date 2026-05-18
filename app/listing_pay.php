<?php

declare(strict_types=1);

const LISTING_PAY_LISTING_FEE = 'listing_fee';
const LISTING_PAY_LAND = 'land';

function listing_pay_kind_normalize(?string $for): ?string {
  $f = strtolower(trim((string)$for));
  if ($f === '' || $f === 'fee') {
    return LISTING_PAY_LISTING_FEE;
  }
  if (in_array($f, ['land', 'buyer', 'plot'], true)) {
    return LISTING_PAY_LAND;
  }
  return null;
}

/** @return string|null Error message if access denied. */
function listing_pay_access_error(array $listing, array $user, string $kind): ?string {
  $uid = (int)($user['id'] ?? 0);
  $role = (string)($user['role'] ?? '');
  $ownerId = (int)($listing['created_by_user_id'] ?? 0);
  $isAdmin = $role === 'admin';
  $isOwner = $ownerId === $uid;

  if ($kind === LISTING_PAY_LISTING_FEE) {
    if (!$isAdmin && !$isOwner) {
      return 'Only the listing owner can pay the publication fee.';
    }
    $ps = (string)($listing['payment_status'] ?? 'pending');
    if (in_array($ps, ['paid', 'waived'], true)) {
      return 'This listing fee is already settled.';
    }
    if ((int)($listing['payment_amount_tzs'] ?? 0) <= 0) {
      return 'No publication fee is required.';
    }
    return null;
  }

  if ($kind === LISTING_PAY_LAND) {
    if ((string)($listing['verification_status'] ?? '') !== 'approved') {
      return 'This plot is not available for payment yet.';
    }
    $ls = (string)($listing['land_payment_status'] ?? 'none');
    if ($ls === 'paid') {
      return 'Payment for this plot has already been received.';
    }
    if ($ls === 'waived') {
      return 'No payment is required for this plot.';
    }
    if ($ls !== 'pending') {
      return 'Payment is not open for this plot yet. Contact us if you need help.';
    }
    if ((int)($listing['land_payment_amount_tzs'] ?? 0) < snippe_min_amount_tzs()) {
      return 'Payment amount has not been set yet.';
    }
    $assigned = (int)($listing['land_payment_user_id'] ?? 0);
    if ($assigned > 0 && $assigned !== $uid && !$isAdmin) {
      return 'This payment is assigned to another account. Log in with the correct account or contact support.';
    }
    return null;
  }

  return 'Invalid payment type.';
}

/**
 * Resolve which payment flow applies. Prefers explicit ?for=, else land for non-owners on approved listings.
 *
 * @return array<string,mixed>|null Pay context or null
 */
function listing_pay_resolve(array $listing, array $user, ?string $requestedFor): ?array {
  $kind = listing_pay_kind_normalize($requestedFor);
  if ($kind === null && $requestedFor !== null && trim((string)$requestedFor) !== '') {
    return null;
  }

  $ownerId = (int)($listing['created_by_user_id'] ?? 0);
  $uid = (int)($user['id'] ?? 0);
  $isOwner = $ownerId === $uid;
  $role = (string)($user['role'] ?? '');
  $isAdmin = $role === 'admin';

  if ($kind === null) {
    $landOpen = (string)($listing['verification_status'] ?? '') === 'approved'
      && (string)($listing['land_payment_status'] ?? 'none') === 'pending'
      && (int)($listing['land_payment_amount_tzs'] ?? 0) >= snippe_min_amount_tzs();
    $feeOpen = (string)($listing['payment_status'] ?? 'pending') === 'pending'
      && (int)($listing['payment_amount_tzs'] ?? 0) > 0
      && ($isOwner || $isAdmin);

    if ($landOpen && (!$isOwner || $requestedFor === 'land')) {
      $kind = LISTING_PAY_LAND;
    } elseif ($feeOpen && ($isOwner || $isAdmin)) {
      $kind = LISTING_PAY_LISTING_FEE;
    } elseif ($landOpen) {
      $kind = LISTING_PAY_LAND;
    } else {
      return null;
    }
  }

  if (listing_pay_access_error($listing, $user, $kind) !== null) {
    return null;
  }

  if ($kind === LISTING_PAY_LAND) {
    return [
      'kind' => $kind,
      'kicker' => 'Plot payment',
      'title' => 'Pay for this land',
      'intro' => 'Amount set by our team. Pay securely online with mobile money or card. You must be logged in.',
      'amount' => (int)($listing['land_payment_amount_tzs'] ?? 0),
      'status' => (string)($listing['land_payment_status'] ?? 'none'),
      'reference' => (string)($listing['land_payment_reference'] ?? ''),
      'push_enabled' => (int)($listing['land_payment_push_enabled'] ?? 0) === 1,
      'push_phone' => trim((string)($listing['land_payment_push_phone'] ?? '')),
      'snippe_status' => (string)($listing['land_snippe_status'] ?? 'none'),
      'snippe_error' => (string)($listing['land_snippe_last_error'] ?? ''),
      'back_url' => APP_BASE_URL . '/listing.php?id=' . (int)$listing['id'],
      'done_url' => APP_BASE_URL . '/listing.php?id=' . (int)$listing['id'],
      'show_package' => false,
    ];
  }

  return [
    'kind' => $kind,
    'kicker' => 'Listing fee',
    'title' => 'Pay to publish your listing',
    'intro' => 'Publication fee for your listing. Pay online with mobile money or card.',
    'amount' => (int)($listing['payment_amount_tzs'] ?? 0),
    'status' => (string)($listing['payment_status'] ?? 'pending'),
    'reference' => (string)($listing['payment_reference'] ?? ''),
    'push_enabled' => (int)($listing['payment_push_enabled'] ?? 0) === 1,
    'push_phone' => trim((string)($listing['payment_push_phone'] ?? '')),
    'snippe_status' => (string)($listing['snippe_status'] ?? 'none'),
    'snippe_error' => (string)($listing['snippe_last_error'] ?? ''),
    'back_url' => $isAdmin
      ? APP_BASE_URL . '/admin/view-listing.php?id=' . (int)$listing['id']
      : APP_BASE_URL . '/my-listings.php',
    'done_url' => $isAdmin
      ? APP_BASE_URL . '/admin/view-listing.php?id=' . (int)$listing['id']
      : APP_BASE_URL . '/my-listings.php',
    'show_package' => true,
  ];
}

function listing_pay_push_phone(array $listing, ?string $userInput, string $kind): ?string {
  if ($kind === LISTING_PAY_LAND) {
    if ((int)($listing['land_payment_push_enabled'] ?? 0) === 1) {
      $assigned = trim((string)($listing['land_payment_push_phone'] ?? ''));
      if ($assigned !== '') {
        return normalize_phone($assigned) ?: null;
      }
    }
  } else {
    return listing_payment_push_phone($listing, $userInput);
  }
  if ($userInput !== null && trim($userInput) !== '') {
    $n = normalize_phone($userInput);
    return $n !== '' ? $n : null;
  }
  return null;
}

function listing_land_payment_reference(int $listingId): string {
  return 'AGL-' . $listingId . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/** Admin: configure buyer plot payment on an approved listing. */
function listing_admin_save_land_payment_settings(
  int $listingId,
  int $amountTzs,
  ?int $userId,
  ?string $pushPhone,
  bool $pushEnabled,
  bool $openPayment
): ?string {
  if ($amountTzs > 0 && $amountTzs < snippe_min_amount_tzs()) {
    return 'Amount must be at least ' . snippe_min_amount_tzs() . ' TZS, or set to 0 to close payment.';
  }

  $phoneNorm = null;
  $clearPhone = false;
  if ($pushPhone !== null) {
    if (trim($pushPhone) === '') {
      $clearPhone = true;
    } else {
      $phoneNorm = normalize_phone($pushPhone);
      if ($phoneNorm === '') {
        return 'Invalid phone number for the payment prompt.';
      }
    }
  }

  if ($pushEnabled && $phoneNorm === null && !$clearPhone) {
    $cur = db()->prepare('SELECT land_payment_push_phone FROM listings WHERE id = ?');
    $cur->execute([$listingId]);
    $row = $cur->fetch();
    if (trim((string)($row['land_payment_push_phone'] ?? '')) === '') {
      return 'Assign a phone before locking the payment prompt, or leave push unlocked.';
    }
  }

  $st = db()->prepare(
    'SELECT land_payment_reference, land_payment_status FROM listings WHERE id = ?'
  );
  $st->execute([$listingId]);
  $row = $st->fetch();
  if (!$row) {
    return 'Listing not found.';
  }

  $ref = (string)($row['land_payment_reference'] ?? '');
  if ($ref === '' && $openPayment && $amountTzs > 0) {
    $ref = listing_land_payment_reference($listingId);
  }

  $status = 'none';
  if ($amountTzs <= 0) {
    $status = 'none';
    $ref = null;
  } elseif ($openPayment) {
    $status = 'pending';
  } else {
    $curStatus = (string)($row['land_payment_status'] ?? 'none');
    $status = $curStatus === 'paid' ? 'paid' : 'none';
  }

  $assignUser = ($userId !== null && $userId > 0) ? $userId : null;

  $sql = 'UPDATE listings SET land_payment_amount_tzs = ?, land_payment_status = ?, land_payment_user_id = ?,
          land_payment_push_enabled = ?';
  $params = [$amountTzs, $status, $assignUser, $pushEnabled ? 1 : 0];

  if ($pushPhone !== null) {
    $sql .= ', land_payment_push_phone = ?';
    $params[] = $clearPhone ? null : $phoneNorm;
  }
  if ($ref !== null) {
    $sql .= ', land_payment_reference = ?';
    $params[] = $ref;
  } elseif ($amountTzs <= 0) {
    $sql .= ', land_payment_reference = NULL';
  }

  $sql .= ' WHERE id = ?';
  $params[] = $listingId;
  db()->prepare($sql)->execute($params);
  return null;
}

function listing_land_mark_paid(int $listingId): void {
  db()->prepare(
    "UPDATE listings SET land_payment_status = 'paid', land_paid_at = COALESCE(land_paid_at, NOW()),
     land_snippe_status = 'completed', land_snippe_last_error = NULL WHERE id = ?"
  )->execute([$listingId]);
}

function listing_land_mark_waived(int $listingId): void {
  db()->prepare(
    "UPDATE listings SET land_payment_status = 'waived', land_paid_at = NULL,
     land_snippe_status = 'none', land_snippe_last_error = NULL WHERE id = ?"
  )->execute([$listingId]);
}

/** Whether public listing page should show Pay button. */
function listing_land_payment_open(array $listing): bool {
  return (string)($listing['verification_status'] ?? '') === 'approved'
    && (string)($listing['land_payment_status'] ?? 'none') === 'pending'
    && (int)($listing['land_payment_amount_tzs'] ?? 0) >= snippe_min_amount_tzs();
}

function user_owns_listings(int $userId): bool {
  $st = db()->prepare('SELECT 1 FROM listings WHERE created_by_user_id = ? LIMIT 1');
  $st->execute([$userId]);
  return (bool)$st->fetch();
}

function user_can_manage_listings(array $user): bool {
  $role = (string)($user['role'] ?? 'buyer');
  if (in_array($role, ['seller', 'agent', 'admin'], true)) {
    return true;
  }
  return user_owns_listings((int)$user['id']);
}

/** Listings this user created that still need the publication fee. */
function user_pending_publication_fees(int $userId): array {
  $st = db()->prepare(
    "SELECT id, title, payment_amount_tzs, payment_reference
     FROM listings
     WHERE created_by_user_id = ? AND payment_status = 'pending' AND payment_amount_tzs > 0
     ORDER BY id DESC"
  );
  $st->execute([$userId]);
  return $st->fetchAll();
}

/** Approved plots this buyer can pay for (admin opened payment). */
function user_open_land_payments(int $userId): array {
  $min = snippe_min_amount_tzs();
  $st = db()->prepare(
    "SELECT id, title, land_payment_amount_tzs, land_payment_reference, land_payment_user_id
     FROM listings
     WHERE verification_status = 'approved'
       AND land_payment_status = 'pending'
       AND land_payment_amount_tzs >= ?
       AND (land_payment_user_id IS NULL OR land_payment_user_id = ?)
     ORDER BY id DESC
     LIMIT 30"
  );
  $st->execute([$min, $userId]);
  return $st->fetchAll();
}
