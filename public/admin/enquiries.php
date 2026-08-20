<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
$admin = require_role('admin');

$providers = db()->query("SELECT id,full_name,role,phone,verification_status FROM users WHERE is_active=1 AND (role='admin' OR (role IN ('seller','agent') AND verification_status='verified')) ORDER BY FIELD(role,'admin','agent','seller'),full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id=(int)($_POST['id']??0);
  $status=(string)($_POST['status']??'new');
  $notes=trim((string)($_POST['admin_notes']??''));
  $providerId=(int)($_POST['assigned_provider_user_id']??0);
  if($id<=0||!in_array($status,['new','contacted','closed'],true)){
    flash_set('err','Invalid property request update.');
  } else {
    if($providerId>0){$check=db()->prepare("SELECT 1 FROM users WHERE id=? AND is_active=1 AND role IN ('seller','agent','admin')");$check->execute([$providerId]);if(!$check->fetchColumn())$providerId=0;}
    db()->prepare('UPDATE enquiries SET status=?,admin_notes=?,assigned_provider_user_id=?,assigned_at=IF(? IS NULL,NULL,COALESCE(assigned_at,NOW())) WHERE id=?')
      ->execute([$status,$notes?:null,$providerId?:null,$providerId?:null,$id]);
    flash_set('ok','Property request updated.');
  }
  redirect('/admin/enquiries.php');
}

$userFilter=(int)($_GET['user_id']??0);
$sql="SELECT e.*,e.user_id AS seeker_user_id,l.id AS listing_id,l.title AS listing_title,l.listing_type,l.region,l.created_by_user_id,
             seeker.full_name AS seeker_profile_name,
             owner.full_name AS owner_name,owner.role AS owner_role,owner.phone AS owner_phone,
             assigned.full_name AS assigned_name,assigned.role AS assigned_role,assigned.phone AS assigned_phone
      FROM enquiries e
      LEFT JOIN listings l ON l.id=e.listing_id
      LEFT JOIN users seeker ON seeker.id=e.user_id
      LEFT JOIN users owner ON owner.id=l.created_by_user_id
      LEFT JOIN users assigned ON assigned.id=e.assigned_provider_user_id";
$params=[];
if($userFilter>0){$sql.=' WHERE e.user_id=?';$params[]=$userFilter;}
$sql.=' ORDER BY FIELD(e.status,\'new\',\'contacted\',\'closed\'),e.id DESC LIMIT 300';
$stmt=db()->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();

ob_start();
?>
<div class="card pad reveal admin-request-header">
  <div><div class="kicker">Admin coordination</div><h1>Property seeker requests</h1><p class="sub">See the selected property, the submitting seller or agent, the seeker's profile and message, then assign the right provider.</p></div>
  <div class="form-actions"><a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/messages.php">Messages</a><a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/listings.php">Listings</a><a class="btn" href="<?= APP_BASE_URL ?>/submit-listing.php">Upload a property</a></div>
</div>

<div class="admin-request-grid">
  <?php if(!$rows):?><div class="card pad empty-state">No property requests found.</div><?php endif;?>
  <?php foreach($rows as $r):
    $requestLabel=['information'=>'More information','viewing'=>'Viewing / meetup','contact'=>'Guided contact','match_me'=>'Admin selects provider'][(string)$r['request_type']]??'Property request';
    $waText='Hello '.(string)($r['name']?:$r['seeker_profile_name']?:'there').', this is Ardhi Way following up on your '.$requestLabel.(!empty($r['listing_id'])?' for '.(string)$r['listing_title'].' '.rtrim(APP_BASE_URL,'/').'/listing.php?id='.(int)$r['listing_id']:'').'.';
  ?>
    <article class="card pad admin-request-card">
      <div class="request-card-head"><div><span class="pill <?= $r['status']==='new'?'warn':'ok' ?>"><?= h((string)$r['status']) ?></span><span class="pill neutral"><?= h($requestLabel) ?></span></div><small>#<?= (int)$r['id'] ?> · <?= h(date('M j, Y H:i',strtotime((string)$r['created_at']))) ?></small></div>
      <div class="request-parties">
        <div><span>Property seeker</span><strong><?= h((string)($r['seeker_profile_name']?:$r['name']?:'Guest')) ?></strong><small><?= h((string)$r['phone']) ?></small><?php if($r['seeker_user_id']):?><a href="<?= APP_BASE_URL ?>/admin/user.php?id=<?= (int)$r['seeker_user_id'] ?>">View profile</a><?php endif;?></div>
        <div><span>Listing provider</span><strong><?= h((string)($r['owner_name']?:'Ardhi Way to select')) ?></strong><small><?= h(ucfirst((string)($r['owner_role']?:'unassigned'))) ?></small><?php if($r['created_by_user_id']):?><a href="<?= APP_BASE_URL ?>/admin/user.php?id=<?= (int)$r['created_by_user_id'] ?>">View provider profile</a><?php endif;?></div>
      </div>
      <?php if($r['listing_id']):?><a class="attached-listing-card" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$r['listing_id'] ?>" target="_blank" rel="noopener"><span class="pill ok">Selected property</span><strong><?= h((string)$r['listing_title']) ?></strong><span><?= h(listing_type_label((string)$r['listing_type'])) ?> · <?= h((string)$r['region']) ?></span></a><?php endif;?>
      <?php if($r['message']):?><div class="seeker-message"><strong>Message:</strong> <?= nl2br(h((string)$r['message'])) ?></div><?php endif;?>
      <div class="request-contact-actions"><a class="btn whatsapp-action" href="<?= h(whatsapp_link($waText,(string)$r['phone'])) ?>" target="_blank" rel="noopener">WhatsApp seeker</a><?php if($r['seeker_user_id']):?><a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/messages.php?user_id=<?= (int)$r['seeker_user_id'] ?>">Open chat</a><?php endif;?></div>
      <form method="post" class="stack request-admin-form">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <div class="row"><div><label>Assigned seller / agent</label><select name="assigned_provider_user_id"><option value="">Admin team will select or coordinate</option><?php foreach($providers as $provider):?><option value="<?= (int)$provider['id'] ?>" <?= (int)$r['assigned_provider_user_id']===(int)$provider['id']?'selected':'' ?>><?= h((string)$provider['full_name']) ?> · <?= h(ucfirst((string)$provider['role'])) ?></option><?php endforeach;?></select></div><div><label>Status</label><select name="status"><?php foreach(['new','contacted','closed'] as $status):?><option value="<?= $status ?>" <?= $r['status']===$status?'selected':'' ?>><?= ucfirst($status) ?></option><?php endforeach;?></select></div></div>
        <div><label>Internal coordination notes</label><textarea name="admin_notes" placeholder="Next action, provider response, viewing date, or follow-up notes..."><?= h((string)$r['admin_notes']) ?></textarea></div>
        <div><button class="btn" type="submit">Save assignment and status</button></div>
      </form>
    </article>
  <?php endforeach;?>
</div>
<?php
$content=ob_get_clean();
$title='Property seeker requests. Ardhi Way';
require __DIR__.'/../_layout.php';
