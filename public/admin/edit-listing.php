<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$u = require_role('admin');
$listingId = (int)($_GET['id'] ?? 0);
if ($listingId <= 0) {
  flash_set('err', 'Invalid listing.');
  redirect('/admin/listings.php');
}

$paymentRedirectBase = '/admin/edit-listing.php?id=' . $listingId;
require __DIR__ . '/_handle-payment-post.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array((string)($_POST['action'] ?? ''), [
  'save_payment_settings',
  'save_land_payment_settings',
  'mark_land_paid',
  'mark_land_waived',
], true)) {
  $err = listing_admin_update($listingId, $_POST);
  if ($err !== null) {
    flash_set('err', $err);
    redirect('/admin/edit-listing.php?id=' . $listingId);
  }

  $st = db()->prepare('SELECT video_path FROM listings WHERE id = ? LIMIT 1');
  $st->execute([$listingId]);
  $listingRow = $st->fetch() ?: [];

  if (!empty($_FILES['video']) && (int)($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $vRes = listing_video_validate_and_save($listingId, [
      'name' => (string)($_FILES['video']['name'] ?? ''),
      'type' => (string)($_FILES['video']['type'] ?? ''),
      'tmp_name' => (string)($_FILES['video']['tmp_name'] ?? ''),
      'error' => (int)($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE),
      'size' => (int)($_FILES['video']['size'] ?? 0),
    ]);
    if (!empty($vRes['ok'])) {
      listing_video_delete((string)($listingRow['video_path'] ?? ''));
      $vUp = db()->prepare('UPDATE listings SET video_path = ?, video_mime = ?, video_size_bytes = ? WHERE id = ?');
      $vUp->execute([$vRes['rel_path'], $vRes['mime'], $vRes['size'], $listingId]);
    } else {
      flash_set('err', 'Saved, but video upload failed: ' . ($vRes['err'] ?? 'unknown'));
      redirect('/admin/edit-listing.php?id=' . $listingId);
    }
  }

  flash_set('ok', 'Listing #' . $listingId . ' updated.');
  redirect('/admin/edit-listing.php?id=' . $listingId);
}

$st = db()->prepare(
  'SELECT l.*, u.email AS owner_email, u.phone AS owner_phone, u.full_name AS owner_name
   FROM listings l
   LEFT JOIN users u ON u.id = l.created_by_user_id
   WHERE l.id = ? LIMIT 1'
);
$st->execute([$listingId]);
$listing = $st->fetch();
if (!$listing) {
  flash_set('err', 'Listing not found.');
  redirect('/admin/listings.php');
}

$regions = location_regions();
$districts = !empty($listing['region_code']) ? location_districts((string)$listing['region_code']) : [];
$wards = !empty($listing['district_code']) ? location_wards((string)$listing['region_code'], (string)$listing['district_code']) : [];

$images = listing_admin_images($listingId);
$docSt = db()->prepare('SELECT COUNT(*) AS c FROM listing_documents WHERE listing_id = ?');
$docSt->execute([$listingId]);
$docCount = (int)($docSt->fetch()['c'] ?? 0);

$verStatus = (string)($listing['verification_status'] ?? 'pending');
$pkg = (string)($listing['listing_package'] ?? 'basic');
$paymentFormAction = APP_BASE_URL . '/admin/edit-listing.php?id=' . $listingId;

ob_start();
?>
  <div class="card pad reveal" style="max-width:1040px;margin:0 auto">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
      <div>
        <div class="kicker">Admin · Edit listing #<?= $listingId ?></div>
        <h1 style="margin:.25rem 0"><?= h((string)$listing['title']) ?></h1>
        <p class="sub">Everything the seller submitted is shown below. Edit text fields, then save. Photos and private documents are view-only here.</p>
      </div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap">
        <span class="pill <?= $verStatus === 'approved' ? 'ok' : ($verStatus === 'rejected' ? 'warn' : 'neutral') ?>"><?= h($verStatus) ?></span>
        <span class="pill neutral"><?= h((string)($listing['payment_status'] ?? 'pending')) ?> fee</span>
        <span class="pill neutral"><?= h((string)($listing['land_payment_status'] ?? 'none')) ?> buyer pay</span>
      </div>
    </div>

    <div class="grid" style="margin-top:1.25rem;align-items:start;gap:1.25rem">
      <div class="col-7">
        <form method="post" enctype="multipart/form-data" class="stack" data-listing-form>
          <div>
            <label>Title</label>
            <input name="title" required value="<?= h((string)$listing['title']) ?>">
          </div>
          <div class="row">
            <div>
              <label>Property type</label>
              <select name="listing_type" data-listing-type>
                <?php foreach (['plot_for_sale','house_for_sale','house_for_rent','apartment'] as $type): ?>
                  <option value="<?= h($type) ?>" <?= ($listing['listing_type'] ?? '') === $type ? 'selected' : '' ?>><?= h(listing_type_label($type)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div data-furnishing-field>
              <label>Furnishing</label>
              <select name="furnishing_status"><option value="furnished" <?= ($listing['furnishing_status']??'')==='furnished'?'selected':'' ?>>Furnished with everything</option><option value="unfurnished" <?= ($listing['furnishing_status']??'')==='unfurnished'?'selected':'' ?>>Unfurnished</option></select>
            </div>
          </div>
          <div class="location-grid" data-location-picker data-locations-url="<?= APP_BASE_URL ?>/locations-api.php">
            <div><label>Region</label><select name="region_code" data-region required><option value="">Choose region</option><?php foreach($regions as $row):?><option value="<?= h($row['code']) ?>" <?= $listing['region_code']===$row['code']?'selected':'' ?>><?= h($row['name']) ?></option><?php endforeach;?></select></div>
            <div><label>District / council</label><select name="district_code" data-district required><option value="">Choose district</option><?php foreach($districts as $row):?><option value="<?= h($row['code']) ?>" <?= $listing['district_code']===$row['code']?'selected':'' ?>><?= h($row['name']) ?></option><?php endforeach;?></select></div>
            <div><label>Ward</label><select name="ward_code" data-ward required><option value="">Choose ward</option><?php foreach($wards as $row):?><option value="<?= h($row['code']) ?>" <?= $listing['ward_code']===$row['code']?'selected':'' ?>><?= h($row['name']) ?></option><?php endforeach;?></select></div>
          </div>
          <div class="row">
            <div>
              <label>Location</label>
              <input name="location_text" value="<?= h((string)($listing['location_text'] ?? '')) ?>">
            </div>
            <div>
              <label>Size</label>
              <input name="size_text" value="<?= h((string)($listing['size_text'] ?? '')) ?>">
            </div>
          </div>
          <div class="row">
            <div>
              <label>Minimum price / rent (TZS)</label>
              <input name="price_min_tzs" value="<?= h((string)($listing['price_min_tzs'] ?? '')) ?>">
            </div>
            <div>
              <label>Maximum price / rent (TZS)</label>
              <input name="price_max_tzs" value="<?= h((string)($listing['price_max_tzs'] ?? '')) ?>">
            </div>
          </div>
          <div>
            <label>Description</label>
            <textarea name="description" rows="6"><?= h((string)($listing['description'] ?? '')) ?></textarea>
          </div>
          <div>
            <label hidden>Legacy listing package</label>
            <select name="listing_package" hidden disabled>
              <option value="basic" <?= $pkg === 'basic' ? 'selected' : '' ?>>Basic — <?= h(format_tzs((string)listing_package_amount_tzs('basic'))) ?></option>
              <option value="featured" <?= $pkg === 'featured' ? 'selected' : '' ?>>Featured — <?= h(format_tzs((string)listing_package_amount_tzs('featured'))) ?></option>
              <option value="premium" <?= $pkg === 'premium' ? 'selected' : '' ?>>Premium — <?= h(format_tzs((string)listing_package_amount_tzs('premium'))) ?></option>
            </select>
            <p class="sub" style="font-size:.88rem;margin-top:.35rem">Changing package does not auto-update the fee amount — adjust seller fee in Payments below.</p>
          </div>
          <div>
            <label>Replace video walk-around (optional)</label>
            <?php if (trim((string)($listing['video_path'] ?? '')) !== ''): ?>
              <p class="sub" style="margin:0 0 .35rem">Current video on <a href="<?= APP_BASE_URL ?>/preview-listing.php?id=<?= $listingId ?>">preview</a>. Upload to replace.</p>
            <?php endif; ?>
            <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-m4v">
          </div>
          <div style="display:flex;gap:.65rem;flex-wrap:wrap">
            <button class="btn" type="submit">Save listing changes</button>
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/view-listing.php?id=<?= $listingId ?>">View full detail</a>
            <a class="btn ghost" href="<?= APP_BASE_URL ?>/admin/listings.php">Queue</a>
          </div>
        </form>
      </div>

      <div class="col-5">
        <div class="card pad" style="background:var(--bg2);margin:0">
          <div class="kicker">Submitted snapshot</div>
          <ul class="sub" style="margin:.5rem 0 0;padding-left:1.1rem;line-height:1.75;font-size:.92rem">
            <li>Owner: <?= h((string)($listing['owner_name'] ?? '-')) ?></li>
            <?php if (trim((string)($listing['owner_phone'] ?? '')) !== ''): ?>
              <li>Phone: <?= h((string)$listing['owner_phone']) ?></li>
            <?php endif; ?>
            <?php if (trim((string)($listing['owner_email'] ?? '')) !== ''): ?>
              <li>Email: <?= h((string)$listing['owner_email']) ?></li>
            <?php endif; ?>
            <li>Badge: <?= h((string)($listing['verification_badge'] ?? '-')) ?></li>
            <li>Submitted: <?= h((string)($listing['created_at'] ?? '')) ?></li>
            <li>Private docs: <?= $docCount ?> file<?= $docCount === 1 ? '' : 's' ?></li>
          </ul>
          <?php if ($images): ?>
            <p class="kicker" style="margin:1rem 0 .5rem">Photos (<?= count($images) ?>)</p>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem">
              <?php foreach ($images as $img): ?>
                <a href="<?= APP_BASE_URL ?>/<?= h((string)$img['file_path']) ?>" target="_blank" rel="noreferrer">
                  <img src="<?= APP_BASE_URL ?>/<?= h((string)$img['file_path']) ?>" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;border:1px solid var(--line)">
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="sub" style="margin:.75rem 0 0">No photos uploaded.</p>
          <?php endif; ?>
          <?php if ($docCount > 0): ?>
            <p class="sub" style="margin:.75rem 0 0"><a href="<?= APP_BASE_URL ?>/admin/view-listing.php?id=<?= $listingId ?>">View verification documents</a> on the detail page.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php require __DIR__ . '/_admin-payment-panel.php'; ?>
<?php
$content = ob_get_clean();
$title = 'Edit listing #' . $listingId;
require __DIR__ . '/../_layout.php';
