<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
if (!user_can_manage_listings($u)) {
  flash_set('err', 'You need a seller account to manage listings. Open My account to switch to Seller.');
  redirect('/my-account.php');
}

$uid = (int)$u['id'];
$stmt = db()->prepare(
  "SELECT l.id, l.title, l.region, l.category, l.verification_status, l.verification_badge, l.is_featured, l.created_at,
          l.listing_package, l.payment_status, l.payment_amount_tzs, l.video_path,
          (SELECT COUNT(*) FROM enquiries e WHERE e.listing_id = l.id) AS enquiry_count,
          (SELECT COUNT(*) FROM listing_documents d WHERE d.listing_id = l.id) AS doc_count
   FROM listings l
   WHERE l.created_by_user_id = ?
   ORDER BY l.id DESC"
);
$stmt->execute([$uid]);
$rows = $stmt->fetchAll();

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem">
    <div class="grid" style="align-items:end">
      <div class="col-7">
        <div class="kicker">Dashboard</div>
        <h1 style="margin-bottom:.35rem">My listings</h1>
        <div class="sub">Track status, leads, and open a preview before a listing is approved.</div>
      </div>
      <div class="col-5" style="display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/index.php">Browse</a>
        <a class="btn" href="<?= APP_BASE_URL ?>/submit-listing.php">New listing</a>
      </div>
    </div>
  </div>

  <div class="card pad reveal" style="overflow:auto">
    <?php if (!$rows): ?>
      <p class="sub">You have no listings yet. <a href="<?= APP_BASE_URL ?>/submit-listing.php" style="font-weight:800;color:var(--earth)">Create your first listing</a>.</p>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse;min-width:760px">
        <thead>
          <tr style="background:var(--bg2);text-align:left">
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Listing</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Status</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Badge</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Leads</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Docs</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Fee</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)">
                <div style="font-weight:900"><?= h((string)$r['title']) ?></div>
                <div class="sub" style="font-size:.9rem"><?= h((string)$r['region']) ?> · <?= h((string)$r['category']) ?></div>
                <?php if (!empty($r['video_path'])): ?>
                  <span class="pill ok" style="margin-top:.35rem;font-size:.62rem">Video uploaded</span>
                <?php endif; ?>
              </td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)">
                <?php $st = (string)$r['verification_status']; ?>
                <?php if ($st === 'approved'): ?>
                  <span class="pill ok">approved</span>
                <?php elseif ($st === 'under_review'): ?>
                  <span class="pill warn">under review</span>
                <?php elseif ($st === 'rejected'): ?>
                  <span class="pill neutral">rejected</span>
                <?php else: ?>
                  <span class="pill neutral">submitted</span>
                <?php endif; ?>
              </td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)">
                <span class="pill neutral"><?= h((string)$r['verification_badge']) ?></span>
              </td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= (int)$r['enquiry_count'] ?></td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= (int)($r['doc_count'] ?? 0) ?></td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)">
                <?php $ps = (string)($r['payment_status'] ?? 'pending'); ?>
                <span class="pill <?= $ps === 'paid' ? 'ok' : ($ps === 'waived' ? 'neutral' : 'warn') ?>"><?= h($ps) ?></span>
                <div class="sub" style="font-size:.8rem"><?= h(format_tzs((string)($r['payment_amount_tzs'] ?? '0'))) ?></div>
              </td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)">
                <div style="display:flex;gap:.45rem;flex-wrap:wrap">
                  <?php if ($ps === 'pending'): ?>
                    <a class="btn" style="padding:.55rem .9rem" href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= (int)$r['id'] ?>">Pay</a>
                  <?php endif; ?>
                  <a class="btn secondary" style="padding:.55rem .9rem" href="<?= APP_BASE_URL ?>/listing-documents.php?id=<?= (int)$r['id'] ?>">Documents</a>
                  <?php if ($st === 'approved'): ?>
                    <a class="btn secondary" style="padding:.55rem .9rem" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$r['id'] ?>">Public view</a>
                  <?php endif; ?>
                  <a class="btn secondary" style="padding:.55rem .9rem" href="<?= APP_BASE_URL ?>/preview-listing.php?id=<?= (int)$r['id'] ?>"><?= !empty($r['video_path']) ? 'Preview + video' : 'Preview' ?></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$title = 'My listings. Ardhi Guide';
require __DIR__ . '/_layout.php';
