<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('err', 'Invalid listing.');
  redirect('/my-listings.php');
}

$st = db()->prepare(
  'SELECT l.*, u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
   FROM listings l
   LEFT JOIN users u ON u.id = l.created_by_user_id
   WHERE l.id = ? LIMIT 1'
);
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
$pushEnabled = (int)($listing['payment_push_enabled'] ?? 0) === 1;
$assignedPhone = trim((string)($listing['payment_push_phone'] ?? ''));
$snippeStatus = (string)($listing['snippe_status'] ?? 'none');

if ($payStatus === 'paid' || $payStatus === 'waived' || $amount <= 0) {
  flash_set('ok', $payStatus === 'paid' ? 'This listing fee is recorded as paid.' : 'No payment required for this listing.');
  redirect($isAdmin ? '/admin/view-listing.php?id=' . $id : '/my-listings.php');
}

$payerSt = db()->prepare('SELECT id, email, full_name, phone FROM users WHERE id = ? LIMIT 1');
$payerSt->execute([(int)$u['id']]);
$payerUser = $payerSt->fetch() ?: $u;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && snippe_enabled()) {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'snippe_mobile') {
    $phoneInput = trim((string)($_POST['pay_phone'] ?? ''));
    $phone = listing_payment_push_phone($listing, $phoneInput);
    if ($phone === null) {
      flash_set('err', $pushEnabled
        ? 'Admin has not assigned a payment phone yet. Contact support.'
        : 'Enter the phone number that will receive the payment prompt.');
      redirect('/pay-listing.php?id=' . $id);
    }
    $res = snippe_create_mobile_payment($listing, $payerUser, $phone);
    if (!$res['ok']) {
      flash_set('err', (string)($res['err'] ?? 'Could not start mobile payment.'));
      redirect('/pay-listing.php?id=' . $id);
    }
    flash_set('ok', 'Check your phone now. Approve the payment prompt on your mobile money screen.');
    redirect('/pay-listing.php?id=' . $id . '&pending=1');
  }

  if ($action === 'snippe_card') {
    $phoneInput = trim((string)($_POST['pay_phone'] ?? ''));
    $phone = $phoneInput !== '' ? listing_payment_push_phone($listing, $phoneInput) : null;
    $res = snippe_create_card_payment($listing, $payerUser, $phone);
    if (!$res['ok']) {
      flash_set('err', (string)($res['err'] ?? 'Could not start card payment.'));
      redirect('/pay-listing.php?id=' . $id);
    }
    $payUrl = (string)($res['payment_url'] ?? '');
    if ($payUrl === '') {
      flash_set('err', 'Card checkout URL was not returned. Try again or use mobile money.');
      redirect('/pay-listing.php?id=' . $id);
    }
    header('Location: ' . $payUrl);
    exit;
  }
}

$title = (string)$listing['title'];
$showPending = isset($_GET['pending']) || $snippeStatus === 'pending';
$defaultPhone = $assignedPhone !== '' ? $assignedPhone : trim((string)($payerUser['phone'] ?? ''));
$waMsg = "Hello Ardhi Guide\n\nPayment receipt for listing #{$id}\nTitle: {$title}\nAmount: TSh " . number_format($amount, 0, '.', ',') . "\nReference: {$ref}\n\nI have completed the payment. Attached is the confirmation (SMS, screenshot, or bank slip). Please verify and approve. Asante sana.";

