<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
$viewer = current_user();
$prefillName = $viewer ? (string)($viewer['full_name'] ?? '') : '';
$prefillPhone = $viewer ? trim((string)($viewer['phone'] ?? '')) : '';

$listingId = (int)($_GET['listing_id'] ?? 0);
$sent = isset($_GET['sent']);
$listing = null;

if ($listingId > 0) {
  $stmt = db()->prepare(
    "SELECT id, title, region, location_text, price_tzs, land_payment_amount_tzs, land_payment_status, verification_status
     FROM listings WHERE id = ? AND verification_status = 'approved' LIMIT 1"
  );
  $stmt->execute([$listingId]);
  $listing = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $listingId = (int)($_POST['listing_id'] ?? 0);
  $name = trim((string)($_POST['name'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $interest = trim((string)($_POST['interest'] ?? ''));
  $message = trim((string)($_POST['message'] ?? ''));

  if ($phone === '') {
    flash_set('err', 'Phone number is required so we can reach you.');
    redirect('/enquiry.php?listing_id=' . $listingId);
  }

  $userId = $viewer ? (int)$viewer['id'] : null;
  $ins = db()->prepare('INSERT INTO enquiries (listing_id,user_id,name,phone,interest,message,user_agent) VALUES (?,?,?,?,?,?,?)');
  $ins->execute([
    $listingId > 0 ? $listingId : null,
    $userId,
    $name !== '' ? $name : null,
    $phone,
    $interest !== '' ? $interest : null,
    $message !== '' ? $message : null,
    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
  ]);

  redirect('/enquiry.php?listing_id=' . $listingId . '&sent=1');
}

$waMsg = '';
if ($sent && $listing) {
  $waMsg = "Hello Ardhi Guide!\n\nFollow-up on my enquiry for:\n" . (string)$listing['title'] . "\n\nPlease share next steps. Asante!";
}

ob_start();
?>
  <div class="card pad reveal" style="max-width:820px;margin:0 auto">
    <div class="kicker">Enquiry</div>
    <?php if ($sent): ?>
      <h1>Enquiry received</h1>
      <p class="sub" style="line-height:1.7">Thank you. Our team has your details and will follow up. To pay for this plot online, use <strong>Pay</strong> on the listing page (login required).</p>
      <?php if ($listing && listing_land_payment_open($listing)): ?>
        <div style="margin-top:1.1rem;display:flex;gap:.7rem;flex-wrap:wrap">
          <?php if ($viewer): ?>
            <a class="btn" href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= (int)$listing['id'] ?>&for=land">Pay <?= h(format_tzs((string)($listing['land_payment_amount_tzs'] ?? '0'))) ?> online</a>
          <?php else: ?>
            <a class="btn" href="<?= APP_BASE_URL ?>/login.php?next=<?= rawurlencode('/pay-listing.php?id=' . (int)$listing['id'] . '&for=land') ?>">Log in to pay online</a>
          <?php endif; ?>
          <a class="btn secondary" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$listing['id'] ?>">Back to listing</a>
        </div>
      <?php endif; ?>
      <?php if ($waMsg !== ''): ?>
        <p class="sub" style="margin-top:1.25rem">Optional: continue on WhatsApp.</p>
        <a class="btn secondary" href="<?= h(whatsapp_link($waMsg)) ?>" target="_blank" rel="noopener">Open WhatsApp (optional)</a>
      <?php endif; ?>
    <?php else: ?>
      <h1>Ask about this plot</h1>
      <div class="sub">Send a question to our team. For payment, use <strong>Pay online</strong> on the listing page — WhatsApp is for messages only.</div>

      <?php if ($listing): ?>
        <div class="card pad" style="margin-top:1rem;background:var(--bg2)">
          <div style="font-weight:900"><?= h((string)$listing['title']) ?></div>
          <div class="sub" style="margin-top:.25rem;font-size:.95rem">
            <?= h((string)$listing['region']) ?><?php if (!empty($listing['location_text'])): ?> · <?= h((string)$listing['location_text']) ?><?php endif; ?>
            · Asking: <?= h(format_tzs((string)($listing['price_tzs'] ?? ''))) ?>
          </div>
          <?php if (listing_land_payment_open($listing)): ?>
            <p class="sub" style="margin-top:.75rem;margin-bottom:0">
              Payment due: <strong><?= h(format_tzs((string)($listing['land_payment_amount_tzs'] ?? '0'))) ?></strong> —
              <a href="<?= APP_BASE_URL ?>/<?= $viewer ? 'pay-listing.php?id=' . (int)$listing['id'] . '&for=land' : 'login.php?next=' . rawurlencode('/pay-listing.php?id=' . (int)$listing['id'] . '&for=land') ?>">pay online</a>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" class="stack" style="margin-top:1rem">
        <input type="hidden" name="listing_id" value="<?= (int)$listingId ?>">
        <div class="row">
          <div>
            <label>Full name</label>
            <input name="name" placeholder="Optional" value="<?= h($prefillName) ?>">
          </div>
          <div>
            <label>Phone</label>
            <input name="phone" type="tel" placeholder="0712 345 678" required value="<?= h($prefillPhone) ?>">
          </div>
        </div>
        <div>
          <label>Interest</label>
          <select name="interest">
            <option value="">Select</option>
            <option>Buying this land</option>
            <option>Need more info</option>
            <option>Request site visit</option>
            <option>Diaspora investor</option>
            <option>Legal / Survey service</option>
          </select>
        </div>
        <div>
          <label>Message</label>
          <textarea name="message" placeholder="Optional"></textarea>
        </div>
        <div style="display:flex;gap:.7rem;flex-wrap:wrap">
          <button class="btn" type="submit">Send enquiry</button>
          <a class="btn secondary" href="<?= APP_BASE_URL ?>/<?= $listing ? 'listing.php?id=' . (int)$listing['id'] : 'index.php' ?>">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$title = 'Enquiry. Ardhi Guide';
require __DIR__ . '/_layout.php';
