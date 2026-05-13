<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$u = require_role('admin');
$listingId = (int)($_GET['id'] ?? 0);
if ($listingId <= 0) {
  flash_set('err', 'Invalid listing.');
  redirect('/admin/listings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'save_notes') {
    $notes = trim((string)($_POST['admin_notes'] ?? ''));
    db()->prepare('UPDATE listings SET admin_notes = ? WHERE id = ?')->execute([
      $notes !== '' ? $notes : null,
      $listingId,
    ]);
    flash_set('ok', 'Internal notes saved.');
    redirect('/admin/view-listing.php?id=' . $listingId);
  }
  if ($action === 'mark_paid') {
    listing_mark_paid($listingId);
    flash_set('ok', 'Payment marked as received.');
    redirect('/admin/view-listing.php?id=' . $listingId);
  }
  if ($action === 'mark_waived') {
    listing_mark_waived($listingId);
    flash_set('ok', 'Payment waived for this listing.');
    redirect('/admin/view-listing.php?id=' . $listingId);
  }
  if ($action === 'delete_doc') {
    $docId = (int)($_POST['doc_id'] ?? 0);
    if ($docId > 0) {
      $st = db()->prepare(
        'SELECT d.stored_name FROM listing_documents d WHERE d.id = ? AND d.listing_id = ?'
      );
      $st->execute([$docId, $listingId]);
      $doc = $st->fetch();
      if ($doc) {
        $path = storage_private_dir() . DIRECTORY_SEPARATOR . (string)$doc['stored_name'];
        db()->prepare('DELETE FROM listing_documents WHERE id = ?')->execute([$docId]);
        if (is_file($path)) {
          @unlink($path);
        }
        flash_set('ok', 'Document removed.');
      }
    }
    redirect('/admin/view-listing.php?id=' . $listingId);
  }
}

$st = db()->prepare(
  'SELECT l.*, u.email AS owner_email, u.full_name AS owner_name
   FROM listings l
   LEFT JOIN users u ON u.id = l.created_by_user_id
   WHERE l.id = ?'
);
$st->execute([$listingId]);
$listing = $st->fetch();
if (!$listing) {
  flash_set('err', 'Listing not found.');
  redirect('/admin/listings.php');
}