ob_start();
?>
  <div class="card pad reveal" style="max-width:720px;margin:0 auto">
    <div class="kicker">Listing fee</div>
    <h1>Pay to publish your listing</h1>
    <div class="sub">Amount set by our team. Pay online with mobile money (USSD prompt) or card, or send proof on WhatsApp.</div>

    <div class="card pad" style="margin-top:1rem;background:var(--bg2)">
      <div style="font-weight:900;font-size:1.35rem;color:var(--brand-900)"><?= h(format_tzs((string)$amount)) ?></div>
      <div class="sub" style="margin-top:.5rem">
        <strong><?= h($title) ?></strong><br>
        Package: <?= h((string)($listing['listing_package'] ?? 'basic')) ?><br>
        Payment code: <strong style="letter-spacing:1px"><?= h($ref) ?></strong>
      </div>
    </div>

    <?php if ($showPending && $snippeStatus === 'pending'): ?>
      <div class="card pad" id="snippe-wait" style="margin-top:1rem;border-color:rgba(14,92,74,.35);background:var(--brand-50)">
        <div class="kicker">Waiting for payment</div>
        <p class="sub" style="margin:.5rem 0 0">Approve the prompt on your phone. This page will update when payment is confirmed.</p>
        <p class="sub" id="snippe-wait-status" style="margin-top:.5rem;font-weight:700">Checking status…</p>
      </div>
    <?php endif; ?>

    <?php if (!empty($listing['snippe_last_error']) && $snippeStatus === 'failed'): ?>
      <div class="flash danger" style="margin-top:1rem"><?= h((string)$listing['snippe_last_error']) ?></div>
    <?php endif; ?>

    <?php if (snippe_enabled()): ?>
      <div class="card pad" style="margin-top:1rem">
        <div class="kicker">Pay online (Snippe)</div>
        <p class="sub" style="margin:.35rem 0 1rem">Secure payment via mobile money networks (Airtel, M-Pesa, Mixx, Halotel) or card.</p>

        <?php if ($pushEnabled && $assignedPhone !== ''): ?>
          <div class="card pad" style="background:var(--gold-50);margin-bottom:1rem;border-color:rgba(165,120,38,.25)">
            <p class="sub" style="margin:0"><strong>Payment prompt phone (assigned by admin):</strong><br><?= h($assignedPhone) ?></p>
          </div>
        <?php endif; ?>

        <form method="post" class="stack" id="snippe-pay-form">
          <?php if (!$pushEnabled || $assignedPhone === ''): ?>
            <div>
              <label>Phone for payment prompt</label>
              <input name="pay_phone" type="tel" required placeholder="0712 345 678" value="<?= h($defaultPhone) ?>" autocomplete="tel">
              <div class="sub" style="font-size:.85rem;margin-top:.3rem">You will receive a USSD / mobile money prompt on this number.</div>
            </div>
          <?php else: ?>
            <input type="hidden" name="pay_phone" value="<?= h($assignedPhone) ?>">
          <?php endif; ?>

          <div style="display:flex;gap:.65rem;flex-wrap:wrap;margin-top:.25rem">
            <button class="btn" type="submit" name="action" value="snippe_mobile">Pay with mobile money</button>
            <button class="btn secondary" type="submit" name="action" value="snippe_card">Pay with card</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="card pad" style="margin-top:1rem;background:var(--bg2)">
        <p class="sub" style="margin:0">Online card/mobile checkout is not enabled yet. Use the manual options below or contact admin.</p>
      </div>
    <?php endif; ?>

    <div class="card pad" style="margin-top:1rem">
      <div class="kicker">Manual payment</div>
      <p class="sub" style="margin:.5rem 0 0;line-height:1.75"><?= nl2br(h(MPESA_PAYMENT_HINT)) ?></p>
      <p class="sub" style="margin-top:.75rem">Include payment code <strong><?= h($ref) ?></strong> in your transaction reference.</p>
      <div style="margin-top:.8rem">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/payment-instructions.php">Open payment guide</a>
      </div>
    </div>

    <div class="receipt-cta" style="margin-top:1rem">
      <div class="kicker">Already paid manually?</div>
      <h3>Send your payment receipt on WhatsApp</h3>
      <p>Forward your confirmation (SMS, screenshot, or bank slip) so we can match code <strong><?= h($ref) ?></strong>.</p>
      <a class="btn" href="<?= h(whatsapp_link($waMsg)) ?>" target="_blank" rel="noopener">Send receipt on WhatsApp</a>
    </div>

    <div style="margin-top:1.2rem">
      <a class="btn secondary" href="<?= APP_BASE_URL ?>/my-listings.php">Back to my listings</a>
    </div>
  </div>

  <?php if ($showPending && snippe_enabled()): ?>
  <script>
  (function () {
    const statusEl = document.getElementById('snippe-wait-status');
    const listingId = <?= (int)$id ?>;
    let stopped = false;
    function poll() {
      if (stopped) return;
      fetch('<?= APP_BASE_URL ?>/payment-status.php?id=' + listingId, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
          if (!statusEl || !data) return;
          if (data.paid) {
            statusEl.textContent = 'Payment confirmed! Redirecting…';
            stopped = true;
            window.location.href = '<?= APP_BASE_URL ?>/my-listings.php';
            return;
          }
          if (data.snippe_status === 'failed') {
            statusEl.textContent = data.snippe_last_error || 'Payment failed. Try again.';
            stopped = true;
            return;
          }
          if (data.snippe_status === 'expired') {
            statusEl.textContent = 'Payment expired. Start a new payment.';
            stopped = true;
            return;
          }
          statusEl.textContent = 'Still waiting… check your phone.';
        })
        .catch(() => { if (statusEl) statusEl.textContent = 'Could not check status. Refresh the page.'; });
    }
    poll();
    setInterval(poll, 4000);
  })();
  </script>
  <?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'Pay listing fee. Ardhi Guide';
require __DIR__ . '/_layout.php';
