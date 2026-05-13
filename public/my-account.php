<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $current = (string)($_POST['current_password'] ?? '');
  $new     = (string)($_POST['new_password'] ?? '');
  $confirm = (string)($_POST['confirm_password'] ?? '');

  $err = change_password((int)$u['id'], $current, $new, $confirm);
  if ($err !== null) {
    flash_set('err', $err);
    redirect('/my-account.php');
  }
  logout();
  flash_set('ok', 'Password updated. Please log in again with your new password.');
  redirect('/login.php');
}

// Refresh full profile from DB so we display the latest values
$st = db()->prepare('SELECT email, full_name, phone, role, created_at FROM users WHERE id = ? LIMIT 1');
$st->execute([(int)$u['id']]);
$profile = $st->fetch() ?: [];

ob_start();
?>
  <div class="reveal" style="max-width:760px;margin:0 auto;display:grid;gap:1rem">
    <div class="card pad">
      <?php
        $displayId = trim((string)($profile['phone'] ?? ''));
        if ($displayId === '') $displayId = trim((string)($profile['email'] ?? ($u['email'] ?? '')));
      ?>
      <div class="kicker">My account</div>
      <h1 style="margin-bottom:.35rem">Account details</h1>
      <div class="sub">Signed in as <strong><?= h($displayId !== '' ? $displayId : 'User') ?></strong>.</div>

      <div class="row" style="margin-top:1rem">
        <div>
          <label>Full name</label>
          <input type="text" value="<?= h((string)($profile['full_name'] ?? '')) ?>" disabled>
        </div>
        <div>
          <label>Role</label>
          <input type="text" value="<?= h((string)($profile['role'] ?? '')) ?>" disabled>
        </div>
      </div>
      <div class="row" style="margin-top:.65rem">
        <div>
          <label>Phone (login)</label>
          <input type="text" value="<?= h((string)($profile['phone'] ?? '')) ?>" disabled>
        </div>
        <div>
          <label>Email <span style="color:var(--muted);font-weight:500">(optional)</span></label>
          <input type="text" value="<?= h((string)($profile['email'] ?? '')) ?>" placeholder="Not set" disabled>
        </div>
      </div>
      <p class="sub" style="margin-top:.85rem;font-size:.88rem">
        To change your name, phone, or email, please contact the admin team.
      </p>
    </div>

    <div class="card pad">
      <div class="kicker">Security</div>
      <h2 style="margin:.25rem 0 .5rem">Change password</h2>
      <p class="sub">Use a strong password of at least 8 characters. You will be logged out after a successful change.</p>

      <form method="post" class="stack" style="margin-top:1rem" autocomplete="off">
        <div>
          <label>Current password</label>
          <div class="pwd">
            <input name="current_password" type="password" required autocomplete="current-password">
            <button type="button" class="pwd-toggle" aria-label="Show password" aria-pressed="false">
              <svg class="pwd-eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="pwd-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12A3 3 0 1 1 9.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <div class="row">
          <div>
            <label>New password</label>
            <div class="pwd">
              <input name="new_password" type="password" required minlength="8" autocomplete="new-password">
              <button type="button" class="pwd-toggle" aria-label="Show password" aria-pressed="false">
                <svg class="pwd-eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="pwd-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12A3 3 0 1 1 9.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
          <div>
            <label>Confirm new password</label>
            <div class="pwd">
              <input name="confirm_password" type="password" required minlength="8" autocomplete="new-password">
              <button type="button" class="pwd-toggle" aria-label="Show password" aria-pressed="false">
                <svg class="pwd-eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="pwd-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12A3 3 0 1 1 9.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>
        </div>
        <div>
          <button class="btn" type="submit">Update password</button>
        </div>
      </form>
    </div>
  </div>
<?php
$content = ob_get_clean();
$title = 'My account. Ardhi Guide';
require __DIR__ . '/_layout.php';
