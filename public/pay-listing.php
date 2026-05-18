<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$for = isset($_GET['for']) ? (string)$_GET['for'] : null;
if ($id <= 0) {
  flash_set('err', 'Invalid listing.');
  redirect('/index.php');
}

$payPath = '/pay-listing.php?id=' . $id . ($for !== null && $for !== '' ? '&for=' . rawurlencode($for) : '');

session_start_safe();
if (!current_user()) {
  $_SESSION['login_redirect'] = $payPath;
  flash_set('ok', 'Please log in to complete payment.');
  redirect('/login.php');
}

$u = require_auth();

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
  redirect('/index.php');
}

$ctx = listing_pay_resolve($listing, $u, $for);
if ($ctx === null) {
  $tryKind = listing_pay_kind_normalize($for) ?? LISTING_PAY_LAND;
  $err = listing_pay_access_error($listing, $u, $tryKind)
    ?? 'Payment is not available for this listing.';
  flash_set('err', $err);
  $ownerId = (int)($listing['created_by_user_id'] ?? 0);
  if ($ownerId === (int)$u['id']) {
    redirect('/my-listings.php');
  }
  redirect('/listing.php?id=' . $id);
}

$kind = (string)$ctx['kind'];
$amount = (int)$ctx['amount'];
$ref = (string)$ctx['reference'];
$pushEnabled = (bool)$ctx['push_enabled'];
$assignedPhone = (string)$ctx['push_phone'];
$snippeStatus = (string)$ctx['snippe_status'];
$forQs = $kind === LISTING_PAY_LAND ? '&for=land' : '';

