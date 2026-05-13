<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
$viewer = current_user();
$prefillName = $viewer ? (string)($viewer['full_name'] ?? '') : '';
$prefillPhone = '';

$listingId = (int)($_GET['listing_id'] ?? 0);
$stmt = null;
$listing = null;

if ($listingId > 0) {
  $stmt = db()->prepare("SELECT id,title,region,location_text,price_tzs FROM listings WHERE id=? AND verification_status='approved' LIMIT 1");
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
    flash_set('err', 'WhatsApp number is required.');
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

  $msg = "Hello Ardhi Guide!\n\n";
  if ($listingId > 0) {
    $st = db()->prepare("SELECT title,region,location_text,price_tzs FROM listings WHERE id=? LIMIT 1");
    $st->execute([$listingId]);
    $l = $st->fetch();
    if ($l) {
      $msg .= "Enquiry for listing:\n";
      $msg .= "Title: " . (string)$l['title'] . "\n";
      $msg .= "Region: " . (string)$l['region'] . "\n";
      $msg .= "Location: " . (string)($l['location_text'] ?? '-') . "\n";
      $msg .= "Price: " . format_tzs((string)($l['price_tzs'] ?? '')) . "\n\n";
    }
  } else {
    $msg .= "General enquiry.\n\n";
  }
  $msg .= "Buyer name: " . ($name !== '' ? $name : 'Buyer') . "\n";
  $msg .= "Buyer WhatsApp: " . $phone . "\n";
  if ($interest !== '') $msg .= "Interest: " . $interest . "\n";
  if ($message !== '') $msg .= "Message: " . $message . "\n";
  $msg .= "\nPlease share next steps. Asante!";

  header('Location: ' . whatsapp_link($msg));
  exit;
}

ob_start();
?>
  <div class="card pad reveal" style="max-width:820px;margin:0 auto">
    <div class="kicker">Enquiry</div>
    <h1>Send a WhatsApp enquiry</h1>
    <div class="sub">Your details are saved as a lead, then you’ll be redirected to WhatsApp to continue the conversation.</div>

    <?php if ($listing): ?>
      <div class="card pad" style="margin-top:1rem;background:var(--bg2)">
        <div style="font-weight:900"><?= h((string)$listing['title']) ?></div>
        <div class="sub" style="margin-top:.25rem;font-size:.95rem">
          <?= h((string)$listing['region']) ?><?php if (!empty($listing['location_text'])): ?> · <?= h((string)$listing['location_text']) ?><?php endif; ?> · <?= h(format_tzs((string)($listing['price_tzs'] ?? ''))) ?>
        </div>
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
          <label>WhatsApp number</label>
          <input name="phone" placeholder="+255 700 000 000" required value="<?= h($prefillPhone) ?>">
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
        <button class="btn" type="submit">Open WhatsApp</button>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/<?= $listing ? 'listing.php?id=' . (int)$listing['id'] : 'index.php' ?>">Cancel</a>
      </div>
    </form>
  </div>
<?php
$content = ob_get_clean();
$title = 'Enquiry. Ardhi Guide';
require __DIR__ . '/_layout.php';

