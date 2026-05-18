<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'become_seller' && ($u['role'] ?? '') === 'buyer') {
    session_start_safe();
    db()->prepare("UPDATE users SET role = 'seller' WHERE id = ?")->execute([(int)$u['id']]);
    $_SESSION['user']['role'] = 'seller';
    flash_set('ok', 'Your account is now a Seller. You can submit listings and pay publication fees from My listings.');
    redirect('/my-listings.php');
  }

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
$role = (string)($profile['role'] ?? $u['role'] ?? 'buyer');
$uid = (int)$u['id'];
$pubFees = user_pending_publication_fees($uid);
$landPays = user_open_land_payments($uid);

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
      <?php if ($role === 'buyer'): ?>
        <p class="sub" style="margin-top:.75rem;padding:.75rem;background:var(--bg2);border-radius:10px">
          You are a <strong>Buyer</strong>. To list land and see <strong>My listings</strong>, use <strong>Switch to Seller</strong> below.
        </p>
      <?php endif; ?>
      <div style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--line)">
        <div class="kicker">Quick links</div>
        <div style="display:flex;gap:.55rem;flex-wrap:wrap;margin-top:.6rem">
          <a class="btn secondary" href="<?= APP_BASE_URL ?>/my-payments.php">Payments</a>
          <?php if (user_can_manage_listings($u)): ?>
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/my-listings.php">My listings</a>
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/submit-listing.php">Submit listing</a>
          <?php elseif ($role === 'buyer'): ?>
            <form method="post" style="margin:0">
              <input type="hidden" name="action" value="become_seller">
              <button class="btn" type="submit">Switch to Seller (list land)</button>
            </form>
          <?php endif; ?>
          <?php if ($role !== 'admin'): ?>
            <a class="btn ghost" href="<?= APP_BASE_URL ?>/my-enquiries.php">My enquiries</a>
          <?php endif; ?>
          <?php if ($role === 'admin'): ?>
            <a class="btn" href="<?= APP_BASE_URL ?>/admin/listings.php">Admin panel</a>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($pubFees || $landPays): ?>
        <div style="margin-top:1rem;padding:1rem;background:var(--gold-50);border-radius:12px;border:1px solid rgba(165,120,38,.25)">
          <strong>Payment due</strong>
          <ul class="sub" style="margin:.5rem 0 0;padding-left:1.1rem">
            <?php foreach ($pubFees as $row): ?>
              <li><a href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= (int)$row['id'] ?>">Pay publication fee: <?= h((string)$row['title']) ?></a></li>
            <?php endforeach; ?>
            <?php foreach ($landPays as $row): ?>
              <li><a href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= (int)$row['id'] ?>&for=land">Pay for plot: <?= h((string)$row['title']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
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
