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
  $stmt = db()->prepare("UPDATE listings SET payment_status = 'waived', paid_at = NULL WHERE id = ?");
  $stmt->execute([$listingId]);
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
