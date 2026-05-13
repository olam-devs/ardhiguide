<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();

if (!in_array(($u['role'] ?? ''), ['seller','agent','admin'], true)) {
  flash_set('err', 'Only sellers/agents can submit listings.');
  redirect('/index.php');
}

function normalize_phone(?string $p): ?string {
  if ($p === null) return null;
  $p = trim($p);
  if ($p === '') return null;
  $p = preg_replace('/[^\d\+]/', '', $p);
  return $p;
}

function parse_price(?string $p): ?int {
  if ($p === null) return null;
  $digits = preg_replace('/[^\d]/', '', $p);
  if ($digits === '') return null;
  return (int)$digits;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim((string)($_POST['title'] ?? ''));
  $category = (string)($_POST['category'] ?? 'residential');
  $region = trim((string)($_POST['region'] ?? ''));
  $locationText = trim((string)($_POST['location_text'] ?? ''));
  $sizeText = trim((string)($_POST['size_text'] ?? ''));
  $price = parse_price((string)($_POST['price_tzs'] ?? ''));
  $desc = trim((string)($_POST['description'] ?? ''));
  $wa = normalize_phone((string)($_POST['contact_whatsapp'] ?? ''));
  $listingPackage = listing_package_normalize((string)($_POST['listing_package'] ?? 'basic'));

  if ($title === '' || $region === '') {
    flash_set('err', 'Title and region are required.');
    redirect('/submit-listing.php');
  }

  $ins = db()->prepare("INSERT INTO listings
    (created_by_user_id,title,category,region,location_text,size_text,price_tzs,description,contact_whatsapp,verification_status,verification_badge)
    VALUES (?,?,?,?,?,?,?,?,?,'submitted','docs_submitted')");
  $ins->execute([
    (int)$u['id'],
    $title,
    in_array($category, ['residential','agricultural','commercial','industrial','other'], true) ? $category : 'other',
    $region,
    $locationText !== '' ? $locationText : null,
    $sizeText !== '' ? $sizeText : null,
    $price,
    $desc !== '' ? $desc : null,
    $wa,
  ]);

  $listingId = (int)db()->lastInsertId();

  // Upload images (optional)
  if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $baseDir = __DIR__ . '/uploads';
    if (!is_dir($baseDir)) @mkdir($baseDir, 0777, true);

    $count = min(count($_FILES['photos']['name']), 6);
    for ($i = 0; $i < $count; $i++) {
      $tmp = $_FILES['photos']['tmp_name'][$i] ?? '';
      $err = (int)($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
      if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;

      $type = (string)($_FILES['photos']['type'][$i] ?? '');
      if (!in_array($type, ['image/jpeg','image/png','image/webp'], true)) continue;

      $ext = $type === 'image/png' ? 'png' : ($type === 'image/webp' ? 'webp' : 'jpg');
      $name = 'l' . $listingId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
      $dest = $baseDir . '/' . $name;
      if (@move_uploaded_file($tmp, $dest)) {
        $rel = 'uploads/' . $name;
        $imgIns = db()->prepare("INSERT INTO listing_images (listing_id,file_path) VALUES (?,?)");
        $imgIns->execute([$listingId, $rel]);
      }
    }
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
      $vUp = db()->prepare('UPDATE listings SET video_path = ?, video_mime = ?, video_size_bytes = ? WHERE id = ?');
      $vUp->execute([$vRes['rel_path'], $vRes['mime'], $vRes['size'], $listingId]);
    } else {
      flash_set('err', 'Listing saved, but the video could not be uploaded: ' . ($vRes['err'] ?? 'unknown error'));
    }
  }

  // Private verification documents (optional, max 5 files this request, 10 total)
  $docErr = null;
  if (!empty($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
    $currentDocs = count_listing_documents($listingId);
    $maxTotal = 10;
    $n = min(count($_FILES['documents']['name']), 5);
    for ($i = 0; $i < $n && $currentDocs < $maxTotal; $i++) {
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
        $docErr = $e;
        break;
      }
      $currentDocs++;
    }
  }

  $isAdmin = (($u['role'] ?? '') === 'admin');
  try {
    listing_apply_payment_row($listingId, $listingPackage, $isAdmin);
  } catch (Throwable $e) {
    flash_set('err', 'Listing saved, but payment fields failed. Run database migrations (php scripts/migrate.php) or import migration_004 SQL.');
    if ($isAdmin) {
      redirect('/admin/listings.php');
    }
    redirect('/my-listings.php');
  }

  notify_admin_new_listing($listingId, $title);

  if ($docErr !== null) {
    flash_set('err', 'Listing submitted, but a document failed: ' . $docErr);
  } else {
    flash_set('ok', $isAdmin ? 'Listing submitted.' : 'Listing submitted. Complete the listing fee to speed up review.');
  }
  if ($isAdmin) {
    redirect('/admin/listings.php');
  }
  redirect('/pay-listing.php?id=' . $listingId);
}

