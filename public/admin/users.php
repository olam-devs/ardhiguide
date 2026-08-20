<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$u = require_role('admin');
$superSt = db()->prepare('SELECT is_super_admin FROM users WHERE id = ?');
$superSt->execute([(int)$u['id']]);
$isSuperAdmin = (bool)$superSt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $action = (string)($_POST['action'] ?? '');

  if ($id <= 0) {
    flash_set('err', 'Invalid user.');
    redirect('/admin/users.php');
  }

  // Safeguard: admin cannot change their own role or deactivate themselves
  $isSelf = ($id === (int)$u['id']);

  if ($action === 'reset_password') {
    $newPwd = (string)($_POST['new_password'] ?? '');
    $err = admin_reset_password($id, $newPwd);
    if ($err !== null) {
      flash_set('err', $err);
    } else {
      flash_set('ok', "Password reset for user #$id. Share the new password with them securely.");
    }
  } elseif ($action === 'set_role') {
    if ($isSelf) {
      flash_set('err', 'You cannot change your own role.');
    } else {
      $role = (string)($_POST['role'] ?? '');
      $err = $role === 'admin' && !$isSuperAdmin
        ? 'Only a super admin can create another admin.'
        : admin_set_user_role($id, $role);
      if ($err !== null) {
        flash_set('err', $err);
      } else {
        flash_set('ok', "Role updated for user #$id.");
      }
    }
  } elseif ($action === 'set_active') {
    if ($isSelf) {
      flash_set('err', 'You cannot deactivate your own account.');
    } else {
      $active = ((string)($_POST['active'] ?? '0')) === '1';
      admin_set_user_active($id, $active);
      flash_set('ok', $active ? "User #$id activated." : "User #$id deactivated.");
    }
  } else {
    flash_set('err', 'Unknown action.');
  }
  redirect('/admin/users.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$roleFilter = (string)($_GET['role'] ?? '');
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR nida_number LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like, $like, $like); }
if (in_array($roleFilter, ['buyer','seller','agent','expert','admin'], true)) { $where[] = 'role = ?'; $params[] = $roleFilter; }
$stmt = db()->prepare(
  'SELECT id,email,full_name,phone,role,is_active,verification_status,created_at FROM users WHERE ' . implode(' AND ', $where) .
  ' ORDER BY FIELD(role,"admin","expert","agent","seller","buyer"), created_at DESC LIMIT 300'
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem">
    <div class="grid" style="align-items:end">
      <div class="col-7">
        <div class="kicker">Admin</div>
        <h1 style="margin-bottom:.35rem">User management</h1>
        <div class="sub">Reset passwords, change roles, and activate or deactivate accounts. Self-actions are blocked to prevent locking yourself out.</div>
      </div>
      <div class="col-5" style="display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/listings.php">Listings queue</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/enquiries.php">Enquiries</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/payment-instructions.php">Payment instructions</a>
        <a class="btn" href="<?= APP_BASE_URL ?>/index.php">Public browse</a>
      </div>
    </div>
  </div>

  <div class="card pad reveal" style="overflow:auto">
    <form method="get" class="filter-bar" style="margin-bottom:1rem">
      <input name="q" value="<?= h($q) ?>" placeholder="Search name, phone, email, or NIDA">
      <select name="role"><option value="">All roles</option><?php foreach (['buyer','seller','agent','expert','admin'] as $opt): ?><option value="<?= $opt ?>" <?= $roleFilter === $opt ? 'selected' : '' ?>><?= $opt === 'buyer' ? 'Property seeker' : ucfirst($opt) ?></option><?php endforeach; ?></select>
      <button class="btn" type="submit">Search</button>
    </form>
    <table class="tbl">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Role</th>
          <th>Status</th>
          <th>Verification</th>
          <th>Reset password</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $isSelf = ((int)$r['id'] === (int)$u['id']); ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td>
              <div style="font-weight:900"><?= h((string)$r['full_name']) ?><?= $isSelf ? ' <span class="pill ok" style="margin-left:.4rem">you</span>' : '' ?></div>
              <div class="sub" style="font-size:.85rem">Joined <?= h(date('M j, Y', strtotime((string)$r['created_at']))) ?></div>
            </td>
            <td style="word-break:break-all"><?= h((string)$r['email']) ?></td>
            <td><?= h((string)($r['phone'] ?? '-')) ?></td>
            <td>
              <?php if ($isSelf): ?>
                <span class="pill"><?= h((string)$r['role']) ?></span>
              <?php else: ?>
                <form class="small-form" method="post">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="set_role">
                  <select name="role">
                    <?php $roleOptions = ['buyer','seller','agent','expert']; if ($isSuperAdmin || $r['role'] === 'admin') $roleOptions[] = 'admin'; ?>
                    <?php foreach ($roleOptions as $opt): ?>
                      <option value="<?= h($opt) ?>" <?= $r['role'] === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn secondary" type="submit">Save</button>
                </form>
              <?php endif; ?>
            </td>
            <td><a class="pill <?= $r['verification_status'] === 'verified' ? 'ok' : ($r['verification_status'] === 'rejected' ? 'bad' : 'warn') ?>" href="<?= APP_BASE_URL ?>/admin/user.php?id=<?= (int)$r['id'] ?>"><?= h((string)$r['verification_status']) ?> · review</a></td>
            <td>
              <?php if ((int)$r['is_active']): ?>
                <?php if ($isSelf): ?>
                  <span class="pill ok">active</span>
                <?php else: ?>
                  <form class="small-form" method="post" onsubmit="return confirm('Deactivate this user? They will not be able to log in.');">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="set_active">
                    <input type="hidden" name="active" value="0">
                    <span class="pill ok">active</span>
                    <button class="btn secondary" type="submit">Deactivate</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <form class="small-form" method="post">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="set_active">
                  <input type="hidden" name="active" value="1">
                  <span class="pill neutral">disabled</span>
                  <button class="btn" type="submit">Activate</button>
                </form>
              <?php endif; ?>
            </td>
            <td>
              <form class="small-form" method="post" onsubmit="return confirm('Reset password for <?= h((string)$r['email']) ?>?');">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="reset_password">
                <div class="pwd" style="flex:1;min-width:180px">
                  <input name="new_password" type="password" placeholder="New password (8+ chars)" minlength="8" required autocomplete="new-password">
                  <button type="button" class="pwd-toggle" aria-label="Show password" aria-pressed="false">
                    <svg class="pwd-eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="pwd-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12A3 3 0 1 1 9.88 9.88"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                  </button>
                </div>
                <button class="btn" type="submit">Reset</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php
$content = ob_get_clean();
$title = 'Admin. Users';
require __DIR__ . '/../_layout.php';
