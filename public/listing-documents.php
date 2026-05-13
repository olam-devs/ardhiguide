<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$listingId = (int)($_GET['id'] ?? 0);
if ($listingId <= 0) {
  flash_set('err', 'Invalid listing.');
  redirect('/my-listings.php');
}

$st = db()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
$st->execute([$listingId]);
$listing = $st->fetch();
if (!$listing) {
  flash_set('err', 'Listing not found.');
  if (($u['role'] ?? '') === 'admin') {
    redirect('/admin/listings.php');
  }
  redirect('/my-listings.php');
}

$ownerId = (int)($listing['created_by_user_id'] ?? 0);
$isAdmin = (($u['role'] ?? '') === 'admin');
$isOwner = ($ownerId === (int)$u['id']);
if (!$isAdmin && !$isOwner) {
  http_response_code(403);
  echo 'Forbidden';
  exit;
}

$maxTotal = 10;
$uploadErr = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['documents'])) {
  $names = $_FILES['documents']['name'] ?? [];
  if (is_array($names)) {
    $n = min(count($names), 5);
    $current = count_listing_documents($listingId);
    for ($i = 0; $i < $n && $current < $maxTotal; $i++) {
      $file = [
        'name' => $_FILES['documents']['name'][$i] ?? '',
        'type' => $_FILES['documents']['type'][$i] ?? '',
        'tmp_name' => $_FILES['documents']['tmp_name'][$i] ?? '',
        'error' => (int)($_FILES['documents']['error'][$i] ?? UPLOAD_ERR_NO_FILE),
        'size' => (int)($_FILES['documents']['size'][$i] ?? 0),
      ];
      if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
      }
      $e = save_listing_document_upload($listingId, (int)$u['id'], $file);
      if ($e !== null) {
        $uploadErr = $e;
        break;
      }
      $current++;
    }
  }
  if ($uploadErr === null) {
    flash_set('ok', 'Documents updated.');
  } else {
    flash_set('err', $uploadErr);
  }
  redirect('/listing-documents.php?id=' . $listingId);
}

$docsStmt = db()->prepare(
  'SELECT id, original_name, mime, size_bytes, created_at
   FROM listing_documents
   WHERE listing_id = ?
   ORDER BY id ASC'
);
$docsStmt->execute([$listingId]);
$docs = $docsStmt->fetchAll();

$count = count($docs);
$canUpload = $count < $maxTotal;

ob_start();
?>
  <div class="card pad reveal" style="max-width:820px;margin:0 auto">
    <div class="kicker">Verification documents</div>
    <h1><?= h((string)$listing['title']) ?></h1>
    <div class="sub">Private files (PDF or images). Only you, admins, and download links after login can access them. Not shown on the public listing page.</div>

    <div style="margin-top:1rem;display:flex;gap:.6rem;flex-wrap:wrap">
      <a class="btn secondary" href="<?= APP_BASE_URL ?>/my-listings.php">Back</a>
      <a class="btn secondary" href="<?= APP_BASE_URL ?>/preview-listing.php?id=<?= $listingId ?>">Preview listing</a>
      <?php if ($isAdmin): ?>
        <a class="btn" href="<?= APP_BASE_URL ?>/admin/view-listing.php?id=<?= $listingId ?>">Admin detail</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card pad reveal" style="max-width:820px;margin:1rem auto 0">
    <h2 style="margin:0 0 .8rem;font-size:1.2rem">Uploaded files (<?= $count ?> / <?= $maxTotal ?>)</h2>
    <?php if (!$docs): ?>
      <p class="sub">No documents yet. Add title deeds, sketches, or other evidence for admin review.</p>
    <?php else: ?>
      <ul style="margin:0;padding-left:1.2rem;line-height:1.8">
        <?php foreach ($docs as $d): ?>
          <li>
            <a href="<?= APP_BASE_URL ?>/download-document.php?id=<?= (int)$d['id'] ?>" style="font-weight:800;color:var(--earth)"><?= h((string)$d['original_name']) ?></a>
            <span class="sub" style="font-size:.85rem"> · <?= h((string)$d['created_at']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($canUpload): ?>
      <form method="post" enctype="multipart/form-data" class="stack" style="margin-top:1.2rem">
        <div>
          <label>Add files (up to 5 at once, <?= $maxTotal ?> total)</label>
          <input type="file" name="documents[]" multiple accept=".pdf,image/jpeg,image/png,image/webp,application/pdf">
          <div class="sub" style="font-size:.92rem;margin-top:.35rem">PDF, JPG, PNG, WebP. Max 5 MB each.</div>
        </div>
        <button class="btn" type="submit">Upload</button>
      </form>
    <?php else: ?>
      <p class="sub" style="margin-top:1rem">Maximum number of documents reached. Ask admin to remove old files if needed.</p>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$title = 'Documents. ' . (string)$listing['title'];
require __DIR__ . '/_layout.php';
