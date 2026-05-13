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
$waMsg = "Hello Ardhi Guide\n\nPayment for listing #{$id}\nTitle: {$title}\nAmount: TSh " . number_format($amount, 0, '.', ',') . "\nReference: {$ref}\n\nI have completed M-Pesa payment (or attach screenshot). Please confirm. Asante.";

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

    <div style="margin-top:1.2rem;display:flex;gap:.7rem;flex-wrap:wrap">
      <a class="btn" href="<?= h(whatsapp_link($waMsg)) ?>" target="_blank" rel="noopener">Send proof on WhatsApp</a>
      <a class="btn secondary" href="<?= APP_BASE_URL ?>/my-listings.php">Back to my listings</a>
    </div>
  </div>
<?php
$content = ob_get_clean();
$title = 'Pay listing fee. Ardhi Guide';
require __DIR__ . '/_layout.php';
