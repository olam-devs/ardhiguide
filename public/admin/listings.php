<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$u = require_role('admin');

function admin_listings_redirect(string $action, int $id, bool $success, string $message): void {
  if ($success) {
    flash_set('ok', $message);
  } else {
    flash_set('err', $message);
  }
  $qs = 'done=' . rawurlencode($action) . '&id=' . $id . ($success ? '' : '&fail=1');
  redirect('/admin/listings.php?' . $qs);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $action = (string)($_POST['action'] ?? '');
  $badges = ['none', 'identity_verified', 'docs_submitted', 'docs_reviewed', 'survey_confirmed'];

  if ($id <= 0) {
    flash_set('err', 'Invalid listing.');
    redirect('/admin/listings.php');
  }

  if ($action === 'delete_listing') {
    $titleSt = db()->prepare('SELECT title FROM listings WHERE id = ?');
    $titleSt->execute([$id]);
    $trow = $titleSt->fetch();
    $err = listing_admin_delete($id);
    if ($err !== null) {
      flash_set('err', $err);
      redirect('/admin/listings.php');
    }
    flash_set('ok', 'Listing #' . $id . ' deleted' . ($trow ? ': ' . (string)$trow['title'] : '') . '.');
    redirect('/admin/listings.php?done=delete&id=' . $id);
  }

  if ($action === 'set_badge') {
    $badge = (string)($_POST['badge'] ?? 'none');
    if (in_array($badge, $badges, true)) {
      $st = db()->prepare('UPDATE listings SET verification_badge = ? WHERE id = ?');
      $st->execute([$badge, $id]);
      admin_listings_redirect('badge', $id, true, "Listing #$id badge saved.");
    }
    admin_listings_redirect('badge', $id, false, "Listing #$id badge could not be saved.");
  }

  if (in_array($action, ['approve', 'reject', 'review', 'feature_on', 'feature_off', 'home_on', 'home_off', 'mark_taken', 'relist'], true)) {
    if ($action === 'approve') {
      if (REQUIRE_PAYMENT_FOR_APPROVAL) {
        $chk = db()->prepare('SELECT payment_status FROM listings WHERE id = ?');
        $chk->execute([$id]);
        $prow = $chk->fetch();
        $ps = (string)($prow['payment_status'] ?? '');
        if ($prow && !in_array($ps, ['paid', 'waived'], true)) {
          admin_listings_redirect('approve', $id, false, "Listing #$id cannot be approved until payment is paid or waived.");
        }
      }
      $st = db()->prepare("UPDATE listings SET verification_status='approved', published_at=COALESCE(published_at, NOW()) WHERE id=?");
      $st->execute([$id]);
      admin_listings_redirect('approve', $id, true, "Listing #$id approved and published.");
    }
    if ($action === 'reject') {
      $st = db()->prepare("UPDATE listings SET verification_status='rejected' WHERE id=?");
      $st->execute([$id]);
      admin_listings_redirect('reject', $id, true, "Listing #$id rejected.");
    }
    if ($action === 'review') {
      $st = db()->prepare("UPDATE listings SET verification_status='under_review' WHERE id=?");
      $st->execute([$id]);
      admin_listings_redirect('review', $id, true, "Listing #$id marked under review.");
    }
    if ($action === 'feature_on') {
      $st = db()->prepare('UPDATE listings SET is_featured=1 WHERE id=?');
      $st->execute([$id]);
      admin_listings_redirect('feature_on', $id, true, "Listing #$id is now featured.");
    }
    if ($action === 'feature_off') {
      $st = db()->prepare('UPDATE listings SET is_featured=0 WHERE id=?');
      $st->execute([$id]);
      admin_listings_redirect('feature_off', $id, true, "Listing #$id removed from featured.");
    }
    if ($action === 'home_on') {
      $st = db()->prepare("UPDATE listings SET show_on_homepage=1 WHERE id=? AND verification_status='approved'");
      $st->execute([$id]);
      admin_listings_redirect('home_on', $id, $st->rowCount() > 0, $st->rowCount() > 0 ? "Listing #$id now appears on the homepage." : "Approve listing #$id before adding it to the homepage.");
    }
    if ($action === 'home_off') {
      db()->prepare('UPDATE listings SET show_on_homepage=0 WHERE id=?')->execute([$id]);
      admin_listings_redirect('home_off', $id, true, "Listing #$id removed from the homepage.");
    }
    if ($action === 'mark_taken') {
      db()->prepare('UPDATE listings SET is_taken=1,taken_at=NOW() WHERE id=?')->execute([$id]);
      admin_listings_redirect('mark_taken', $id, true, "Listing #$id marked taken.");
    }
    if ($action === 'relist') {
      db()->prepare("UPDATE listings SET is_taken=0,taken_at=NULL,verification_status='submitted',published_at=NULL WHERE id=?")->execute([$id]);
      admin_listings_redirect('relist', $id, true, "Listing #$id submitted for relisting approval.");
    }
  }

  flash_set('err', 'Unknown action.');
  redirect('/admin/listings.php');
}

