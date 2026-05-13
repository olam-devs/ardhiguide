<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('err', 'Invalid listing.');
  redirect('/my-listings.php');
}

$st = db()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
$st->execute([$id]);
$listing = $st->fetch();
if (!$listing) {
  flash_set('err', 'Listing not found.');
  redirect('/my-listings.php');
}

$ownerId = (int)($listing['created_by_user_id'] ?? 0);
$isAdmin = (($u['role'] ?? '') === 'admin');
$isOwner = ($ownerId === (int)$u['id']);
if (!$isAdmin && !$isOwner) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$payStatus = (string)($listing['payment_status'] ?? 'pending');
$amount = (int)($listing['payment_amount_tzs'] ?? 0);
$ref = (string)($listing['payment_reference'] ?? '');

if ($payStatus === 'paid' || $payStatus === 'waived' || $amount <= 0) {
  flash_set('ok', $payStatus === 'paid' ? 'This listing fee is recorded as paid.' : 'No payment required for this listing.');
  redirect(($u['role'] ?? '') === 'admin' ? '/admin/view-listing.php?id=' . $id : '/my-listings.php');
}

$title = (string)$listing['title'];
$waMsg = "Hello Ardhi Guide\n\nPayment receipt for listing #{$id}\nTitle: {$title}\nAmount: TSh " . number_format($amount, 0, '.', ',') . "\nReference: {$ref}\n\nI have completed the M-Pesa payment. Attached is the confirmation SMS / screenshot. Please verify and approve. Asante sana.";

ob_start();
?>
  <div class="card pad reveal" style="max-width:640px;margin:0 auto">
    <div class="kicker">Listing fee</div>
    <h1>Pay to publish your listing</h1>
    <div class="sub">After payment, our team can review and approve. You can send proof via WhatsApp using the button below.</div>

    <div class="card pad" style="margin-top:1rem;background:var(--bg2)">
      <div style="font-weight:900;font-size:1.1rem"><?= h($title) ?></div>
      <div class="sub" style="margin-top:.5rem">
        Package: <strong><?= h((string)($listing['listing_package'] ?? 'basic')) ?></strong><br>
        Amount: <strong><?= h(format_tzs((string)$amount)) ?></strong><br>
        Payment code: <strong style="letter-spacing:1px"><?= h($ref) ?></strong>
      </div>
    </div>

    <div class="card pad" style="margin-top:1rem">
      <div class="kicker">M-Pesa</div>
      <p class="sub" style="margin:.5rem 0 0;line-height:1.75"><?= nl2br(h(MPESA_PAYMENT_HINT)) ?></p>
      <p class="sub" style="margin-top:.75rem">Always include the <strong>payment code</strong> in the transaction description so we can match your payment.</p>
    </div>

    <div class="card pad" style="margin-top:1rem;border-color:rgba(165,120,38,.25);background:var(--gold-50)">
      <div class="kicker" style="color:var(--gold-700)">Need full step-by-step?</div>
      <h3 style="margin:.3rem 0 .45rem;font-size:1.1rem">See the published payment guide</h3>
      <p class="sub" style="margin:0">Our admin team publishes complete payment categories (M-Pesa, bank, and more) with numbered steps. Read them on the public payment guide page.</p>
      <div style="margin-top:.8rem">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/payment-instructions.php">Open payment guide</a>
      </div>
    </div>

    <div class="receipt-cta">
      <div class="kicker">Important last step</div>
      <h3>Send your M-Pesa receipt on WhatsApp</h3>
      <p>
        After completing the payment, forward the M-Pesa confirmation SMS (or a screenshot) to our admin on WhatsApp.
        Your listing will only be approved once the receipt is received and matched to payment code
        <strong><?= h($ref) ?></strong>.
      </p>
      <a class="btn" href="<?= h(whatsapp_link($waMsg)) ?>" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="margin-right:.25rem"><path d="M.057 24l1.687-6.163A11.867 11.867 0 0 1 .096 11.86C.099 5.334 5.43.003 11.954.003a11.815 11.815 0 0 1 8.413 3.488 11.821 11.821 0 0 1 3.48 8.414c-.003 6.526-5.335 11.857-11.86 11.857a11.9 11.9 0 0 1-5.674-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
        Send receipt on WhatsApp
      </a>
    </div>

    <div style="margin-top:1.2rem;display:flex;gap:.7rem;flex-wrap:wrap">
      <a class="btn secondary" href="<?= APP_BASE_URL ?>/my-listings.php">Back to my listings</a>
    </div>
  </div>
<?php
$content = ob_get_clean();
$title = 'Pay listing fee. Ardhi Guide';
require __DIR__ . '/_layout.php';
