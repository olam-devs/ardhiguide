<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$u = require_role('admin');
$listingId = (int)($_GET['id'] ?? 0);
if ($listingId <= 0) {
  flash_set('err', 'Invalid listing.');
  redirect('/admin/listings.php');
}

$st = db()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
$st->execute([$listingId]);
$listing = $st->fetch();
if (!$listing) {
  flash_set('err', 'Listing not found.');
  redirect('/admin/listings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $err = listing_admin_update($listingId, $_POST);
  if ($err !== null) {
    flash_set('err', $err);
    redirect('/admin/edit-listing.php?id=' . $listingId);
  }

  if (!empty($_FILES['video']) && (int)($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $vRes = listing_video_validate_and_save($listingId, [
      'name' => (string)($_FILES['video']['name'] ?? ''),
      'type' => (string)($_FILES['video']['type'] ?? ''),
      'tmp_name' => (string)($_FILES['video']['tmp_name'] ?? ''),
      'error' => (int)($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE),
      'size' => (int)($_FILES['video']['size'] ?? 0),
    ]);
    if (!empty($vRes['ok'])) {
      listing_video_delete((string)($listing['video_path'] ?? ''));
      $vUp = db()->prepare('UPDATE listings SET video_path = ?, video_mime = ?, video_size_bytes = ? WHERE id = ?');
      $vUp->execute([$vRes['rel_path'], $vRes['mime'], $vRes['size'], $listingId]);
    } else {
      flash_set('err', 'Saved, but video upload failed: ' . ($vRes['err'] ?? 'unknown'));
      redirect('/admin/view-listing.php?id=' . $listingId);
    }
  }

  flash_set('ok', 'Listing #' . $listingId . ' updated.');
  redirect('/admin/view-listing.php?id=' . $listingId);
}

ob_start();
?>
  <div class="card pad reveal" style="max-width:920px;margin:0 auto">
    <div class="kicker">Admin · Edit listing #<?= $listingId ?></div>
    <h1>Edit listing</h1>
    <p class="sub">Update text fields or replace the walk-around video. Payment and approval stay on the listing detail page.</p>

    <form method="post" enctype="multipart/form-data" class="stack" style="margin-top:1rem">
      <div>
        <label>Title</label>
        <input name="title" required value="<?= h((string)$listing['title']) ?>">
      </div>
      <div class="row">
        <div>
          <label>Category</label>
          <select name="category">
            <?php foreach (['residential','agricultural','commercial','industrial','other'] as $cat): ?>
              <option value="<?= h($cat) ?>" <?= ($listing['category'] ?? '') === $cat ? 'selected' : '' ?>><?= h(ucfirst($cat)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Region</label>
          <input name="region" required value="<?= h((string)$listing['region']) ?>">
        </div>
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
          <label>Price (TZS)</label>
          <input name="price_tzs" value="<?= h((string)($listing['price_tzs'] ?? '')) ?>">
        </div>
        <div>
          <label>WhatsApp contact</label>
          <input name="contact_whatsapp" value="<?= h((string)($listing['contact_whatsapp'] ?? '')) ?>">
        </div>
      </div>
      <div>
        <label>Description</label>
        <textarea name="description"><?= h((string)($listing['description'] ?? '')) ?></textarea>
      </div>
      <?php if (trim((string)($listing['video_path'] ?? '')) !== ''): ?>
        <p class="sub">Current video is on the <a href="<?= APP_BASE_URL ?>/preview-listing.php?id=<?= $listingId ?>">preview page</a>. Upload a new file to replace it.</p>
      <?php endif; ?>
      <div>
        <label>Replace video (optional)</label>
        <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-m4v">
      </div>
      <div style="display:flex;gap:.65rem;flex-wrap:wrap">
        <button class="btn" type="submit">Save changes</button>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/view-listing.php?id=<?= $listingId ?>">Cancel</a>
      </div>
    </form>
  </div>
<?php
$content = ob_get_clean();
$title = 'Edit listing #' . $listingId;
require __DIR__ . '/../_layout.php';
