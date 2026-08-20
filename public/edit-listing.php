<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
$u = require_auth();
if (!user_can_manage_listings($u)) { flash_set('err', 'Your account must be verified to edit listings.'); redirect('/my-account.php'); }
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM listings WHERE id = ? AND created_by_user_id = ? LIMIT 1');
$st->execute([$id, (int)$u['id']]);
$listing = $st->fetch();
if (!$listing) { http_response_code(404); exit('Listing not found.'); }

$parsePrice = static function(string $value): ?int {
  $digits = preg_replace('/[^\d]/', '', $value) ?? '';
  return $digits === '' ? null : (int)$digits;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = trim((string)($_POST['title'] ?? ''));
  $type = (string)($_POST['listing_type'] ?? 'plot_for_sale');
  $furnishing = (string)($_POST['furnishing_status'] ?? 'not_applicable');
  $regionCode = trim((string)($_POST['region_code'] ?? ''));
  $districtCode = trim((string)($_POST['district_code'] ?? ''));
  $wardCode = trim((string)($_POST['ward_code'] ?? ''));
  $location = location_names($regionCode, $districtCode, $wardCode);
  $min = $parsePrice((string)($_POST['price_min_tzs'] ?? ''));
  $max = $parsePrice((string)($_POST['price_max_tzs'] ?? ''));
  if (!in_array($type, ['plot_for_sale','house_for_sale','house_for_rent','apartment'], true)) $type = 'plot_for_sale';
  if (!in_array($type, ['house_for_rent','apartment'], true)) $furnishing = 'not_applicable';
  if ($title === '' || !$location || $min === null || $max === null || $min > $max) {
    flash_set('err', 'Add a title, valid location, and valid price range.');
    redirect('/edit-listing.php?id=' . $id);
  }
  $up = db()->prepare(
    "UPDATE listings SET title=?,listing_type=?,furnishing_status=?,region=?,district=?,ward=?,region_code=?,district_code=?,ward_code=?,
     location_text=?,size_text=?,price_tzs=?,price_min_tzs=?,price_max_tzs=?,description=?,verification_status='submitted',published_at=NULL WHERE id=?"
  );
  $up->execute([
    $title,$type,$furnishing,$location['region'],$location['district'],$location['ward'],$regionCode,$districtCode,$wardCode,
    trim((string)($_POST['location_text'] ?? '')) ?: null,trim((string)($_POST['size_text'] ?? '')) ?: null,
    $min,$min,$max,trim((string)($_POST['description'] ?? '')) ?: null,$id,
  ]);
  flash_set('ok', 'Changes saved and submitted for admin re-approval.');
  redirect('/my-listings.php');
}

$regions = location_regions();
$districts = !empty($listing['region_code']) ? location_districts((string)$listing['region_code']) : [];
$wards = !empty($listing['district_code']) ? location_wards((string)$listing['region_code'], (string)$listing['district_code']) : [];
ob_start();
?>
<div class="card pad reveal" style="max-width:920px;margin:0 auto">
  <div class="kicker">Edit listing</div><h1><?= h((string)$listing['title']) ?></h1>
  <p class="sub">Saving changes takes the listing offline until an admin approves it again.</p>
  <form method="post" class="stack" data-listing-form>
    <div><label>Title</label><input name="title" required value="<?= h((string)$listing['title']) ?>"></div>
    <div class="row">
      <div><label>Property type</label><select name="listing_type" data-listing-type><?php foreach (['plot_for_sale'=>'Plot for sale','house_for_sale'=>'House for sale','house_for_rent'=>'House for rent','apartment'=>'Apartment'] as $value=>$label): ?><option value="<?= $value ?>" <?= $listing['listing_type'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
      <div data-furnishing-field><label>Furnishing</label><select name="furnishing_status"><option value="furnished" <?= $listing['furnishing_status'] === 'furnished' ? 'selected' : '' ?>>Furnished with everything</option><option value="unfurnished" <?= $listing['furnishing_status'] === 'unfurnished' ? 'selected' : '' ?>>Unfurnished</option></select></div>
    </div>
    <div class="location-grid" data-location-picker data-locations-url="<?= APP_BASE_URL ?>/locations-api.php">
      <div><label>Region</label><select name="region_code" data-region required><option value="">Choose region</option><?php foreach ($regions as $row): ?><option value="<?= h($row['code']) ?>" <?= $listing['region_code'] === $row['code'] ? 'selected' : '' ?>><?= h($row['name']) ?></option><?php endforeach; ?></select></div>
      <div><label>District / council</label><select name="district_code" data-district required><option value="">Choose district</option><?php foreach ($districts as $row): ?><option value="<?= h($row['code']) ?>" <?= $listing['district_code'] === $row['code'] ? 'selected' : '' ?>><?= h($row['name']) ?></option><?php endforeach; ?></select></div>
      <div><label>Ward</label><select name="ward_code" data-ward required><option value="">Choose ward</option><?php foreach ($wards as $row): ?><option value="<?= h($row['code']) ?>" <?= $listing['ward_code'] === $row['code'] ? 'selected' : '' ?>><?= h($row['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="row"><div><label>Street / landmark</label><input name="location_text" value="<?= h((string)$listing['location_text']) ?>"></div><div><label>Size</label><input name="size_text" value="<?= h((string)$listing['size_text']) ?>"></div></div>
    <div class="row"><div><label>Minimum price / rent (TZS)</label><input name="price_min_tzs" required inputmode="numeric" value="<?= h((string)$listing['price_min_tzs']) ?>"></div><div><label>Maximum price / rent (TZS)</label><input name="price_max_tzs" required inputmode="numeric" value="<?= h((string)$listing['price_max_tzs']) ?>"></div></div>
    <div><label>Description</label><textarea name="description"><?= h((string)$listing['description']) ?></textarea></div>
    <div style="display:flex;gap:.7rem;flex-wrap:wrap"><button class="btn" type="submit">Save and request approval</button><a class="btn secondary" href="<?= APP_BASE_URL ?>/my-listings.php">Cancel</a></div>
  </form>
</div>
<?php
$content = ob_get_clean();
$title = 'Edit listing. Ardhi Way';
require __DIR__ . '/_layout.php';
