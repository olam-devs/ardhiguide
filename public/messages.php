<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
$u = require_auth();
if (($u['role'] ?? '') === 'admin') redirect('/admin/messages.php');
$uid = (int)$u['id'];
$selectedListingId = (int)($_GET['listing_id'] ?? $_POST['listing_id'] ?? 0);

$find = db()->prepare('SELECT id FROM conversations WHERE buyer_user_id=? LIMIT 1');
$find->execute([$uid]);
$conversationId = (int)($find->fetchColumn() ?: 0);

$selectedListing = null;
if ($selectedListingId > 0) {
  $ls = db()->prepare("SELECT id,title,listing_type,region,price_min_tzs,price_max_tzs FROM listings WHERE id=? AND verification_status='approved' AND is_taken=0");
  $ls->execute([$selectedListingId]);
  $selectedListing = $ls->fetch() ?: null;
  if (!$selectedListing) $selectedListingId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = trim((string)($_POST['body'] ?? ''));
  if (($body === '' && $selectedListingId === 0) || strlen($body) > 4000) {
    flash_set('err', 'Write a message or attach a property listing.');
  } else {
    if ($conversationId === 0) {
      db()->prepare('INSERT INTO conversations (buyer_user_id,last_message_at) VALUES (?,NOW())')->execute([$uid]);
      $conversationId = (int)db()->lastInsertId();
    }
    if ($body === '') $body = 'I would like help with this property.';
    db()->prepare('INSERT INTO messages (conversation_id,sender_user_id,listing_id,body) VALUES (?,?,?,?)')
      ->execute([$conversationId,$uid,$selectedListingId ?: null,$body]);
    db()->prepare("UPDATE conversations SET status='open',last_message_at=NOW() WHERE id=?")->execute([$conversationId]);
    flash_set('ok', 'Message sent to Ardhi Way.');
  }
  redirect('/messages.php');
}

$messages = [];
if ($conversationId > 0) {
  $st = db()->prepare("SELECT m.*,u.full_name,u.role,l.title AS listing_title,l.listing_type,l.region,l.price_min_tzs,l.price_max_tzs
    FROM messages m JOIN users u ON u.id=m.sender_user_id LEFT JOIN listings l ON l.id=m.listing_id
    WHERE m.conversation_id=? ORDER BY m.id ASC");
  $st->execute([$conversationId]);
  $messages = $st->fetchAll();
  db()->prepare('UPDATE messages SET read_at=COALESCE(read_at,NOW()) WHERE conversation_id=? AND sender_user_id<>?')->execute([$conversationId,$uid]);
}

ob_start();
?>
<div class="card pad reveal chat-shell">
  <div class="kicker">Private support</div>
  <h1>Chat with Ardhi Way</h1>
  <p class="sub">Ask for help or share a property directly in this chat. Admin can reply with other approved property links.</p>
  <div class="message-thread">
    <?php if (!$messages): ?><div class="empty-state">No messages yet. Start the conversation below.</div><?php endif; ?>
    <?php foreach ($messages as $msg): $mine=(int)$msg['sender_user_id']===$uid; ?>
      <div class="message <?= $mine ? 'mine' : 'theirs' ?>">
        <?php if (!empty($msg['listing_id']) && !empty($msg['listing_title'])): ?>
          <a class="message-listing-card" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$msg['listing_id'] ?>">
            <span>Property #<?= (int)$msg['listing_id'] ?></span><strong><?= h((string)$msg['listing_title']) ?></strong>
            <small><?= h(listing_type_label((string)$msg['listing_type'])) ?> · <?= h((string)$msg['region']) ?> · <?= h(format_tzs_range($msg['price_min_tzs'],$msg['price_max_tzs'])) ?></small>
          </a>
        <?php endif; ?>
        <div><?= nl2br(h((string)$msg['body'])) ?></div>
        <span><?= $mine ? 'You' : 'Ardhi Way' ?> · <?= h(date('M j, H:i',strtotime((string)$msg['created_at']))) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <form method="post" class="stack chat-compose">
    <?php if ($selectedListing): ?>
      <input type="hidden" name="listing_id" value="<?= (int)$selectedListing['id'] ?>">
      <div class="compose-attachment"><div><span class="pill ok">Attached property</span><strong><?= h((string)$selectedListing['title']) ?></strong><small><?= h((string)$selectedListing['region']) ?> · <?= h(format_tzs_range($selectedListing['price_min_tzs'],$selectedListing['price_max_tzs'])) ?></small></div><a href="<?= APP_BASE_URL ?>/messages.php">Remove</a></div>
    <?php endif; ?>
    <div><label>Your message</label><textarea name="body" maxlength="4000" placeholder="Tell admin what information, viewing, contact, or property match you need..."></textarea></div>
    <div class="form-actions"><button class="btn" type="submit">Send to admin</button><a class="btn secondary" href="<?= APP_BASE_URL ?>/index.php#browse">Browse properties</a></div>
  </form>
</div>
<?php
$content=ob_get_clean();
$title='Property support chat. Ardhi Way';
require __DIR__.'/_layout.php';
