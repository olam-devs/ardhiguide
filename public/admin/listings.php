<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $action = (string)($_POST['action'] ?? '');
  $badges = ['none', 'identity_verified', 'docs_submitted', 'docs_reviewed', 'survey_confirmed'];
  if ($id > 0 && $action === 'set_badge') {
    $badge = (string)($_POST['badge'] ?? 'none');
    if (in_array($badge, $badges, true)) {
      $st = db()->prepare('UPDATE listings SET verification_badge = ? WHERE id = ?');
      $st->execute([$badge, $id]);
      flash_set('ok', "Listing #$id verification badge updated.");
    }
  } elseif ($id > 0 && in_array($action, ['approve', 'reject', 'review', 'feature_on', 'feature_off'], true)) {
    if ($action === 'approve') {
      if (REQUIRE_PAYMENT_FOR_APPROVAL) {
        $chk = db()->prepare('SELECT payment_status FROM listings WHERE id = ?');
        $chk->execute([$id]);
        $prow = $chk->fetch();
        $ps = (string)($prow['payment_status'] ?? '');
        if ($prow && !in_array($ps, ['paid', 'waived'], true)) {
          flash_set('err', "Listing #$id cannot be approved until payment is marked paid or waived (see listing detail).");
          redirect('/admin/listings.php');
        }
      }
      $st = db()->prepare("UPDATE listings SET verification_status='approved', published_at=COALESCE(published_at, NOW()) WHERE id=?");
      $st->execute([$id]);
      flash_set('ok', "Listing #$id approved.");
    } else if ($action === 'reject') {
      $st = db()->prepare("UPDATE listings SET verification_status='rejected' WHERE id=?");
      $st->execute([$id]);
      flash_set('ok', "Listing #$id rejected.");
    } else if ($action === 'review') {
      $st = db()->prepare("UPDATE listings SET verification_status='under_review' WHERE id=?");
      $st->execute([$id]);
      flash_set('ok', "Listing #$id set to under review.");
    } else if ($action === 'feature_on') {
      $st = db()->prepare('UPDATE listings SET is_featured=1 WHERE id=?');
      $st->execute([$id]);
      flash_set('ok', "Listing #$id featured.");
    } else if ($action === 'feature_off') {
      $st = db()->prepare('UPDATE listings SET is_featured=0 WHERE id=?');
      $st->execute([$id]);
      flash_set('ok', "Listing #$id unfeatured.");
    }
  }
  redirect('/admin/listings.php');
}

