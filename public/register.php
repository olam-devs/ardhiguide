<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
if (current_user()) {
  redirect_after_login();
}

$defaultRole = (string)($_GET['role'] ?? 'buyer');
if (!in_array($defaultRole, ['buyer', 'seller', 'agent'], true)) {
  $defaultRole = 'buyer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = (string)($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');
  $fullName = (string)($_POST['full_name'] ?? '');
  $phone = trim((string)($_POST['phone'] ?? ''));
  $role = (string)($_POST['role'] ?? 'buyer');

  $err = register_user($email, $password, $fullName, $phone !== '' ? $phone : null, $role);
  if ($err !== null) {
    flash_set('err', $err);
  } else {
    flash_set('ok', 'Account created. You are logged in.');
    redirect_after_login();
  }
}

ob_start();
?>
  <div class="card pad reveal" style="max-width:560px;margin:0 auto">
    <div class="kicker">Register</div>
    <h1>Create an account</h1>
    <div class="sub">Buyers can save enquiries; sellers and agents can list land after review.</div>

    <form method="post" class="stack" style="margin-top:1rem">
      <div>
        <label>Account type</label>
        <select name="role">
          <option value="buyer" <?= $defaultRole === 'buyer' ? 'selected' : '' ?>>Buyer</option>
          <option value="seller" <?= $defaultRole === 'seller' ? 'selected' : '' ?>>Seller</option>
          <option value="agent" <?= $defaultRole === 'agent' ? 'selected' : '' ?>>Agent</option>
        </select>
      </div>
      <div>
        <label>Full name</label>
        <input name="full_name" required autocomplete="name" placeholder="Your name">
      </div>
      <div>
        <label>Email</label>
        <input name="email" type="email" required autocomplete="email" placeholder="you@example.com">
      </div>
      <div>
        <label>WhatsApp / phone (optional)</label>
        <input name="phone" type="tel" autocomplete="tel" placeholder="+255 700 000 000">
      </div>
      <div>
        <label>Password</label>
        <input name="password" type="password" required autocomplete="new-password" minlength="8" placeholder="At least 8 characters">
      </div>
      <button class="btn" type="submit" style="width:100%">Create account</button>
    </form>

    <p class="sub" style="margin-top:1rem;text-align:center">
      Already have an account? <a href="<?= APP_BASE_URL ?>/login.php" style="color:var(--earth);font-weight:800">Log in</a>
    </p>
  </div>
<?php
$content = ob_get_clean();
$title = 'Register. Ardhi Guide';
require __DIR__ . '/_layout.php';
