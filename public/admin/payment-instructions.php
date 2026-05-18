<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'create_category') {
    $title = trim((string)($_POST['title'] ?? ''));
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $publish = isset($_POST['is_published']) ? 1 : 0;
    if ($title === '') {
      flash_set('err', 'Category title is required.');
      redirect('/admin/payment-instructions.php');
    }
    $maxOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM payment_categories')->fetchColumn();
    $st = db()->prepare('INSERT INTO payment_categories (title, subtitle, is_published, sort_order, created_by_user_id) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$title, $subtitle !== '' ? $subtitle : null, $publish, $maxOrder + 1, (int)$u['id']]);
    flash_set('ok', 'Payment category created.');
    redirect('/admin/payment-instructions.php?cat=' . (int)db()->lastInsertId());
  }

  if ($action === 'update_category') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $publish = isset($_POST['is_published']) ? 1 : 0;
    if ($id <= 0 || $title === '') {
      flash_set('err', 'Title is required.');
      redirect('/admin/payment-instructions.php');
    }
    $st = db()->prepare('UPDATE payment_categories SET title = ?, subtitle = ?, is_published = ? WHERE id = ?');
    $st->execute([$title, $subtitle !== '' ? $subtitle : null, $publish, $id]);
    flash_set('ok', 'Category updated.');
    redirect('/admin/payment-instructions.php?cat=' . $id);
  }

  if ($action === 'delete_category') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      $st = db()->prepare('DELETE FROM payment_categories WHERE id = ?');
      $st->execute([$id]);
      flash_set('ok', 'Category deleted.');
    }
    redirect('/admin/payment-instructions.php');
  }

  if ($action === 'add_step') {
    $catId = (int)($_POST['category_id'] ?? 0);
    $body = trim((string)($_POST['body'] ?? ''));
    if ($catId <= 0 || $body === '') {
      flash_set('err', 'Step text is required.');
      redirect('/admin/payment-instructions.php?cat=' . $catId);
    }
    $maxOrder = (int)db()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM payment_steps WHERE category_id = ?')->execute([$catId]) ? 0 : 0;
    $q = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) AS mx FROM payment_steps WHERE category_id = ?');
    $q->execute([$catId]);
    $mx = (int)$q->fetchColumn();
    $st = db()->prepare('INSERT INTO payment_steps (category_id, body, sort_order) VALUES (?, ?, ?)');
    $st->execute([$catId, $body, $mx + 1]);
    flash_set('ok', 'Step added.');
    redirect('/admin/payment-instructions.php?cat=' . $catId);
  }

  if ($action === 'delete_step') {
    $stepId = (int)($_POST['step_id'] ?? 0);
    $catId  = (int)($_POST['category_id'] ?? 0);
    if ($stepId > 0) {
      $st = db()->prepare('DELETE FROM payment_steps WHERE id = ?');
      $st->execute([$stepId]);
      flash_set('ok', 'Step removed.');
    }
    redirect('/admin/payment-instructions.php?cat=' . $catId);
  }

  if ($action === 'move_step') {
    $stepId = (int)($_POST['step_id'] ?? 0);
    $catId  = (int)($_POST['category_id'] ?? 0);
    $dir    = (string)($_POST['dir'] ?? 'up');
    if ($stepId > 0 && $catId > 0) {
      $q = db()->prepare('SELECT id, sort_order FROM payment_steps WHERE category_id = ? ORDER BY sort_order ASC, id ASC');
      $q->execute([$catId]);
      $all = $q->fetchAll();
      $idx = -1;
      foreach ($all as $i => $row) {
        if ((int)$row['id'] === $stepId) { $idx = $i; break; }
      }
      if ($idx !== -1) {
        $swap = ($dir === 'down') ? ($idx + 1) : ($idx - 1);
        if (isset($all[$swap])) {
          $a = $all[$idx]; $b = $all[$swap];
          $u1 = db()->prepare('UPDATE payment_steps SET sort_order = ? WHERE id = ?');
          $u1->execute([(int)$b['sort_order'], (int)$a['id']]);
          $u1->execute([(int)$a['sort_order'], (int)$b['id']]);
        }
      }
    }
    redirect('/admin/payment-instructions.php?cat=' . $catId);
  }

  redirect('/admin/payment-instructions.php');
}

$cats = db()->query('SELECT id, title, subtitle, is_published, sort_order FROM payment_categories ORDER BY sort_order ASC, id ASC')->fetchAll();

$activeId = (int)($_GET['cat'] ?? 0);
if ($activeId <= 0 && $cats) { $activeId = (int)$cats[0]['id']; }

