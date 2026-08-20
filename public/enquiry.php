<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
$viewer = current_user();
$prefillName = $viewer ? (string)($viewer['full_name'] ?? '') : '';
$prefillPhone = $viewer ? trim((string)($viewer['phone'] ?? '')) : '';
$listingId = (int)($_GET['listing_id'] ?? $_POST['listing_id'] ?? 0);
$sent = isset($_GET['sent']);
$requestedType = (string)($_GET['request'] ?? $_POST['request_type'] ?? 'information');
$requestTypes = [
  'information' => 'Request more information',
  'viewing' => 'Request a viewing or meetup',
  'contact' => 'Initiate contact with the listing provider',
  'match_me' => 'Ask admin to select a suitable provider',
];
if (!isset($requestTypes[$requestedType])) $requestedType = 'information';

$listing = null;
if ($listingId > 0) {
  $stmt = db()->prepare(
    "SELECT l.id,l.title,l.listing_type,l.region,l.location_text,l.price_min_tzs,l.price_max_tzs,l.created_by_user_id,
            u.full_name AS provider_name,u.role AS provider_role,u.verification_status AS provider_verification
     FROM listings l LEFT JOIN users u ON u.id=l.created_by_user_id
     WHERE l.id=? AND l.verification_status='approved' AND l.is_taken=0 LIMIT 1"
  );
  $stmt->execute([$listingId]);
  $listing = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim((string)($_POST['name'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $message = trim((string)($_POST['message'] ?? ''));
  $providerPreference = (string)($_POST['provider_preference'] ?? 'listing_provider');
  if (!isset($requestTypes[$requestedType])) $requestedType = 'information';
  if (!in_array($providerPreference, ['listing_provider','admin_select'], true)) $providerPreference = 'listing_provider';
  if ($requestedType === 'match_me') $providerPreference = 'admin_select';

  if ($phone === '') {
    flash_set('err', 'Phone number is required so the Ardhi Way team can reach you.');
    redirect('/enquiry.php?listing_id=' . $listingId . '&request=' . rawurlencode($requestedType));
  }

  $assignedProviderId = null;
  if ($listing && $providerPreference === 'listing_provider') {
    $assignedProviderId = (int)($listing['created_by_user_id'] ?? 0) ?: null;
  }
  $userId = $viewer ? (int)$viewer['id'] : null;
  $ins = db()->prepare('INSERT INTO enquiries
    (listing_id,user_id,name,phone,interest,message,user_agent,request_type,provider_preference,assigned_provider_user_id,assigned_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)');
  $ins->execute([
    $listingId > 0 ? $listingId : null,
    $userId,
    $name !== '' ? $name : null,
    $phone,
    $requestTypes[$requestedType],
    $message !== '' ? $message : null,
    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    $requestedType,
    $providerPreference,
    $assignedProviderId,
    $assignedProviderId ? date('Y-m-d H:i:s') : null,
  ]);

  if ($userId) {
    $find = db()->prepare('SELECT id FROM conversations WHERE buyer_user_id=? LIMIT 1');
    $find->execute([$userId]);
    $conversationId = (int)($find->fetchColumn() ?: 0);
    if ($conversationId === 0) {
      db()->prepare('INSERT INTO conversations (buyer_user_id,last_message_at) VALUES (?,NOW())')->execute([$userId]);
      $conversationId = (int)db()->lastInsertId();
    }
    $chatBody = $requestTypes[$requestedType];
    if ($message !== '') $chatBody .= "\n" . $message;
    db()->prepare('INSERT INTO messages (conversation_id,sender_user_id,listing_id,body) VALUES (?,?,?,?)')
      ->execute([$conversationId,$userId,$listingId > 0 ? $listingId : null,$chatBody]);
    db()->prepare("UPDATE conversations SET status='open',last_message_at=NOW() WHERE id=?")->execute([$conversationId]);
  }

  redirect('/enquiry.php?listing_id=' . $listingId . '&request=' . rawurlencode($requestedType) . '&sent=1');
}

$listingUrl = $listing ? rtrim(APP_BASE_URL, '/') . '/listing.php?id=' . (int)$listing['id'] : rtrim(APP_BASE_URL, '/') . '/index.php';
$waMsg = $listing
  ? "Hello Ardhi Way, I submitted a request for:\n\n" . (string)$listing['title'] . "\n" . $requestTypes[$requestedType] . "\n\n" . $listingUrl
  : 'Hello Ardhi Way, I need help finding a suitable property.';

ob_start();
?>
  <div class="card pad reveal enquiry-card">
    <div class="kicker">Property request</div>
    <?php if ($sent): ?>
      <h1>Your request is with Ardhi Way</h1>
      <p class="lead">Admin can now review the property, your preferred next step, and the seller or agent connected to it. We will coordinate the response privately.</p>
      <div class="request-success-actions">
        <?php if ($viewer): ?><a class="btn" href="<?= APP_BASE_URL ?>/messages.php">Open your Ardhi Way chat</a><?php endif; ?>
        <?php if ($listing): ?><a class="btn secondary" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$listing['id'] ?>">Back to property</a><?php endif; ?>
        <a class="btn whatsapp-action" href="<?= h(whatsapp_link($waMsg)) ?>" target="_blank" rel="noopener">Continue on WhatsApp</a>
      </div>
    <?php else: ?>
      <h1>How would you like to proceed?</h1>
      <p class="sub">Request details, arrange a viewing, initiate guided contact, or ask admin to select the most suitable provider.</p>

      <?php if ($listing): ?>
        <a class="attached-listing-card" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$listing['id'] ?>">
          <span class="pill ok">Approved property</span>
          <strong><?= h((string)$listing['title']) ?></strong>
          <span><?= h(listing_type_label((string)$listing['listing_type'])) ?> · <?= h((string)$listing['region']) ?> · <?= h(format_tzs_range($listing['price_min_tzs'], $listing['price_max_tzs'])) ?></span>
        </a>
      <?php endif; ?>

      <form method="post" class="stack request-form">
        <input type="hidden" name="listing_id" value="<?= (int)$listingId ?>">
        <div>
          <label>What do you need?</label>
          <div class="request-type-grid">
            <?php foreach ($requestTypes as $key => $label): ?>
              <label class="request-type-option"><input type="radio" name="request_type" value="<?= h($key) ?>" <?= $requestedType === $key ? 'checked' : '' ?>><span><?= h($label) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php if ($listing): ?>
          <div>
            <label>Who should admin coordinate?</label>
            <select name="provider_preference">
              <option value="listing_provider">The <?= h(($listing['provider_role'] ?? '') === 'admin' ? 'Ardhi Way team' : (string)($listing['provider_role'] ?? 'provider')) ?> attached to this property</option>
              <option value="admin_select">Let admin select another suitable seller or agent</option>
            </select>
          </div>
        <?php else: ?>
          <input type="hidden" name="provider_preference" value="admin_select">
        <?php endif; ?>
        <div class="row"><div><label>Full name</label><input name="name" placeholder="Your name" value="<?= h($prefillName) ?>"></div><div><label>Phone / WhatsApp</label><input name="phone" type="tel" required placeholder="0712 345 678" value="<?= h($prefillPhone) ?>"></div></div>
        <div><label>Message for admin</label><textarea name="message" placeholder="Preferred day for a viewing, questions, budget, location needs, or any special instructions..."></textarea></div>
        <div class="form-actions"><button class="btn" type="submit">Send property request</button><a class="btn secondary" href="<?= APP_BASE_URL ?>/<?= $listing ? 'listing.php?id=' . (int)$listing['id'] : 'index.php' ?>">Cancel</a></div>
      </form>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$title = 'Property request. Ardhi Way';
require __DIR__ . '/_layout.php';