$payerSt = db()->prepare('SELECT id, email, full_name, phone FROM users WHERE id = ? LIMIT 1');
$payerSt->execute([(int)$u['id']]);
$payerUser = $payerSt->fetch() ?: $u;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && snippe_enabled()) {
  $action = trim((string)($_POST['action'] ?? ''));
  $postKind = listing_pay_kind_normalize((string)($_POST['pay_kind'] ?? $kind)) ?? $kind;

  if ($action === '' && ($_POST['pay_phone'] ?? null) !== null) {
    flash_set('err', 'Could not start payment. Please try again.');
    redirect('/pay-listing.php?id=' . $id . $forQs);
  }

  if (in_array($action, ['snippe_mobile', 'snippe_card'], true)) {
    $postBlock = snippe_prepare_new_payment($id, $postKind);
    if ($postBlock !== null) {
      flash_set('err', $postBlock);
      $fresh = snippe_reload_listing($id);
      $stillPending = $fresh && snippe_listing_snippe_status($fresh, $postKind) === 'pending';
      redirect('/pay-listing.php?id=' . $id . $forQs . ($stillPending ? '&pending=1' : ''));
    }
    $listing = snippe_reload_listing($id) ?: $listing;
  }

  if ($action === 'snippe_mobile') {
    $phoneInput = trim((string)($_POST['pay_phone'] ?? ''));
    $phone = listing_pay_push_phone($listing, $phoneInput, $postKind);
    if ($phone === null) {
      flash_set('err', $pushEnabled
        ? 'Admin has not assigned a payment phone yet. Contact support.'
        : 'Enter the phone number that will receive the payment prompt.');
      redirect('/pay-listing.php?id=' . $id . $forQs);
    }
    $res = snippe_create_mobile_payment($listing, $payerUser, $phone, $postKind);
    if (!$res['ok']) {
      flash_set('err', (string)($res['err'] ?? 'Could not start mobile payment.'));
      redirect('/pay-listing.php?id=' . $id . $forQs);
    }
    $promptPhone = (string)($res['push_phone'] ?? $phone);
    flash_set(
      'ok',
      'USSD prompt sent to ' . snippe_format_phone_display($promptPhone)
      . '. Unlock that phone and approve the M-Pesa / Airtel / Mixx request. It can take up to 30 seconds.'
    );
    redirect('/pay-listing.php?id=' . $id . $forQs . '&pending=1');
  }

  if ($action === 'snippe_resend_push') {
    $postKind = listing_pay_kind_normalize((string)($_POST['pay_kind'] ?? $kind)) ?? $kind;
    $listing = snippe_reload_listing($id) ?: $listing;
    $phoneInput = trim((string)($_POST['pay_phone'] ?? ''));
    $phone = listing_pay_push_phone($listing, $phoneInput, $postKind);
    if ($phone === null) {
      flash_set('err', 'Enter the phone number that should receive the prompt.');
      redirect('/pay-listing.php?id=' . $id . $forQs . '&pending=1');
    }
    $snippeRef = $postKind === LISTING_PAY_LAND
      ? (string)($listing['land_snippe_reference'] ?? '')
      : (string)($listing['snippe_reference'] ?? '');
    if ($snippeRef === '') {
      flash_set('err', 'No payment in progress. Start mobile payment again.');
      redirect('/pay-listing.php?id=' . $id . $forQs);
    }
    $pushRes = snippe_send_mobile_ussd_push($snippeRef, $phone);
    if (!$pushRes['ok']) {
      flash_set('err', (string)($pushRes['err'] ?? 'Could not resend prompt.'));
    } else {
      flash_set('ok', 'Prompt resent to ' . snippe_format_phone_display($phone) . '. Check that phone now.');
    }
    redirect('/pay-listing.php?id=' . $id . $forQs . '&pending=1');
  }

  if ($action === 'snippe_abandon') {
    $postKind = listing_pay_kind_normalize((string)($_POST['pay_kind'] ?? $kind)) ?? $kind;
    $msg = snippe_abandon_pending_payment($id, $postKind);
    if ($msg !== null) {
      flash_set(str_contains($msg, 'confirmed') ? 'ok' : 'err', $msg);
    } else {
      flash_set('ok', 'You can start a new payment now.');
    }
    redirect('/pay-listing.php?id=' . $id . $forQs);
  }

  if ($action === 'snippe_card') {
    $phoneInput = trim((string)($_POST['pay_phone'] ?? ''));
    $phone = $phoneInput !== '' ? listing_pay_push_phone($listing, $phoneInput, $postKind) : null;
    $res = snippe_create_card_payment($listing, $payerUser, $phone, $postKind);
    if (!$res['ok']) {
      flash_set('err', (string)($res['err'] ?? 'Could not start card payment.'));
      redirect('/pay-listing.php?id=' . $id . $forQs);
    }
    $payUrl = (string)($res['payment_url'] ?? '');
    if ($payUrl === '') {
      flash_set('err', 'Card checkout URL was not returned. Try again or use mobile money.');
      redirect('/pay-listing.php?id=' . $id . $forQs);
    }
    header('Location: ' . $payUrl);
    exit;
  }
}

$title = (string)$listing['title'];
$retryNotice = '';
if (snippe_enabled()) {
  if ($snippeStatus === 'pending') {
    snippe_sync_listing_payment($id, $kind);
    $st->execute([$id]);
    $listing = $st->fetch() ?: $listing;
    $ctx = listing_pay_resolve($listing, $u, $for);
    if ($ctx !== null) {
      $snippeStatus = (string)$ctx['snippe_status'];
    }
  }
  $alreadyPaid = ($kind === LISTING_PAY_LAND
    ? (($listing['land_payment_status'] ?? '') === 'paid')
    : (($listing['payment_status'] ?? '') === 'paid'));
  if (in_array($snippeStatus, ['expired', 'failed', 'completed'], true) && !$alreadyPaid) {
    $retryNotice = $snippeStatus === 'expired'
      ? 'Your previous payment attempt expired. You can pay again below.'
      : 'Your previous payment did not complete. You can try again below.';
    snippe_normalize_for_retry($id, $kind);
    $st->execute([$id]);
    $listing = $st->fetch() ?: $listing;
    $ctx = listing_pay_resolve($listing, $u, $for);
    if ($ctx !== null) {
      $snippeStatus = (string)$ctx['snippe_status'];
      $snippeErr = (string)$ctx['snippe_error'];
    }
  }
}
$showPending = $snippeStatus === 'pending';
$justPaid = ($kind === LISTING_PAY_LAND
  ? (($listing['land_payment_status'] ?? '') === 'paid')
  : (($listing['payment_status'] ?? '') === 'paid'));