$highlightId = (int)($_GET['id'] ?? 0);
$highlightAction = (string)($_GET['done'] ?? '');
$highlightFail = isset($_GET['fail']);

$filter = (string)($_GET['filter'] ?? '');
$sql = "SELECT l.id,l.title,l.region,l.listing_type,l.is_taken,l.verification_status,l.verification_badge,l.is_featured,l.show_on_homepage,
               l.listing_package,l.payment_status,l.payment_amount_tzs,l.land_payment_status,l.land_payment_amount_tzs,
               l.video_path,l.created_at,
               u.email AS owner_email,u.phone AS owner_phone,u.full_name AS owner_name
        FROM listings l
        LEFT JOIN users u ON u.id = l.created_by_user_id";
if ($filter === 'land_paid') {
  $sql .= " WHERE l.land_payment_status = 'paid'";
}
$sql .= " ORDER BY FIELD(l.verification_status,'submitted','under_review','approved','rejected'), l.id DESC LIMIT 200";
$stmt = db()->query($sql);
$rows = $stmt->fetchAll();

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem">
    <div class="grid" style="align-items:end">
      <div class="col-7">
        <div class="kicker">Admin</div>
        <h1 style="margin-bottom:.35rem"><?= $filter === 'land_paid' ? 'Plots with buyer payment received' : 'Listings review queue' ?></h1>
        <div class="sub"><?= $filter === 'land_paid' ? 'Listings where a buyer completed online plot payment.' : 'Approve listings to publish them. Preview video on the detail or preview page before approving.' ?></div>
      </div>
      <div class="col-5" style="display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/users.php">Users</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/messages.php">Messages</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/expert-requests.php">Expert requests</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/enquiries.php">Enquiries</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/submit-listing.php">Submit listing</a>
        <a class="btn" href="<?= APP_BASE_URL ?>/index.php">Public browse</a>
      </div>
    </div>
  </div>

  <div class="card pad reveal" style="overflow:auto" data-admin-queue>
    <table class="tbl admin-listings-table" style="min-width:1180px">
      <thead>
        <tr>
          <th>ID</th>
          <th>Listing</th>
          <th>Region</th>
          <th>Owner</th>
          <th>Status</th>
          <th>Featured</th>
          <th>Homepage</th>
          <th>Availability</th>
          <th>Badge</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $rid = (int)$r['id'];
            $vStatus = (string)$r['verification_status'];
            $isApproved = ($vStatus === 'approved');
            $hasVideo = trim((string)($r['video_path'] ?? '')) !== '';
            $rowHighlight = ($highlightId === $rid);
          ?>
          <tr id="listing-row-<?= $rid ?>" class="admin-listing-row<?= $rowHighlight ? ' is-highlighted' : '' ?><?= $rowHighlight && $highlightFail ? ' is-highlight-fail' : '' ?>">
            <td><?= $rid ?></td>
            <td>
              <div style="font-weight:900"><?= h((string)$r['title']) ?></div>
              <div class="sub" style="font-size:.9rem"><?= h(listing_type_label((string)$r['listing_type'])) ?>
                <?php if ($hasVideo): ?> · <span class="pill ok" style="font-size:.62rem">Video</span><?php endif; ?>
              </div>
            </td>
            <td><?= h((string)$r['region']) ?></td>
            <td>
              <?php $oe = trim((string)($r['owner_email'] ?? '')); $op = trim((string)($r['owner_phone'] ?? '')); ?>
              <?php if ($oe !== ''): ?><div style="word-break:break-all"><?= h($oe) ?></div><?php endif; ?>
              <?php if ($op !== ''): ?><div class="sub" style="font-size:.85rem"><?= h($op) ?></div><?php endif; ?>
              <?php if ($oe === '' && $op === ''): ?>-<?php endif; ?>
            </td>
            <td>
              <?php if ($vStatus === 'approved'): ?>
                <span class="pill ok">approved</span>
              <?php elseif ($vStatus === 'under_review'): ?>
                <span class="pill warn">under review</span>
              <?php elseif ($vStatus === 'rejected'): ?>
                <span class="pill neutral">rejected</span>
              <?php else: ?>
                <span class="pill neutral">submitted</span>
              <?php endif; ?>
            </td>
            <td><?= (int)$r['is_featured'] ? 'Yes' : 'No' ?></td>
            <td><span class="pill <?= (int)$r['show_on_homepage'] ? 'ok' : 'neutral' ?>"><?= (int)$r['show_on_homepage'] ? 'Visible' : 'Hidden' ?></span></td>
            <td>
              <span class="pill <?= (int)$r['is_taken'] ? 'neutral' : 'ok' ?>"><?= (int)$r['is_taken'] ? 'taken' : 'available' ?></span>
            </td>
            <td>
              <form method="post" class="small-form admin-action-form" data-listing-id="<?= $rid ?>">
                <input type="hidden" name="id" value="<?= $rid ?>">
                <input type="hidden" name="action" value="set_badge">
                <select name="badge" style="max-width:200px">
                  <?php
                  $cur = (string)($r['verification_badge'] ?? 'none');
                  foreach (['none' => 'None', 'identity_verified' => 'Identity', 'docs_submitted' => 'Docs submitted', 'docs_reviewed' => 'Docs reviewed', 'survey_confirmed' => 'Survey'] as $bv => $bl): ?>
                    <option value="<?= h($bv) ?>" <?= $cur === $bv ? 'selected' : '' ?>><?= h($bl) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn secondary btn-action" type="submit" data-action="badge">Save badge</button>
              </form>
            </td>
            <td>
              <div class="admin-actions-wrap">
                <a class="btn secondary btn-action" href="<?= APP_BASE_URL ?>/admin/view-listing.php?id=<?= $rid ?>">Detail</a>
                <a class="btn secondary btn-action" href="<?= APP_BASE_URL ?>/admin/edit-listing.php?id=<?= $rid ?>">Edit</a>
                <a class="btn secondary btn-action<?= $hasVideo ? ' btn-has-video' : '' ?>" href="<?= APP_BASE_URL ?>/preview-listing.php?id=<?= $rid ?>"><?= $hasVideo ? 'Preview + video' : 'Preview' ?></a>
                <?php if ($isApproved): ?>
                  <a class="btn secondary btn-action btn-public" href="<?= APP_BASE_URL ?>/listing.php?id=<?= $rid ?>" target="_blank" rel="noreferrer">Public</a>
                <?php else: ?>
                  <span class="btn secondary btn-action btn-public is-disabled" title="Available after approval">Public</span>
                <?php endif; ?>
                <form method="post" class="admin-action-form admin-status-form" data-listing-id="<?= $rid ?>">
                  <input type="hidden" name="id" value="<?= $rid ?>">
                  <button class="btn btn-action btn-approve<?= $vStatus === 'approved' ? ' is-active-state' : '' ?>" name="action" value="approve" type="submit" data-action="approve">Approve</button>
                  <button class="btn secondary btn-action btn-review<?= $vStatus === 'under_review' ? ' is-active-state' : '' ?>" name="action" value="review" type="submit" data-action="review">Review</button>
                  <button class="btn secondary btn-action btn-reject<?= $vStatus === 'rejected' ? ' is-active-state' : '' ?>" name="action" value="reject" type="submit" data-action="reject">Reject</button>
                  <?php if ((int)$r['is_taken']): ?><button class="btn secondary btn-action" name="action" value="relist" type="submit">Relist for review</button><?php else: ?><button class="btn secondary btn-action" name="action" value="mark_taken" type="submit">Mark taken</button><?php endif; ?>
                  <?php if ((int)$r['is_featured']): ?>
                    <button class="btn secondary btn-action btn-feature is-active-state" name="action" value="feature_off" type="submit" data-action="feature_off">Unfeature</button>
                  <?php else: ?>
                    <button class="btn secondary btn-action btn-feature" name="action" value="feature_on" type="submit" data-action="feature_on">Feature</button>
                  <?php endif; ?>
                  <?php if ((int)$r['show_on_homepage']): ?>
                    <button class="btn secondary btn-action is-active-state" name="action" value="home_off" type="submit">Remove from home</button>
                  <?php else: ?>
                    <button class="btn secondary btn-action" name="action" value="home_on" type="submit">Show on home</button>
                  <?php endif; ?>
                </form>
                <form method="post" class="admin-action-form" data-listing-id="<?= $rid ?>" onsubmit="return confirm('Permanently delete listing #<?= $rid ?>? This cannot be undone.');">
                  <input type="hidden" name="id" value="<?= $rid ?>">
                  <button class="btn secondary btn-action btn-delete" name="action" value="delete_listing" type="submit" data-action="delete">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($highlightId > 0 && $highlightAction !== ''): ?>
    <script>
      window.__adminHighlight = <?= json_encode(['id' => $highlightId, 'action' => $highlightAction, 'fail' => $highlightFail], JSON_THROW_ON_ERROR) ?>;
    </script>
  <?php endif; ?>
<?php
$content = ob_get_clean();
$title = 'Admin. Listings';
require __DIR__ . '/../_layout.php';
