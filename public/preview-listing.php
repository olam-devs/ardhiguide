<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  echo 'Not found';
  exit;
}

$stmt = db()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$l = $stmt->fetch();
if (!$l) {
  http_response_code(404);
  echo 'Not found';
  exit;
}

$ownerId = (int)($l['created_by_user_id'] ?? 0);
$isAdmin = (($u['role'] ?? '') === 'admin');
$isOwner = ($ownerId === (int)$u['id']);
if (!$isAdmin && !$isOwner) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$status = (string)$l['verification_status'];
$isPublic = ($status === 'approved');

$imgsStmt = db()->prepare('SELECT file_path FROM listing_images WHERE listing_id=? ORDER BY id ASC');
$imgsStmt->execute([$id]);
$imgs = $imgsStmt->fetchAll();
$heroImg = $imgs ? public_file((string)$imgs[0]['file_path']) : null;

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem;border-color:rgba(200,150,12,.35);background:rgba(200,150,12,.08)">
    <strong>Preview</strong>
    <span class="sub" style="display:block;margin-top:.35rem">
      This page is only visible to you and admins.
      <?php if (!$isPublic): ?>
        This listing is not published. Public link will work after approval.
      <?php endif; ?>
    </span>
    <div style="margin-top:.85rem;display:flex;gap:.6rem;flex-wrap:wrap">
      <a class="btn secondary" href="<?= APP_BASE_URL ?>/listing-documents.php?id=<?= $id ?>">Verification documents</a>
      <?php if ($isAdmin): ?>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/view-listing.php?id=<?= $id ?>">Admin detail</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card reveal" style="overflow:hidden;border-color:rgba(139,69,19,.18)">
    <div style="height:240px;background:linear-gradient(135deg, rgba(139,69,19,.20), rgba(200,150,12,.18));position:relative">
      <?php if ($heroImg): ?>
        <img src="<?= APP_BASE_URL ?>/<?= h($heroImg) ?>" alt="Listing photo" style="width:100%;height:100%;object-fit:cover;display:block">
      <?php endif; ?>
      <div style="position:absolute;inset:0;background:linear-gradient(0deg, rgba(20,12,7,.62), rgba(20,12,7,.10), transparent)"></div>
      <div style="position:absolute;left:16px;right:16px;bottom:16px;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div>
          <div class="kicker" style="color:rgba(255,255,255,.85)"><?= h((string)$l['region']) ?> · <?= h(listing_type_label((string)$l['listing_type'])) ?></div>
          <h1 style="margin:.4rem 0 .4rem;color:#fff"><?= h((string)$l['title']) ?></h1>
          <div style="color:rgba(255,255,255,.78);line-height:1.6">
            <?= h((string)($l['location_text'] ?? '')) ?>
            <?php if (!empty($l['size_text'])): ?> · <?= h((string)$l['size_text']) ?><?php endif; ?>
          </div>
        </div>
        <div style="text-align:right;min-width:240px">
          <div class="price" style="color:#fff;font-size:1.7rem"><?= h(format_tzs_range($l['price_min_tzs'], $l['price_max_tzs'])) ?></div>
          <div style="margin-top:.65rem;display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/my-listings.php">Back</a>
            <?php if ($isPublic): ?>
              <a class="btn" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$l['id'] ?>">Public page</a>
            <?php endif; ?>
          </div>
          <div style="margin-top:.8rem;display:flex;gap:.5rem;justify-content:flex-end;flex-wrap:wrap">
            <span class="pill <?= $isPublic ? 'ok' : 'warn' ?>"><?= h($status) ?></span>
            <?php if (($l['verification_badge'] ?? 'none') !== 'none'): ?>
              <span class="pill neutral"><?= h((string)$l['verification_badge']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php require __DIR__ . '/_partials/listing-video.php'; ?>

  <div class="grid" style="margin-top:1rem;align-items:start">
    <div class="col-7">
      <div class="card pad reveal">
        <div class="kicker">Details</div>
        <h2 style="margin:.35rem 0 .8rem;font-size:1.35rem">About this listing</h2>
        <div class="sub" style="color:rgba(20,12,7,.78)">
          <?= nl2br(h((string)($l['description'] ?? ''))) ?>
        </div>
      </div>
    </div>
    <div class="col-5">
      <div class="card pad reveal">
        <div class="kicker">Photos</div>
        <h2 style="margin:.35rem 0 .8rem;font-size:1.35rem">Gallery</h2>
        <?php if (!$imgs): ?>
          <div class="sub">No photos uploaded.</div>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem">
            <?php foreach ($imgs as $im): ?>
              <?php $p = public_file((string)$im['file_path']); ?>
              <a href="<?= APP_BASE_URL ?>/<?= h($p) ?>" target="_blank" rel="noreferrer" style="text-decoration:none">
                <img src="<?= APP_BASE_URL ?>/<?= h($p) ?>" alt="Listing photo" style="width:100%;height:132px;object-fit:cover;border-radius:12px;border:1px solid var(--line)">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($isPublic): ?>
          <div style="margin-top:1rem">
            <a class="btn" style="width:100%" href="<?= APP_BASE_URL ?>/enquiry.php?listing_id=<?= (int)$l['id'] ?>">Enquire (test)</a>
          </div>
        <?php else: ?>
          <p class="sub" style="margin-top:1rem">Enquiries open only after the listing is approved.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php
$content = ob_get_clean();
$title = 'Preview: ' . (string)$l['title'];
require __DIR__ . '/_layout.php';
