<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$isAdmin = (($u['role'] ?? '') === 'admin');

if (!user_can_manage_listings($u)) {
  $role = (string)($u['role'] ?? 'buyer');
  flash_set('err', in_array($role, ['seller','agent'], true)
    ? 'Your account must be verified by admin before you can submit listings.'
    : 'Only verified sellers and agents can submit listings.');
  redirect('/my-account.php');
}

function parse_price(?string $p): ?int {
  if ($p === null) return null;
  $digits = preg_replace('/[^\d]/', '', $p);
  if ($digits === '') return null;
  return (int)$digits;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim((string)($_POST['title'] ?? ''));
  $listingType = (string)($_POST['listing_type'] ?? 'plot_for_sale');
  $furnishing = (string)($_POST['furnishing_status'] ?? 'not_applicable');
  $regionCode = trim((string)($_POST['region_code'] ?? ''));
  $districtCode = trim((string)($_POST['district_code'] ?? ''));
  $wardCode = trim((string)($_POST['ward_code'] ?? ''));
  $location = location_names($regionCode, $districtCode, $wardCode);
  $locationText = trim((string)($_POST['location_text'] ?? ''));
  $sizeText = trim((string)($_POST['size_text'] ?? ''));
  $priceMin = parse_price((string)($_POST['price_min_tzs'] ?? ''));
  $priceMax = parse_price((string)($_POST['price_max_tzs'] ?? ''));
  $desc = trim((string)($_POST['description'] ?? ''));
  $publishNow = $isAdmin && isset($_POST['publish_now']);
  $showOnHomepage = $publishNow && isset($_POST['show_on_homepage']);
  $verificationStatus = $publishNow ? 'approved' : 'submitted';
  $publishedAt = $publishNow ? date('Y-m-d H:i:s') : null;
  if (!in_array($listingType, ['plot_for_sale','house_for_sale','house_for_rent','apartment'], true)) $listingType = 'plot_for_sale';
  if (!in_array($furnishing, ['not_applicable','furnished','unfurnished'], true)) $furnishing = 'not_applicable';
  if (!in_array($listingType, ['house_for_rent','apartment'], true)) $furnishing = 'not_applicable';

  if ($title === '' || !$location || $priceMin === null || $priceMax === null || $priceMin > $priceMax) {
    flash_set('err', 'Add a title, valid location, and a valid minimum-to-maximum price range.');
    redirect('/submit-listing.php');
  }

  $ins = db()->prepare("INSERT INTO listings
    (created_by_user_id,title,category,listing_type,furnishing_status,region,district,ward,region_code,district_code,ward_code,
     location_text,size_text,price_tzs,price_min_tzs,price_max_tzs,description,contact_whatsapp,verification_status,verification_badge,payment_status,show_on_homepage,published_at)
    VALUES (?,?,'residential',?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,?,'none','waived',?,?)");
  $ins->execute([
    (int)$u['id'],
    $title,
    $listingType,
    $furnishing,
    $location['region'],
    $location['district'],
    $location['ward'],
    $regionCode,
    $districtCode,
    $wardCode,
    $locationText !== '' ? $locationText : null,
    $sizeText !== '' ? $sizeText : null,
    $priceMin,
    $priceMin,
    $priceMax,
    $desc !== '' ? $desc : null,
    $verificationStatus,
    $showOnHomepage ? 1 : 0,
    $publishedAt,
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

  notify_admin_new_listing($listingId, $title);

  if ($docErr !== null) {
    flash_set('err', 'Listing submitted, but a document failed: ' . $docErr);
  } else {
    flash_set('ok', $isAdmin ? 'Listing submitted.' : 'Listing submitted. It will be reviewed by our team shortly.');
  }
  if ($isAdmin) {
    redirect('/admin/listings.php');
  }
  redirect('/my-listings.php');
}

$regions = location_regions();

ob_start();
?>
  <div class="card pad reveal" style="max-width:920px;margin:0 auto">
    <div class="kicker">Submit listing</div>
    <h1>Create a listing</h1>
    <div class="sub">Provide the basics. Photos help conversions. Listings are reviewed before they appear publicly.</div>

    <form method="post" enctype="multipart/form-data" class="stack" style="margin-top:1rem" data-listing-form>
      <div>
        <label>Title</label>
        <input name="title" placeholder="Residential plot, 600 sqm" required>
      </div>

      <div class="row">
        <div>
          <label>Property type</label>
          <select name="listing_type" data-listing-type>
            <option value="plot_for_sale">Plot for sale</option>
            <option value="house_for_sale">House for sale</option>
            <option value="house_for_rent">House for rent</option>
            <option value="apartment">Apartment</option>
          </select>
        </div>
        <div data-furnishing-field>
          <label>Furnishing</label>
          <select name="furnishing_status">
            <option value="furnished">Furnished with everything</option>
            <option value="unfurnished">Unfurnished</option>
          </select>
        </div>
      </div>

      <div class="location-grid" data-location-picker data-locations-url="<?= APP_BASE_URL ?>/locations-api.php">
        <div><label>Region</label><select name="region_code" data-region required><option value="">Choose region</option><?php foreach ($regions as $region): ?><option value="<?= h($region['code']) ?>"><?= h($region['name']) ?></option><?php endforeach; ?></select></div>
        <div><label>District / council</label><select name="district_code" data-district required disabled><option value="">Choose district</option></select></div>
        <div><label>Ward</label><select name="ward_code" data-ward required disabled><option value="">Choose ward</option></select></div>
      </div>

      <div class="row">
        <div>
          <label>Street / landmark</label>
          <input name="location_text" placeholder="Street, neighbourhood, or nearby landmark">
        </div>
        <div>
          <label>Size</label>
          <input name="size_text" placeholder="600 sqm / 2 acres">
        </div>
      </div>

      <div class="row">
        <div>
          <label>Minimum price / rent (TZS)</label>
          <input name="price_min_tzs" inputmode="numeric" required placeholder="30,000,000">
        </div>
        <div>
          <label>Maximum price / rent (TZS)</label>
          <input name="price_max_tzs" inputmode="numeric" required placeholder="35,000,000">
        </div>
      </div>

      <div>
        <label>Description</label>
        <textarea name="description" placeholder="Documents available, access road, utilities, nearby landmarks..."></textarea>
      </div>

      <?php if ($isAdmin): ?>
        <div class="admin-publish-options">
          <div class="kicker">Admin publishing controls</div>
          <label><input type="checkbox" name="publish_now" value="1"> Approve and publish this property immediately</label>
          <label><input type="checkbox" name="show_on_homepage" value="1"> Feature this property in the curated homepage selection</label>
          <p class="sub">Homepage selection applies only when the listing is published immediately. You can change it later from Admin Listings.</p>
        </div>
      <?php endif; ?>

      <div>
        <label hidden>Legacy listing package</label>
        <select name="listing_package" hidden disabled>
          <option value="basic">Basic. TSh 5,000</option>
          <option value="featured">Featured. TSh 30,000</option>
          <option value="premium">Premium. TSh 100,000</option>
        </select>
        <div class="sub" hidden></div>
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
$title = 'Submit listing. Ardhi Way';
require __DIR__ . '/_layout.php';