$active = null;
$steps = [];
if ($activeId > 0) {
  $st = db()->prepare('SELECT id, title, subtitle, is_published FROM payment_categories WHERE id = ?');
  $st->execute([$activeId]);
  $active = $st->fetch() ?: null;
  if ($active) {
    $st = db()->prepare('SELECT id, body, sort_order FROM payment_steps WHERE category_id = ? ORDER BY sort_order ASC, id ASC');
    $st->execute([$activeId]);
    $steps = $st->fetchAll();
  } else {
    $activeId = 0;
  }
}

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem">
    <div class="grid" style="align-items:end">
      <div class="col-7">
        <div class="kicker">Admin</div>
        <h1 style="margin:.25rem 0 .35rem">Payment instructions</h1>
        <div class="sub">Edit the public <strong>Payment guide</strong> (mobile money, card, payment code, security tips, troubleshooting). Add categories and steps in order. Tick <strong>Publish</strong> to show each category on the site.</div>
      </div>
      <div class="col-5" style="display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/listings.php">Listings</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/users.php">Users</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/enquiries.php">Enquiries</a>
        <a class="btn" href="<?= APP_BASE_URL ?>/payment-instructions.php" target="_blank" rel="noreferrer">Public guide</a>
      </div>
    </div>
  </div>

  <div class="grid reveal" style="align-items:start">
    <div class="col-5">
      <div class="card pad" style="margin-bottom:1rem">
        <div class="kicker">New category</div>
        <h2 style="margin:.25rem 0 .55rem;font-size:1.35rem">Add a payment category</h2>
        <form method="post" class="stack">
          <input type="hidden" name="action" value="create_category">
          <div>
            <label>Title</label>
            <input name="title" required placeholder="Pay with mobile money">
          </div>
          <div>
            <label>Subtitle (optional)</label>
            <input name="subtitle" placeholder="Airtel, M-Pesa, Mixx, Halotel — secure online checkout">
          </div>
          <label style="display:flex;align-items:center;gap:.5rem;letter-spacing:0;font-size:.9rem;text-transform:none">
            <input type="checkbox" name="is_published" value="1" style="width:auto;margin:0"> Publish immediately
          </label>
          <button class="btn" type="submit">Create category</button>
        </form>
      </div>

      <div class="card pad">
        <div class="kicker">All categories</div>
        <h3 style="margin:.25rem 0 .55rem">Pick one to edit</h3>
        <?php if (!$cats): ?>
          <div class="sub">No categories yet. Create one to start.</div>
        <?php else: ?>
          <div class="stack" style="gap:.5rem">
            <?php foreach ($cats as $c): $cid=(int)$c['id']; $isActive=$cid===$activeId; ?>
              <a href="<?= APP_BASE_URL ?>/admin/payment-instructions.php?cat=<?= $cid ?>"
                 class="cat-row <?= $isActive ? 'is-active' : '' ?>">
                <div class="cat-row-title"><?= h((string)$c['title']) ?></div>
                <div class="cat-row-meta">
                  <?php if ((int)$c['is_published'] === 1): ?>
                    <span class="pill ok">published</span>
                  <?php else: ?>
                    <span class="pill neutral">draft</span>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-7">
      <?php if ($active): ?>
        <div class="card pad" style="margin-bottom:1rem">
          <div class="kicker">Editing</div>
          <h2 style="margin:.25rem 0 .55rem"><?= h((string)$active['title']) ?></h2>
          <form method="post" class="stack">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
            <div>
              <label>Title</label>
              <input name="title" value="<?= h((string)$active['title']) ?>" required>
            </div>
            <div>
              <label>Subtitle</label>
              <input name="subtitle" value="<?= h((string)($active['subtitle'] ?? '')) ?>">
            </div>
            <label style="display:flex;align-items:center;gap:.5rem;letter-spacing:0;font-size:.9rem;text-transform:none">
              <input type="checkbox" name="is_published" value="1" <?= (int)$active['is_published'] === 1 ? 'checked' : '' ?> style="width:auto;margin:0">
              Publish (visible on the public payment guide)
            </label>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
              <button class="btn" type="submit">Save changes</button>
              <button class="btn ghost" type="submit" form="delete-cat-form" onclick="return confirm('Delete this category and all its steps?')">Delete category</button>
            </div>
          </form>
          <form id="delete-cat-form" method="post" style="display:none">
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
          </form>
        </div>

        <div class="card pad" style="margin-bottom:1rem">
          <div class="kicker">Steps</div>
          <h3 style="margin:.25rem 0 .55rem">Add a new step</h3>
          <form method="post" class="stack">
            <input type="hidden" name="action" value="add_step">
            <input type="hidden" name="category_id" value="<?= (int)$active['id'] ?>">
            <div>
              <label>Step body</label>
              <textarea name="body" required placeholder="From My listings, tap Pay, then Pay with mobile money..."></textarea>
            </div>
            <button class="btn" type="submit">Add step</button>
          </form>
        </div>

        <div class="card pad">
          <div class="kicker">Order</div>
          <h3 style="margin:.25rem 0 .55rem">Current steps (<?= count($steps) ?>)</h3>
          <?php if (!$steps): ?>
            <div class="sub">No steps yet. Add the first one above.</div>
          <?php else: ?>
            <ol class="step-list">
              <?php foreach ($steps as $i => $s): $sid=(int)$s['id']; ?>
                <li class="step-row">
                  <div class="step-body"><?= nl2br(h((string)$s['body'])) ?></div>
                  <div class="step-actions">
                    <form method="post" style="margin:0">
                      <input type="hidden" name="action" value="move_step">
                      <input type="hidden" name="category_id" value="<?= (int)$active['id'] ?>">
                      <input type="hidden" name="step_id" value="<?= $sid ?>">
                      <input type="hidden" name="dir" value="up">
                      <button type="submit" class="btn ghost step-btn" <?= $i === 0 ? 'disabled' : '' ?>>Up</button>
                    </form>
                    <form method="post" style="margin:0">
                      <input type="hidden" name="action" value="move_step">
                      <input type="hidden" name="category_id" value="<?= (int)$active['id'] ?>">
                      <input type="hidden" name="step_id" value="<?= $sid ?>">
                      <input type="hidden" name="dir" value="down">
                      <button type="submit" class="btn ghost step-btn" <?= $i === count($steps) - 1 ? 'disabled' : '' ?>>Down</button>
                    </form>
                    <form method="post" style="margin:0" onsubmit="return confirm('Remove this step?')">
                      <input type="hidden" name="action" value="delete_step">
                      <input type="hidden" name="category_id" value="<?= (int)$active['id'] ?>">
                      <input type="hidden" name="step_id" value="<?= $sid ?>">
                      <button type="submit" class="btn ghost step-btn step-btn-del">Remove</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="card pad">
          <div class="kicker">Get started</div>
          <h2 style="margin:.25rem 0 .55rem">No category selected</h2>
          <p class="sub">Create your first payment category on the left. Then add steps in the order you want them shown.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <style>
    .cat-row{
      display:flex; align-items:center; justify-content:space-between;
      padding:.7rem .9rem; border:1px solid var(--line); border-radius: 12px;
      text-decoration: none; color: var(--ink); background: #fff;
      transition: border-color .18s ease, background .18s ease, transform .18s ease;
    }
    .cat-row:hover{ border-color: rgba(14,92,74,.25); background: var(--brand-50); transform: translateY(-1px) }
    .cat-row.is-active{ border-color: var(--brand); background: var(--brand-50) }
    .cat-row-title{ font-weight: 600; font-size: .95rem }
    .step-list{ list-style:none; counter-reset: stp; margin:0; padding:0 }
    .step-row{
      counter-increment: stp;
      display:flex; gap:.85rem; align-items:flex-start;
      padding: .85rem 0; border-bottom: 1px solid var(--line);
    }
    .step-row:last-child{ border-bottom: none }
    .step-row:before{
      content: counter(stp);
      flex: 0 0 auto;
      width: 1.85rem; height: 1.85rem; border-radius: 50%;
      background: linear-gradient(135deg, var(--brand), var(--brand-900)); color:#fff;
      display:flex; align-items:center; justify-content:center;
      font-family: var(--font-sans); font-size: .8rem; font-weight: 700;
      margin-top: .15rem;
    }
    .step-body{ flex: 1 1 auto; line-height: 1.65; color: var(--ink-2); white-space: pre-wrap }
    .step-actions{ display:flex; gap:.35rem; flex-wrap: wrap; flex: 0 0 auto }
    .step-btn{ padding:.4rem .7rem; font-size:.78rem; box-shadow:none }
    .step-btn-del{ color:#b42318; border-color: rgba(180,35,24,.30) }
    .step-btn-del:hover{ background: rgba(180,35,24,.06); color: #6b1812 }
  </style>
<?php
$content = ob_get_clean();
$title = 'Admin. Payment instructions';
require __DIR__ . '/../_layout.php';
