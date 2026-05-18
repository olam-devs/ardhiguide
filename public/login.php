<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
$next = trim((string)($_GET['next'] ?? ''));
if ($next !== '' && str_starts_with($next, '/') && !str_contains($next, '//')) {
  $_SESSION['login_redirect'] = $next;
}
if (current_user()) {
  redirect_after_login();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifier = trim((string)($_POST['identifier'] ?? ($_POST['email'] ?? '')));
  $password = (string)($_POST['password'] ?? '');

  if ($identifier === '' || $password === '') {
    flash_set('err', 'Email or phone, and password, are required.');
  } else if (!login($identifier, $password)) {
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
    <div class="sub">Sign in with your phone number or email. Use admin to review listings, or seller to submit new listings.</div>

    <form method="post" class="stack" style="margin-top:1rem">
      <div>
        <label>Email or phone</label>
        <input name="identifier" type="text" placeholder="e.g. 0712 345 678 or you@example.com" required autocomplete="username" inputmode="text">
      </div>
      <div>
        <label>Password</label>
        <div class="pwd">
          <input name="password" type="password" placeholder="Enter password" required autocomplete="current-password">
          <button type="button" class="pwd-toggle" aria-label="Show password" aria-pressed="false">
            <svg class="pwd-eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="pwd-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12A3 3 0 1 1 9.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
      <button class="btn" type="submit" style="width:100%">Login</button>
    </form>

    <p class="sub" style="margin-top:1rem;text-align:center">
      No account? <a href="<?= APP_BASE_URL ?>/register.php" style="color:var(--earth);font-weight:800">Create one</a>
    </p>

    <div class="forgot-note">
      <h4>Forgot your password?</h4>
      <p>Until self-service password reset is enabled, please contact our admin team. Send your registered phone number (or email) and we will reset it for you.</p>
      <div class="contact-buttons" style="justify-content:flex-start">
        <a class="contact-btn wa" href="https://wa.me/255657925368?text=Hello%20Ardhi%20Guide%2C%20I%20forgot%20my%20password.%20My%20phone%20%2F%20email%3A%20" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163A11.867 11.867 0 0 1 .096 11.86C.099 5.334 5.43.003 11.954.003a11.815 11.815 0 0 1 8.413 3.488 11.821 11.821 0 0 1 3.48 8.414c-.003 6.526-5.335 11.857-11.86 11.857a11.9 11.9 0 0 1-5.674-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>
          WhatsApp admin
        </a>
        <a class="contact-btn phone" href="tel:+255657925368">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92V21a1 1 0 0 1-1.11 1A19 19 0 0 1 2 4.11 1 1 0 0 1 3 3h4.09a1 1 0 0 1 1 .75l1 4a1 1 0 0 1-.29 1L7 10.5a16 16 0 0 0 6.5 6.5l1.75-1.8a1 1 0 0 1 1-.29l4 1a1 1 0 0 1 .75 1z"/></svg>
          +255 657 925 368
        </a>
      </div>
    </div>

    <div class="card pad" style="margin-top:1rem;background:var(--bg2);border-radius:16px">
      <div class="sub" style="font-size:.92rem">
        <div><strong>Admin:</strong> admin@ardhiguide.local <em>or</em> 255657925368 / Admin123!</div>
        <div><strong>Seller:</strong> seller@ardhiguide.local <em>or</em> 255700000001 / Seller123!</div>
      </div>
    </div>
  </div>
<?php
$content = ob_get_clean();
$title = 'Login. Ardhi Guide';
require __DIR__ . '/_layout.php';

