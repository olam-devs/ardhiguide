<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
$admin = require_role('admin');
$conversationId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$requestedUserId = (int)($_GET['user_id'] ?? 0);
if ($conversationId === 0 && $requestedUserId > 0) {
  $conversationLookup = db()->prepare('SELECT id FROM conversations WHERE buyer_user_id=? LIMIT 1');
  $conversationLookup->execute([$requestedUserId]);
  $conversationId = (int)($conversationLookup->fetchColumn() ?: 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = trim((string)($_POST['body'] ?? ''));
  $listingId = (int)($_POST['listing_id'] ?? 0);
  $exists = db()->prepare('SELECT 1 FROM conversations WHERE id=?');
  $exists->execute([$conversationId]);
  if ($listingId > 0) {
    $validListing = db()->prepare("SELECT 1 FROM listings WHERE id=? AND verification_status='approved' AND is_taken=0");
    $validListing->execute([$listingId]);
    if (!$validListing->fetchColumn()) $listingId = 0;
  }
  if (!$exists->fetchColumn() || ($body === '' && $listingId === 0) || strlen($body) > 4000) {
    flash_set('err','Write a reply or select an approved property to share.');
  } else {
    if ($body === '') $body = 'This approved property may suit your request.';
    db()->prepare('INSERT INTO messages (conversation_id,sender_user_id,listing_id,body) VALUES (?,?,?,?)')
      ->execute([$conversationId,(int)$admin['id'],$listingId ?: null,$body]);
    db()->prepare("UPDATE conversations SET last_message_at=NOW(),status='open' WHERE id=?")->execute([$conversationId]);
    flash_set('ok','Reply sent to the property seeker.');
  }
  redirect('/admin/messages.php?id='.$conversationId);
}

$conversations = db()->query('SELECT c.*,u.full_name,u.phone,u.email,(SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.read_at IS NULL AND m.sender_user_id<>'.(int)$admin['id'].') unread FROM conversations c JOIN users u ON u.id=c.buyer_user_id ORDER BY c.last_message_at DESC,c.id DESC')->fetchAll();
$shareListings = db()->query("SELECT id,title,listing_type,region,price_min_tzs,price_max_tzs FROM listings WHERE verification_status='approved' AND is_taken=0 ORDER BY show_on_homepage DESC,is_featured DESC,id DESC LIMIT 300")->fetchAll();
$active=null;$messages=[];
if($conversationId>0){
  $st=db()->prepare('SELECT c.*,u.full_name,u.phone,u.email FROM conversations c JOIN users u ON u.id=c.buyer_user_id WHERE c.id=?');$st->execute([$conversationId]);$active=$st->fetch();
  if($active){
    $ms=db()->prepare("SELECT m.*,u.full_name,u.role,l.title AS listing_title,l.listing_type,l.region,l.price_min_tzs,l.price_max_tzs FROM messages m JOIN users u ON u.id=m.sender_user_id LEFT JOIN listings l ON l.id=m.listing_id WHERE m.conversation_id=? ORDER BY m.id");
    $ms->execute([$conversationId]);$messages=$ms->fetchAll();
    db()->prepare('UPDATE messages SET read_at=COALESCE(read_at,NOW()) WHERE conversation_id=? AND sender_user_id<>?')->execute([$conversationId,(int)$admin['id']]);
  }
}

ob_start();
?>
<div class="card pad reveal admin-chat-shell">
  <div class="kicker">Admin coordination</div><h1>Property seeker conversations</h1>
  <p class="sub">Review each profile and request, then share approved listings directly into the conversation.</p>
  <div class="inbox-layout">
    <aside class="inbox-list">
      <?php if(!$conversations):?><p class="sub">No conversations.</p><?php endif;?>
      <?php foreach($conversations as $c):?><a class="<?= (int)$c['id']===$conversationId?'active':'' ?>" href="<?= APP_BASE_URL ?>/admin/messages.php?id=<?= (int)$c['id'] ?>"><strong><?= h((string)$c['full_name']) ?></strong><span><?= h((string)$c['phone']) ?><?php if((int)$c['unread']>0):?> · <?= (int)$c['unread'] ?> new<?php endif;?></span></a><?php endforeach;?>
    </aside>
    <section>
      <?php if(!$active):?><div class="empty-state">Choose a property seeker to review the conversation.</div><?php else:?>
        <div class="chat-profile-bar"><div><h2><?= h((string)$active['full_name']) ?></h2><span><?= h((string)$active['phone']) ?><?php if($active['email']):?> · <?= h((string)$active['email']) ?><?php endif;?></span></div><div><a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/user.php?id=<?= (int)$active['buyer_user_id'] ?>">View profile</a><a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/enquiries.php?user_id=<?= (int)$active['buyer_user_id'] ?>">View requests</a></div></div>
        <div class="message-thread admin-thread">
          <?php foreach($messages as $msg):$mine=(int)$msg['sender_user_id']===(int)$admin['id'];?>
            <div class="message <?= $mine?'mine':'theirs' ?>">
              <?php if(!empty($msg['listing_id'])&&!empty($msg['listing_title'])):?><a class="message-listing-card" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$msg['listing_id'] ?>" target="_blank" rel="noopener"><span>Shared property #<?= (int)$msg['listing_id'] ?></span><strong><?= h((string)$msg['listing_title']) ?></strong><small><?= h(listing_type_label((string)$msg['listing_type'])) ?> · <?= h((string)$msg['region']) ?> · <?= h(format_tzs_range($msg['price_min_tzs'],$msg['price_max_tzs'])) ?></small></a><?php endif;?>
              <div><?= nl2br(h((string)$msg['body'])) ?></div><span><?= h((string)$msg['full_name']) ?> · <?= h(date('M j, H:i',strtotime((string)$msg['created_at']))) ?></span>
            </div>
          <?php endforeach;?>
        </div>
        <form method="post" class="stack admin-chat-compose">
          <input type="hidden" name="id" value="<?= $conversationId ?>">
          <div><label>Share an approved property</label><select name="listing_id"><option value="">No property attached</option><?php foreach($shareListings as $listing):?><option value="<?= (int)$listing['id'] ?>">#<?= (int)$listing['id'] ?> · <?= h((string)$listing['title']) ?> · <?= h((string)$listing['region']) ?></option><?php endforeach;?></select></div>
          <div><label>Reply</label><textarea name="body" maxlength="4000" placeholder="Explain why this listing fits, confirm next steps, or answer the property seeker's question..."></textarea></div>
          <div><button class="btn" type="submit">Send reply or property link</button></div>
        </form>
      <?php endif;?>
    </section>
  </div>
</div>
<?php
$content=ob_get_clean();
$title='Property seeker messages. Ardhi Way';
require __DIR__.'/../_layout.php';
