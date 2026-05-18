<?php

declare(strict_types=1);

function listing_package_normalize(string $p): string {
  return in_array($p, ['basic', 'featured', 'premium'], true) ? $p : 'basic';
}

function listing_package_amount_tzs(string $package): int {
  return match (listing_package_normalize($package)) {
    'featured' => 30_000,
    'premium' => 100_000,
    default => 5_000,
  };
}

function listing_payment_reference(int $listingId): string {
  return 'AG-' . $listingId . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/** After INSERT: set package, amount, reference, and payment status (waived for admin-created listings). */
function listing_apply_payment_row(int $listingId, string $package, bool $createdByAdmin): void {
  $pkg = listing_package_normalize($package);
  $amt = listing_package_amount_tzs($pkg);
  $ref = listing_payment_reference($listingId);
  $status = $createdByAdmin ? 'waived' : 'pending';
  $stmt = db()->prepare(
    'UPDATE listings SET listing_package = ?, payment_amount_tzs = ?, payment_status = ?, payment_reference = ? WHERE id = ?'
  );
  $stmt->execute([$pkg, $amt, $status, $ref, $listingId]);
}

function listing_mark_paid(int $listingId): void {
  $stmt = db()->prepare("UPDATE listings SET payment_status = 'paid', paid_at = COALESCE(paid_at, NOW()) WHERE id = ?");
  $stmt->execute([$listingId]);
}

function listing_mark_waived(int $listingId): void {
  $stmt = db()->prepare("UPDATE listings SET payment_status = 'waived', paid_at = NULL, snippe_status = 'none' WHERE id = ?");
  $stmt->execute([$listingId]);
}

/**
 * Admin: set fee amount, optional push phone, and whether Snippe USSD push is enabled.
 * Pass null for $pushPhone to leave unchanged; empty string to clear assignment.
 */
function listing_admin_save_payment_settings(
  int $listingId,
  int $amountTzs,
  ?string $pushPhone,
  bool $pushEnabled
): ?string {
  if ($amountTzs > 0 && $amountTzs < snippe_min_amount_tzs()) {
    return 'Amount must be at least ' . snippe_min_amount_tzs() . ' TZS, or set to 0 and waive the fee.';
  }

  $phoneNorm = null;
  $clearPhone = false;
  if ($pushPhone !== null) {
    if (trim($pushPhone) === '') {
      $clearPhone = true;
    } else {
      $phoneNorm = normalize_phone($pushPhone);
      if ($phoneNorm === '') {
        return 'Invalid phone number for payment prompt.';
      }
    }
  }

  if ($pushEnabled && $phoneNorm === null && !$clearPhone) {
    $cur = db()->prepare('SELECT payment_push_phone FROM listings WHERE id = ?');
    $cur->execute([$listingId]);
    $row = $cur->fetch();
    $existing = trim((string)($row['payment_push_phone'] ?? ''));
    if ($existing === '') {
      return 'Assign a phone number before enabling the payment prompt, or leave push disabled.';
    }
  }

  $sql = 'UPDATE listings SET payment_amount_tzs = ?, payment_push_enabled = ?';
  $params = [$amountTzs, $pushEnabled ? 1 : 0];

  if ($pushPhone !== null) {
    $sql .= ', payment_push_phone = ?';
    $params[] = $clearPhone ? null : $phoneNorm;
  }

  $sql .= ' WHERE id = ?';
  $params[] = $listingId;
  db()->prepare($sql)->execute($params);
  return null;
}

function notify_admin_new_listing(int $listingId, string $title): void {
  $to = (string)cfg('ADMIN_NOTIFY_EMAIL', '');
  if ($to === '') {
    return;
  }
  $from = (string)cfg('MAIL_FROM', 'noreply@ardhiguide.local');
  $subject = APP_NAME . ': new listing #' . $listingId;
  $url = APP_BASE_URL . '/admin/view-listing.php?id=' . $listingId;
  $body = "A new listing was submitted.\r\n\r\nTitle: {$title}\r\nReview: {$url}\r\n";
  @mail($to, $subject, $body, 'From: ' . $from . "\r\nContent-Type: text/plain; charset=UTF-8");
}
