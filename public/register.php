<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
if (current_user()) {
  redirect_after_login();
}

$defaultRole = (string)($_POST['role'] ?? $_GET['role'] ?? 'buyer');
if (!in_array($defaultRole, ['buyer', 'seller', 'agent', 'expert'], true)) {
  $defaultRole = 'buyer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $passport = store_private_user_upload([
    'name' => (string)($_FILES['passport_photo']['name'] ?? ''),
    'tmp_name' => (string)($_FILES['passport_photo']['tmp_name'] ?? ''),
    'error' => (int)($_FILES['passport_photo']['error'] ?? UPLOAD_ERR_NO_FILE),
    'size' => (int)($_FILES['passport_photo']['size'] ?? 0),
  ], true);
  $err = !($passport['ok'] ?? false)
    ? (string)($passport['err'] ?? 'Upload your passport photo.')
    : register_user(array_map(static fn($v) => is_string($v) ? $v : '', $_POST), $passport);
  if ($err !== null) {
    if (!empty($passport['ok'])) discard_private_upload($passport);
    flash_set('err', $err);
  } else {
    $role = (string)($_POST['role'] ?? 'buyer');
    flash_set('ok', $role === 'buyer'
      ? 'Account created. You can start browsing immediately.'
      : 'Account created. Our team will review your verification details.');
    redirect_after_login();
  }
}

$regions = location_regions();

ob_start();
?>
  <div class="card pad reveal" style="max-width:760px;margin:0 auto">
    <div class="kicker">Register</div>
    <h1>Create an account</h1>
    <div class="sub">Create a profile for guided property support. Property seekers can browse immediately; sellers, agents, and experts are reviewed by our team.</div>

    <form method="post" enctype="multipart/form-data" class="stack" style="margin-top:1rem" data-role-form>
      <div>
        <label>Account type</label>
        <select name="role" data-role-select>
          <option value="buyer" <?= $defaultRole === 'buyer' ? 'selected' : '' ?>>Property seeker (buy, rent, or invest)</option>
          <option value="seller" <?= $defaultRole === 'seller' ? 'selected' : '' ?>>Seller</option>
          <option value="agent" <?= $defaultRole === 'agent' ? 'selected' : '' ?>>Agent</option>
          <option value="expert" <?= $defaultRole === 'expert' ? 'selected' : '' ?>>Expert</option>
        </select>
      </div>
      <div>
        <label>Full name</label>
        <input name="full_name" value="<?= h((string)($_POST['full_name'] ?? '')) ?>" required autocomplete="name" placeholder="Your full legal name">
      </div>
      <div>
        <label>WhatsApp / phone</label>
        <input name="phone" value="<?= h((string)($_POST['phone'] ?? '')) ?>" type="tel" required autocomplete="tel" placeholder="0712 345 678 or +255 712 345 678">
        <div class="sub" style="font-size:.85rem;margin-top:.3rem">Required. You will use this to log in.</div>
      </div>
      <div>
        <label>Email <span style="color:var(--muted);font-weight:500">(optional)</span></label>
        <input name="email" value="<?= h((string)($_POST['email'] ?? '')) ?>" type="email" autocomplete="email" placeholder="you@example.com">
      </div>
      <div class="row">
        <div>
          <label>NIDA number</label>
          <input name="nida_number" value="<?= h((string)($_POST['nida_number'] ?? '')) ?>" required maxlength="40" placeholder="National ID number">
        </div>
        <div>
          <label>Passport photo</label>
          <input name="passport_photo" type="file" required accept="image/jpeg,image/png,image/webp">
          <div class="sub" style="font-size:.85rem;margin-top:.3rem">Private. JPG, PNG, or WebP up to 5 MB.</div>
        </div>
      </div>
      <div data-role-section="agent">
        <label>Agent profile type</label>
        <select name="account_type" data-account-type>
          <option value="individual">Individual agent</option>
          <option value="company" <?= (string)($_POST['account_type'] ?? '') === 'company' ? 'selected' : '' ?>>Company agent</option>
        </select>
      </div>
      <div class="stack" data-company-fields>
        <div><label>Registered company name</label><input name="company_name" value="<?= h((string)($_POST['company_name'] ?? '')) ?>"></div>
        <div class="row">
          <div><label>BRELA registration number</label><input name="brela_number" value="<?= h((string)($_POST['brela_number'] ?? '')) ?>"></div>
          <div><label>TIN</label><input name="tin_number" value="<?= h((string)($_POST['tin_number'] ?? '')) ?>"></div>
        </div>
      </div>
      <div class="stack" data-role-section="expert">
        <div>
          <label>Profession</label>
          <select name="expert_type">
            <option value="">Choose profession</option>
            <option value="surveyor">Surveyor</option>
            <option value="valuer">Valuer</option>
            <option value="town_planner">Town planner</option>
            <option value="advocate">Advocate</option>
          </select>
        </div>
        <div class="row" data-location-picker data-locations-url="<?= APP_BASE_URL ?>/locations-api.php">
          <div>
            <label>Region</label>
            <select name="region_code" data-region>
              <option value="">Choose region</option>
              <?php foreach ($regions as $region): ?><option value="<?= h($region['code']) ?>"><?= h($region['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label>District / council</label><select name="district_code" data-district disabled><option value="">Choose district</option></select></div>
          <div><label>Ward</label><select name="ward_code" data-ward disabled><option value="">Choose ward</option></select></div>
        </div>
      </div>
      <div>
        <label>Physical address</label>
        <textarea name="address_text" required placeholder="Street, neighbourhood, district, and region"><?= h((string)($_POST['address_text'] ?? '')) ?></textarea>
      </div>
      <div>
        <label>Password</label>
        <div class="pwd">
          <input name="password" type="password" required autocomplete="new-password" minlength="8" placeholder="At least 8 characters">
          <button type="button" class="pwd-toggle" aria-label="Show password" aria-pressed="false">
            <svg class="pwd-eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="pwd-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12A3 3 0 1 1 9.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
      <button class="btn" type="submit" style="width:100%">Create account</button>
    </form>

    <p class="sub" style="margin-top:1rem;text-align:center">
      Already have an account? <a href="<?= APP_BASE_URL ?>/login.php" style="color:var(--earth);font-weight:800">Log in</a>
    </p>
  </div>
<?php
$content = ob_get_clean();
$title = 'Register. Ardhi Way';
require __DIR__ . '/_layout.php';
