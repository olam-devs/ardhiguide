<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
$admin = require_role('admin');
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare(
  'SELECT u.*, r.name AS region_name, d.name AS district_name, w.name AS ward_name
   FROM users u
   LEFT JOIN locations_regions r ON r.code = u.region_code
   LEFT JOIN locations_districts d ON d.region_code = u.region_code AND d.code = u.district_code
   LEFT JOIN locations_wards w ON w.region_code = u.region_code AND w.district_code = u.district_code AND w.code = u.ward_code
   WHERE u.id = ? LIMIT 1'
);
$st->execute([$id]);
$profile = $st->fetch();
if (!$profile) { http_response_code(404); exit('User not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $status = (string)($_POST['verification_status'] ?? 'pending');
  $notes = trim((string)($_POST['verification_notes'] ?? ''));
  if (!in_array($status, ['pending','under_review','verified','rejected','not_required'], true)) {
    flash_set('err', 'Invalid verification status.');
  } else {
    $up = db()->prepare(
      'UPDATE users SET verification_status = ?, verification_notes = ?, verified_by_user_id = ?,
       verified_at = CASE WHEN ? = \'verified\' THEN NOW() ELSE NULL END WHERE id = ?'
    );
    $up->execute([$status, $notes !== '' ? $notes : null, (int)$admin['id'], $status, $id]);
    flash_set('ok', 'Verification decision saved.');
  }
  redirect('/admin/user.php?id=' . $id);
}

$docs = db()->prepare('SELECT id, document_type, original_name, mime, size_bytes, created_at FROM user_documents WHERE user_id = ? ORDER BY created_at DESC');
$docs->execute([$id]);
$documents = $docs->fetchAll();

ob_start();
?>
<div class="card pad reveal" style="max-width:1000px;margin:0 auto">
  <div class="kicker">Admin · KYC review</div>
  <div style="display:flex;justify-content:space-between;gap:1rem;align-items:start;flex-wrap:wrap">
    <div><h1><?= h((string)$profile['full_name']) ?></h1><div class="sub">User #<?= $id ?> · <?= h((string)$profile['role']) ?></div></div>
    <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/users.php">Back to users</a>
  </div>

  <div class="profile-grid" style="margin-top:1.2rem">
    <div><span>Phone</span><strong><?= h((string)$profile['phone']) ?></strong></div>
    <div><span>Email</span><strong><?= h((string)($profile['email'] ?: 'Not provided')) ?></strong></div>
    <div><span>NIDA</span><strong><?= h((string)($profile['nida_number'] ?: 'Not provided')) ?></strong></div>
    <div><span>Account type</span><strong><?= h((string)$profile['account_type']) ?></strong></div>
    <div><span>Verification</span><strong><?= h((string)$profile['verification_status']) ?></strong></div>
    <div><span>Address</span><strong><?= h((string)($profile['address_text'] ?: 'Not provided')) ?></strong></div>
    <?php if ($profile['role'] === 'agent' && $profile['account_type'] === 'company'): ?>
      <div><span>Company</span><strong><?= h((string)$profile['company_name']) ?></strong></div>
      <div><span>BRELA</span><strong><?= h((string)$profile['brela_number']) ?></strong></div>
      <div><span>TIN</span><strong><?= h((string)$profile['tin_number']) ?></strong></div>
    <?php endif; ?>
    <?php if ($profile['role'] === 'expert'): ?>
      <div><span>Profession</span><strong><?= h(str_replace('_', ' ', (string)$profile['expert_type'])) ?></strong></div>
      <div><span>Location</span><strong><?= h(implode(' · ', array_filter([(string)$profile['region_name'], (string)$profile['district_name'], (string)$profile['ward_name']]))) ?></strong></div>
    <?php endif; ?>
  </div>

  <h2 style="margin-top:1.5rem">Private documents</h2>
  <?php if (!$documents): ?><p class="sub">No KYC documents uploaded.</p><?php endif; ?>
  <div class="doc-list">
    <?php foreach ($documents as $doc): ?>
      <a href="<?= APP_BASE_URL ?>/admin/user-document.php?id=<?= (int)$doc['id'] ?>" target="_blank" rel="noopener">
        <strong><?= h(ucwords(str_replace('_', ' ', (string)$doc['document_type']))) ?></strong>
        <span><?= h((string)$doc['original_name']) ?> · <?= number_format(((int)$doc['size_bytes']) / 1024, 0) ?> KB</span>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="post" class="stack" style="margin-top:1.5rem;padding-top:1.2rem;border-top:1px solid var(--line)">
    <div><label>Verification decision</label><select name="verification_status">
      <?php foreach (['pending'=>'Pending','under_review'=>'Under review','verified'=>'Verified','rejected'=>'Rejected','not_required'=>'Not required'] as $value=>$label): ?>
        <option value="<?= $value ?>" <?= $profile['verification_status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select></div>
    <div><label>Admin notes</label><textarea name="verification_notes" placeholder="Reason, missing information, or review notes"><?= h((string)$profile['verification_notes']) ?></textarea></div>
    <div><button class="btn" type="submit">Save verification</button></div>
  </form>
</div>
<?php
$content = ob_get_clean();
$title = 'KYC review. Ardhi Way';
require __DIR__ . '/../_layout.php';
