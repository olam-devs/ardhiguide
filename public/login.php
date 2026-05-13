<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
if (current_user()) {
  redirect_after_login();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($email === '' || $password === '') {
    flash_set('err', 'Email and password are required.');
  } else if (!login($email, $password)) {
    flash_set('err', 'Invalid credentials.');
  } else {
    redirect_after_login();
  }
}

ob_start();
?>
  <div class="card pad reveal" style="max-width:560px;margin:0 auto">
    <div class="kicker">Login</div>
    <h1>Access your dashboard</h1>
    <div class="sub">Use admin to review listings, or seller to submit new listings.</div>

    <form method="post" class="stack" style="margin-top:1rem">
      <div>
        <label>Email</label>
        <input name="email" type="email" placeholder="admin@ardhiguide.local" required>
      </div>
      <div>
        <label>Password</label>
        <input name="password" type="password" placeholder="Enter password" required>
      </div>
      <button class="btn" type="submit" style="width:100%">Login</button>
    </form>

    <p class="sub" style="margin-top:1rem;text-align:center">
      No account? <a href="<?= APP_BASE_URL ?>/register.php" style="color:var(--earth);font-weight:800">Create one</a>
    </p>

    <div class="card pad" style="margin-top:1rem;background:var(--bg2);border-radius:16px">
      <div class="sub" style="font-size:.92rem">
        <div><strong>Admin:</strong> admin@ardhiguide.local / Admin123!</div>
        <div><strong>Seller:</strong> seller@ardhiguide.local / Seller123!</div>
      </div>
    </div>
  </div>
<?php
$content = ob_get_clean();
$title = 'Login. Ardhi Guide';
require __DIR__ . '/_layout.php';