if ($justPaid) {
  flash_set('ok', 'Payment received. Thank you!');
  redirect((string)$ctx['done_url']);
}
if (!$showPending && isset($_GET['pending'])) {
  redirect('/pay-listing.php?id=' . $id . $forQs);
}

$listing = snippe_reload_listing($id) ?: $listing;
$ctx = listing_pay_resolve($listing, $u, $for);
if ($ctx === null) {
  flash_set('err', 'Payment is not available for this listing.');
  redirect('/listing.php?id=' . $id);
}
$kind = (string)$ctx['kind'];
$amount = (int)$ctx['amount'];
$ref = (string)$ctx['reference'];
$pushEnabled = (bool)$ctx['push_enabled'];
$assignedPhone = (string)$ctx['push_phone'];
$snippeStatus = (string)$ctx['snippe_status'];
$snippeErr = (string)$ctx['snippe_error'];
$showPending = $snippeStatus === 'pending';

$defaultPhone = $assignedPhone !== '' ? $assignedPhone : trim((string)($payerUser['phone'] ?? ''));
$landAmount = (int)($listing['land_payment_amount_tzs'] ?? 0);
$feeAmount = (int)($listing['payment_amount_tzs'] ?? 0);

ob_start();
?>
  <div class="card pad reveal" style="max-width:720px;margin:0 auto">
    <div class="kicker"><?= h((string)$ctx['kicker']) ?></div>
    <h1><?= h((string)$ctx['title']) ?></h1>
    <div class="sub"><?= h((string)$ctx['intro']) ?></div>

    <div class="card pad" style="margin-top:1rem;background:var(--bg2)">
      <div class="sub" style="margin:0 0 .35rem;font-size:.88rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700">
        <?= $kind === LISTING_PAY_LAND ? 'Plot payment (buyer)' : 'Publication fee (seller)' ?>
      </div>
      <div style="font-weight:900;font-size:1.35rem;color:var(--brand-900)"><?= h(format_tzs((string)$amount)) ?></div>
      <div class="sub" style="margin-top:.5rem">
        <strong><?= h($title) ?></strong><br>
        <?php if ($kind === LISTING_PAY_LAND && $feeAmount > 0 && (int)($listing['created_by_user_id'] ?? 0) === (int)$u['id']): ?>
          <span style="font-size:.88rem">Your seller publication fee (<?= h(format_tzs((string)$feeAmount)) ?>) is separate — <a href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= $id ?>">pay listing fee</a>.</span><br>
        <?php endif; ?>
        <?php if ($kind !== LISTING_PAY_LAND && $landAmount >= snippe_min_amount_tzs() && listing_land_payment_open($listing)): ?>
          <span style="font-size:.88rem">Buyer plot payment is <?= h(format_tzs((string)$landAmount)) ?> — <a href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= $id ?>&for=land">pay for plot</a>.</span><br>
        <?php endif; ?>
        <?php if (!empty($ctx['show_package'])): ?>
          Package: <?= h((string)($listing['listing_package'] ?? 'basic')) ?><br>
        <?php endif; ?>
        Payment code: <strong style="letter-spacing:1px"><?= h($ref) ?></strong>
      </div>
    </div>

    <?php if ($showPending && $snippeStatus === 'pending'): ?>
      <div class="card pad" id="snippe-wait" style="margin-top:1rem;border-color:rgba(14,92,74,.35);background:var(--brand-50)">
        <div class="kicker">Waiting for payment</div>
        <?php
          $promptPhoneWait = $assignedPhone !== '' ? $assignedPhone : $defaultPhone;
          $snippeRefWait = $kind === LISTING_PAY_LAND
            ? (string)($listing['land_snippe_reference'] ?? '')
            : (string)($listing['snippe_reference'] ?? '');
        ?>
        <p class="sub" style="margin:.5rem 0 0">
          Approve the mobile money request on <strong><?= h(snippe_format_phone_display($promptPhoneWait)) ?></strong>.
          Use the phone that has this number active (M-Pesa, Airtel Money, Mixx, or Halotel).
        </p>
        <p class="sub" id="snippe-wait-status" style="margin-top:.5rem;font-weight:700">Checking status…</p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem;align-items:flex-start">
          <button type="button" class="btn secondary" id="snippe-check-now">I entered my PIN — check again</button>
          <?php if ($snippeRefWait !== ''): ?>
            <form method="post" style="margin:0">
              <input type="hidden" name="action" value="snippe_resend_push">
              <input type="hidden" name="pay_kind" value="<?= h($kind) ?>">
              <input type="hidden" name="pay_phone" value="<?= h($promptPhoneWait) ?>">
              <button class="btn secondary" type="submit">Resend prompt</button>
            </form>
          <?php endif; ?>
          <form method="post" style="margin:0" onsubmit="return confirm('Clear this attempt and start a new payment? Only do this if you did NOT complete payment on your phone.');">
            <input type="hidden" name="pay_kind" value="<?= h($kind) ?>">
            <button class="btn ghost" type="submit" name="action" value="snippe_abandon">Start over</button>
          </form>
        </div>
        <p class="sub" style="margin:.65rem 0 0;font-size:.85rem">No prompt after 30 seconds? Confirm the number above, then tap <strong>Resend prompt</strong>. Wrong number? Use <strong>Start over</strong>.</p>
      </div>
    <?php endif; ?>

    <?php if ($retryNotice !== ''): ?>
      <div class="flash ok" style="margin-top:1rem"><?= h($retryNotice) ?></div>
    <?php endif; ?>

    <?php if ($snippeErr !== '' && $snippeStatus === 'failed'): ?>
      <div class="flash danger" style="margin-top:1rem"><?= h($snippeErr) ?></div>
    <?php endif; ?>

    <?php if (snippe_enabled() && !($showPending && $snippeStatus === 'pending')): ?>
      <div class="card pad" style="margin-top:1rem">
        <div class="kicker">Pay online</div>
        <p class="sub" style="margin:.35rem 0 1rem">Secure checkout with mobile money (Airtel, M-Pesa, Mixx, Halotel) or card. Enter your PIN only on the USSD prompt on your phone.</p>

        <?php if ($pushEnabled && $assignedPhone !== ''): ?>
          <div class="card pad" style="background:var(--gold-50);margin-bottom:1rem;border-color:rgba(165,120,38,.25)">
            <p class="sub" style="margin:0"><strong>Payment prompt phone (assigned by admin):</strong><br><?= h($assignedPhone) ?></p>
          </div>
        <?php endif; ?>

        <form method="post" class="stack" id="snippe-pay-form" data-pay-lock="1">
          <input type="hidden" name="action" id="snippe-pay-action" value="">
          <input type="hidden" name="pay_kind" value="<?= h($kind) ?>">
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
            <button class="btn" type="submit" name="action" value="snippe_mobile" data-pay-btn>Pay with mobile money</button>
            <button class="btn secondary" type="submit" name="action" value="snippe_card" data-pay-btn>Pay with card</button>
          </div>
          <p class="sub" id="snippe-pay-lock-msg" style="display:none;margin:.5rem 0 0;font-size:.88rem">Starting payment… please wait. Do not tap again.</p>
        </form>
      </div>
    <?php else: ?>
      <div class="card pad" style="margin-top:1rem;background:var(--bg2)">
        <p class="sub" style="margin:0">Online checkout is not available right now. Please try again later or <a href="<?= h(whatsapp_link('Hello Ardhi Guide, I need help with an online payment.')) ?>" target="_blank" rel="noopener">contact us</a>.</p>
      </div>
    <?php endif; ?>

    <div class="card pad" style="margin-top:1rem;background:var(--bg2)">
      <div class="kicker">How payment works</div>
      <ul class="sub" style="margin:.65rem 0 0;padding-left:1.15rem;line-height:1.75">
        <?php foreach (payment_guide_pay_page_summary() as $line): ?>
          <li><?= payment_guide_format_step($line) ?></li>
        <?php endforeach; ?>
      </ul>
      <div style="margin-top:.85rem">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/payment-instructions.php">Full payment guide</a>
      </div>
    </div>

    <div style="margin-top:1.2rem;display:flex;gap:.65rem;flex-wrap:wrap">
      <a class="btn secondary" href="<?= h((string)$ctx['back_url']) ?>">Back</a>
      <?php if ($kind === LISTING_PAY_LAND): ?>
        <a class="btn ghost" href="<?= APP_BASE_URL ?>/enquiry.php?listing_id=<?= $id ?>">Ask a question</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (snippe_enabled() && !($showPending && $snippeStatus === 'pending')): ?>
  <script>
  (function () {
    const form = document.getElementById('snippe-pay-form');
    if (!form) return;
    const actionInput = document.getElementById('snippe-pay-action');
    let locked = false;
    form.querySelectorAll('[data-pay-btn]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (actionInput) actionInput.value = btn.value;
      });
    });
    form.addEventListener('submit', function (e) {
      if (locked) {
        e.preventDefault();
        return;
      }
      locked = true;
      const msg = document.getElementById('snippe-pay-lock-msg');
      if (msg) msg.style.display = 'block';
      setTimeout(function () {
        form.querySelectorAll('[data-pay-btn]').forEach(function (b) { b.disabled = true; });
      }, 0);
    });
  })();
  </script>
  <?php endif; ?>

  <?php if ($showPending && snippe_enabled()): ?>
  <script>
  (function () {
    const statusEl = document.getElementById('snippe-wait-status');
    const checkBtn = document.getElementById('snippe-check-now');
    const listingId = <?= (int)$id ?>;
    const forQs = <?= json_encode($forQs, JSON_THROW_ON_ERROR) ?>;
    const doneUrl = <?= json_encode((string)$ctx['done_url'], JSON_THROW_ON_ERROR) ?>;
    let stopped = false;
    function poll() {
      if (stopped) return;
      if (statusEl) statusEl.textContent = 'Checking with payment provider…';
      fetch('<?= APP_BASE_URL ?>/payment-status.php?id=' + listingId + forQs, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
          if (!statusEl || !data) return;
          if (data.paid || data.snippe_status === 'completed') {
            statusEl.textContent = 'Payment confirmed! Redirecting…';
            stopped = true;
            window.location.href = doneUrl;
            return;
          }
          if (data.snippe_status === 'failed') {
            statusEl.textContent = data.snippe_last_error || 'Payment failed. Try again.';
            stopped = true;
            setTimeout(() => window.location.reload(), 2500);
            return;
          }
          if (data.snippe_status === 'expired' || data.snippe_status === 'failed' || data.snippe_status === 'none') {
            stopped = true;
            window.location.replace('<?= APP_BASE_URL ?>/pay-listing.php?id=' + listingId + forQs);
            return;
          }
          statusEl.textContent = 'Still waiting… approve the prompt on your phone, or tap Start over if it failed.';
        })
        .catch(() => { if (statusEl) statusEl.textContent = 'Could not check status. Tap check again or refresh.'; });
    }
    if (checkBtn) checkBtn.addEventListener('click', poll);
    poll();
    setInterval(poll, 3000);
  })();
  </script>
  <?php endif; ?>
<?php
$content = ob_get_clean();
$title = ($kind === LISTING_PAY_LAND ? 'Pay for plot' : 'Pay listing fee') . '. Ardhi Guide';
require __DIR__ . '/_layout.php';
