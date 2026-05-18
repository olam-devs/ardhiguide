<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
$viewer = current_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(404);
  echo "Not found";
  exit;
}

$stmt = db()->prepare("SELECT * FROM listings WHERE id=? AND verification_status='approved' LIMIT 1");
$stmt->execute([$id]);
$l = $stmt->fetch();
if (!$l) {
  http_response_code(404);
  echo "Not found";
  exit;
}

$imgsStmt = db()->prepare("SELECT file_path FROM listing_images WHERE listing_id=? ORDER BY id ASC");
$imgsStmt->execute([$id]);
$imgs = $imgsStmt->fetchAll();
$heroImg = $imgs ? public_file((string)$imgs[0]['file_path']) : null;

ob_start();
?>
  <div class="card reveal" style="overflow:hidden;border-color:rgba(139,69,19,.18)">
    <div style="height:240px;background:linear-gradient(135deg, rgba(139,69,19,.20), rgba(200,150,12,.18));position:relative">
      <?php if ($heroImg): ?>
        <img src="<?= APP_BASE_URL ?>/<?= h($heroImg) ?>" alt="Listing photo" style="width:100%;height:100%;object-fit:cover;display:block">
      <?php endif; ?>
      <div style="position:absolute;inset:0;background:linear-gradient(0deg, rgba(20,12,7,.62), rgba(20,12,7,.10), transparent)"></div>
      <div style="position:absolute;left:16px;right:16px;bottom:16px;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-end">
        <div>
          <div class="kicker" style="color:rgba(255,255,255,.85)"><?= h((string)$l['region']) ?> · <?= h((string)$l['category']) ?></div>
          <h1 style="margin:.4rem 0 .4rem;color:#fff"><?= h((string)$l['title']) ?></h1>
          <div style="color:rgba(255,255,255,.78);line-height:1.6">
            <?= h((string)($l['location_text'] ?? '')) ?>
            <?php if (!empty($l['size_text'])): ?> · <?= h((string)$l['size_text']) ?><?php endif; ?>
          </div>
        </div>
        <div style="text-align:right;min-width:240px">
          <div class="price" style="color:#fff;font-size:1.7rem"><?= h(format_tzs((string)($l['price_tzs'] ?? ''))) ?></div>
          <div style="margin-top:.65rem;display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/index.php">Back</a>
            <?php if (listing_land_payment_open($l)): ?>
              <?php $payUrl = '/pay-listing.php?id=' . (int)$l['id'] . '&for=land'; ?>
              <?php if ($viewer): ?>
                <a class="btn" href="<?= APP_BASE_URL . $payUrl ?>">Pay <?= h(format_tzs((string)($l['land_payment_amount_tzs'] ?? '0'))) ?></a>
              <?php else: ?>
                <a class="btn" href="<?= APP_BASE_URL ?>/login.php?next=<?= rawurlencode($payUrl) ?>">Log in to pay</a>
              <?php endif; ?>
            <?php endif; ?>
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/enquiry.php?listing_id=<?= (int)$l['id'] ?>">Enquire</a>
          </div>
          <div style="margin-top:.8rem;display:flex;gap:.5rem;justify-content:flex-end;flex-wrap:wrap">
            <span class="pill ok">Approved</span>
            <?php if (($l['verification_badge'] ?? 'none') !== 'none'): ?>
              <span class="pill warn"><?= h((string)$l['verification_badge']) ?></span>
            <?php else: ?>
              <span class="pill neutral">No badge</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="grid" style="margin-top:1rem;align-items:start">
    <div class="col-7">
      <?php $vp = (string)($l['video_path'] ?? ''); if ($vp !== ''): $vm = (string)($l['video_mime'] ?? 'video/mp4'); ?>
        <div class="card reveal listing-video" style="margin-bottom:1rem;overflow:hidden">
          <video controls preload="metadata" playsinline style="width:100%;display:block;background:#000">
            <source src="<?= APP_BASE_URL ?>/<?= h(public_file($vp)) ?>" type="<?= h($vm) ?>">
            Your browser does not support inline video.
          </video>
          <div class="pad" style="padding:.85rem 1rem;display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
            <span class="pill ok">Walk-around video</span>
            <span class="sub" style="margin:0;font-size:.9rem">Short clip uploaded by the seller.</span>
          </div>
        </div>
      <?php endif; ?>

      <div class="card pad reveal">
        <div class="kicker">Details</div>
        <h2 style="margin:.35rem 0 .8rem;font-size:1.35rem">About this listing</h2>
        <div class="sub" style="color:rgba(20,12,7,.78)">
          <?= nl2br(h((string)($l['description'] ?? ''))) ?>
        </div>
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line);color:rgba(20,12,7,.55);font-size:.9rem;line-height:1.7">
          Verification here means an internal review status and badge level; it is not a legal guarantee of ownership.
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
        <div style="margin-top:1rem;display:flex;flex-direction:column;gap:.5rem">
          <?php if (listing_land_payment_open($l)): ?>
            <?php $payUrl = '/pay-listing.php?id=' . (int)$l['id'] . '&for=land'; ?>
            <a class="btn" style="width:100%" href="<?= APP_BASE_URL ?><?= $viewer ? $payUrl : '/login.php?next=' . rawurlencode($payUrl) ?>">
              <?= $viewer ? 'Pay online' : 'Log in to pay' ?> · <?= h(format_tzs((string)($l['land_payment_amount_tzs'] ?? '0'))) ?>
            </a>
          <?php endif; ?>
          <a class="btn secondary" style="width:100%" href="<?= APP_BASE_URL ?>/enquiry.php?listing_id=<?= (int)$l['id'] ?>">Ask a question</a>
        </div>
      </div>
    </div>
  </div>
<?php
$content = ob_get_clean();
$title = (string)$l['title'] . '. Ardhi Guide';
require __DIR__ . '/_layout.php';