$stmt = db()->query("SELECT l.id,l.title,l.region,l.category,l.verification_status,l.verification_badge,l.is_featured,l.listing_package,l.payment_status,l.payment_amount_tzs,l.created_at,u.email AS owner_email,u.phone AS owner_phone,u.full_name AS owner_name
                     FROM listings l
                     LEFT JOIN users u ON u.id = l.created_by_user_id
                     ORDER BY FIELD(l.verification_status,'submitted','under_review','approved','rejected'), l.id DESC
                     LIMIT 200");
$rows = $stmt->fetchAll();

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem">
    <div class="grid" style="align-items:end">
      <div class="col-7">
        <div class="kicker">Admin</div>
        <h1 style="margin-bottom:.35rem">Listings review queue</h1>
        <div class="sub">Approve listings to publish them. Use “under review” while your ops team checks documents.</div>
      </div>
      <div class="col-5" style="display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/users.php">Users</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/payment-instructions.php">Payment instructions</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/enquiries.php">Enquiries</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/submit-listing.php">Submit listing</a>
        <a class="btn" href="<?= APP_BASE_URL ?>/index.php">Public browse</a>
      </div>
    </div>
  </div>

  <div class="card pad reveal" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse;min-width:1040px">
      <thead>
        <tr style="background:var(--bg2);text-align:left">
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">ID</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Listing</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Region</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Owner</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Status</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Featured</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Pay</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Badge</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= (int)$r['id'] ?></td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <div style="font-weight:900"><?= h((string)$r['title']) ?></div>
              <div class="sub" style="font-size:.9rem"><?= h((string)$r['category']) ?></div>
            </td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= h((string)$r['region']) ?></td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <?php $oe = trim((string)($r['owner_email'] ?? '')); $op = trim((string)($r['owner_phone'] ?? '')); ?>
              <?php if ($oe !== ''): ?><div style="word-break:break-all"><?= h($oe) ?></div><?php endif; ?>
              <?php if ($op !== ''): ?><div class="sub" style="font-size:.85rem"><?= h($op) ?></div><?php endif; ?>
              <?php if ($oe === '' && $op === ''): ?>-<?php endif; ?>
            </td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <?php $st=(string)$r['verification_status']; ?>
              <?php if ($st === 'approved'): ?>
                <span class="pill ok">approved</span>
              <?php elseif ($st === 'under_review'): ?>
                <span class="pill warn">under review</span>
              <?php elseif ($st === 'rejected'): ?>
                <span class="pill neutral">rejected</span>
              <?php else: ?>
                <span class="pill neutral">submitted</span>
              <?php endif; ?>
            </td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= (int)$r['is_featured'] ? 'Yes' : 'No' ?></td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <?php $ps = (string)($r['payment_status'] ?? 'pending'); ?>
              <span class="pill <?= $ps === 'paid' ? 'ok' : ($ps === 'waived' ? 'neutral' : 'warn') ?>"><?= h($ps) ?></span>
              <div class="sub" style="font-size:.8rem;margin-top:.2rem"><?= h((string)($r['listing_package'] ?? 'basic')) ?> · <?= h(format_tzs((string)($r['payment_amount_tzs'] ?? '0'))) ?></div>
            </td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <form method="post" style="display:flex;gap:.4rem;align-items:center;margin:0;flex-wrap:wrap">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="set_badge">
                <select name="badge" style="max-width:200px;padding:.5rem .6rem;font-size:.85rem">
                  <?php
                  $cur = (string)($r['verification_badge'] ?? 'none');
                  foreach (['none' => 'None', 'identity_verified' => 'Identity', 'docs_submitted' => 'Docs submitted', 'docs_reviewed' => 'Docs reviewed', 'survey_confirmed' => 'Survey'] as $bv => $bl): ?>
                    <option value="<?= h($bv) ?>" <?= $cur === $bv ? 'selected' : '' ?>><?= h($bl) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn secondary" style="padding:.5rem .75rem" type="submit">Save</button>
              </form>
            </td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <div style="display:flex;gap:.45rem;flex-wrap:wrap;align-items:center">
                <a class="btn secondary" style="padding:.55rem .9rem" href="<?= APP_BASE_URL ?>/admin/view-listing.php?id=<?= (int)$r['id'] ?>">Detail</a>
                <a class="btn secondary" style="padding:.55rem .9rem" href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noreferrer">Public</a>
                <form method="post" style="display:flex;gap:.45rem;flex-wrap:wrap;align-items:center;margin:0">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn" style="padding:.55rem .9rem" name="action" value="approve" type="submit">Approve</button>
                  <button class="btn secondary" style="padding:.55rem .9rem" name="action" value="review" type="submit">Review</button>
                  <button class="btn secondary" style="padding:.55rem .9rem" name="action" value="reject" type="submit">Reject</button>
                  <?php if ((int)$r['is_featured']): ?>
                    <button class="btn secondary" style="padding:.55rem .9rem" name="action" value="feature_off" type="submit">Unfeature</button>
                  <?php else: ?>
                    <button class="btn secondary" style="padding:.55rem .9rem" name="action" value="feature_on" type="submit">Feature</button>
                  <?php endif; ?>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php
$content = ob_get_clean();
$title = 'Admin. Listings';
require __DIR__ . '/../_layout.php';