$docsStmt = db()->prepare(
  'SELECT id, original_name, mime, size_bytes, created_at
   FROM listing_documents
   WHERE listing_id = ?
   ORDER BY id ASC'
);
$docsStmt->execute([$listingId]);
$docs = $docsStmt->fetchAll();

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem">
    <div class="grid" style="align-items:end">
      <div class="col-7">
        <div class="kicker">Admin · Listing</div>
        <h1 style="margin-bottom:.35rem"><?= h((string)$listing['title']) ?></h1>
        <div class="sub">#<?= $listingId ?> · <?= h((string)$listing['region']) ?> · <?= h((string)$listing['verification_status']) ?></div>
      </div>
      <div class="col-5" style="display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/listings.php">Queue</a>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/listing-documents.php?id=<?= $listingId ?>">Upload as admin</a>
        <a class="btn" href="<?= APP_BASE_URL ?>/preview-listing.php?id=<?= $listingId ?>">Preview</a>
      </div>
    </div>
  </div>

  <div class="grid" style="align-items:start">
    <div class="col-7">
      <div class="card pad reveal">
        <div class="kicker">Owner</div>
        <p class="sub" style="margin:.5rem 0 0">
          <?= h((string)($listing['owner_name'] ?? '-')) ?><br>
          <?= h((string)($listing['owner_email'] ?? '-')) ?>
        </p>
        <div class="kicker" style="margin-top:1rem">Description</div>
        <div class="sub" style="margin-top:.5rem"><?= nl2br(h((string)($listing['description'] ?? ''))) ?></div>
      </div>
    </div>
    <div class="col-5">
      <div class="card pad reveal">
        <div class="kicker">Facts</div>
        <ul class="sub" style="margin:.5rem 0 0;padding-left:1.1rem;line-height:1.8">
          <li>Category: <?= h((string)$listing['category']) ?></li>
          <li>Location: <?= h((string)($listing['location_text'] ?? '')) ?></li>
          <li>Size: <?= h((string)($listing['size_text'] ?? '')) ?></li>
          <li>Price: <?= h(format_tzs((string)($listing['price_tzs'] ?? ''))) ?></li>
          <li>Badge: <?= h((string)$listing['verification_badge']) ?></li>
          <li>Package: <?= h((string)($listing['listing_package'] ?? 'basic')) ?></li>
          <li>Payment: <?= h((string)($listing['payment_status'] ?? 'pending')) ?> · <?= h(format_tzs((string)($listing['payment_amount_tzs'] ?? '0'))) ?></li>
          <?php if (!empty($listing['payment_reference'])): ?>
            <li>Ref: <code><?= h((string)$listing['payment_reference']) ?></code></li>
          <?php endif; ?>
          <?php if (!empty($listing['paid_at'])): ?>
            <li>Paid at: <?= h((string)$listing['paid_at']) ?></li>
          <?php endif; ?>
        </ul>
        <?php $ps = (string)($listing['payment_status'] ?? 'pending'); ?>
        <?php if ($ps === 'pending'): ?>
          <div style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
            <form method="post" style="margin:0">
              <input type="hidden" name="action" value="mark_paid">
              <button class="btn" type="submit">Mark paid</button>
            </form>
            <form method="post" style="margin:0">
              <input type="hidden" name="action" value="mark_waived">
              <button class="btn secondary" type="submit">Waive fee</button>
            </form>
            <a class="btn secondary" href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= $listingId ?>">Open pay page</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card pad reveal" style="margin-top:1rem">
    <div class="kicker">Internal notes (admin only)</div>
    <p class="sub" style="margin:.35rem 0 .8rem">Not visible to sellers or the public.</p>
    <form method="post" class="stack">
      <input type="hidden" name="action" value="save_notes">
      <textarea name="admin_notes" placeholder="Verification checklist, phone calls, risk flags..."><?= h((string)($listing['admin_notes'] ?? '')) ?></textarea>
      <button class="btn" type="submit">Save notes</button>
    </form>
  </div>

  <div class="card pad reveal" style="margin-top:1rem">
    <div class="kicker">Private documents</div>
    <h2 style="margin:.35rem 0 1rem;font-size:1.2rem">Verification files</h2>
    <?php if (!$docs): ?>
      <p class="sub">No documents uploaded.</p>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="background:var(--bg2);text-align:left">
            <th style="padding:.75rem;border-bottom:1px solid var(--line)">File</th>
            <th style="padding:.75rem;border-bottom:1px solid var(--line)">Size</th>
            <th style="padding:.75rem;border-bottom:1px solid var(--line)">When</th>
            <th style="padding:.75rem;border-bottom:1px solid var(--line)"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $d): ?>
            <tr>
              <td style="padding:.75rem;border-bottom:1px solid var(--line)">
                <a href="<?= APP_BASE_URL ?>/download-document.php?id=<?= (int)$d['id'] ?>" style="font-weight:800"><?= h((string)$d['original_name']) ?></a>
              </td>
              <td style="padding:.75rem;border-bottom:1px solid var(--line)"><?= (int)$d['size_bytes'] ?> B</td>
              <td style="padding:.75rem;border-bottom:1px solid var(--line)"><?= h((string)$d['created_at']) ?></td>
              <td style="padding:.75rem;border-bottom:1px solid var(--line)">
                <form method="post" style="margin:0" onsubmit="return confirm('Delete this file permanently?');">
                  <input type="hidden" name="action" value="delete_doc">
                  <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                  <button class="btn secondary" style="padding:.45rem .75rem" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$title = 'Admin listing #' . $listingId;
require __DIR__ . '/../_layout.php';