ob_start();
?>
  <div class="card pad reveal" style="max-width:920px;margin:0 auto">
    <div class="kicker">Submit listing</div>
    <h1>Create a listing</h1>
    <div class="sub">Provide the basics. Photos help conversions. Listings are reviewed before they appear publicly.</div>

    <form method="post" enctype="multipart/form-data" class="stack" style="margin-top:1rem">
      <div>
        <label>Title</label>
        <input name="title" placeholder="Residential plot, 600 sqm" required>
      </div>

      <div class="row">
        <div>
          <label>Category</label>
          <select name="category">
            <option value="residential">Residential</option>
            <option value="agricultural">Agricultural</option>
            <option value="commercial">Commercial</option>
            <option value="industrial">Industrial</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <label>Region</label>
          <input name="region" placeholder="Dar es Salaam" required>
        </div>
      </div>

      <div class="row">
        <div>
          <label>Location</label>
          <input name="location_text" placeholder="Mikocheni, Dar es Salaam">
        </div>
        <div>
          <label>Size</label>
          <input name="size_text" placeholder="600 sqm / 2 acres">
        </div>
      </div>

      <div class="row">
        <div>
          <label>Price (TZS)</label>
          <input name="price_tzs" placeholder="35,000,000">
        </div>
        <div>
          <label>WhatsApp contact (optional)</label>
          <input name="contact_whatsapp" placeholder="+255 700 000 000">
        </div>
      </div>

      <div>
        <label>Description</label>
        <textarea name="description" placeholder="Documents available, access road, utilities, nearby landmarks..."></textarea>
      </div>

      <div>
        <label>Listing package (fee)</label>
        <select name="listing_package">
          <option value="basic">Basic. TSh 5,000</option>
          <option value="featured">Featured. TSh 30,000</option>
          <option value="premium">Premium. TSh 100,000</option>
        </select>
        <div class="sub" style="font-size:.92rem;margin-top:.35rem">Fee is due before we prioritise review. Follow the published payment guide for full steps; admin will confirm receipt.</div>
      </div>

      <div>
        <label>Photos (up to 6)</label>
        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp">
        <div class="sub" style="font-size:.92rem;margin-top:.35rem">Accepted formats: JPG, PNG, WebP.</div>
      </div>

      <div>
        <label>Short video walk-around (optional)</label>
        <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm,video/x-m4v">
        <div class="sub" style="font-size:.92rem;margin-top:.35rem">MP4, MOV or WEBM. Keep it under <strong>1 minute</strong> and <strong>25 MB</strong>. Buyers love quick walk-arounds.</div>
      </div>

      <div>
        <label>Verification documents (optional, up to 5 files)</label>
        <input type="file" name="documents[]" multiple accept=".pdf,application/pdf,image/jpeg,image/png,image/webp">
        <div class="sub" style="font-size:.92rem;margin-top:.35rem">PDF or images, max 5 MB each. Stored privately; not shown on the public page.</div>
      </div>

      <div style="display:flex;gap:.7rem;flex-wrap:wrap;margin-top:.2rem">
        <button class="btn" type="submit">Submit listing</button>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/index.php">Cancel</a>
      </div>

      <div class="sub" style="font-size:.92rem;margin-top:.3rem">
        “Approval” is an internal status and badge level; it is not a legal ownership guarantee.
      </div>
    </form>
  </div>
<?php
$content = ob_get_clean();
$title = 'Submit listing. Ardhi Guide';
require __DIR__ . '/_layout.php';

