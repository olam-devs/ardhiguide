<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

$u = require_role('admin');

$stmt = db()->query(
  "SELECT e.id,e.created_at,e.name,e.phone,e.interest,e.message,
          l.id AS listing_id, l.title AS listing_title
   FROM enquiries e
   LEFT JOIN listings l ON l.id = e.listing_id
   ORDER BY e.id DESC
   LIMIT 250"
);
$rows = $stmt->fetchAll();

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem">
    <div class="grid" style="align-items:end">
      <div class="col-7">
        <div class="kicker">Admin</div>
        <h1 style="margin-bottom:.35rem">Enquiries</h1>
        <div class="sub">Every enquiry is saved as a lead before redirecting the user to WhatsApp.</div>
      </div>
      <div class="col-5" style="display:flex;gap:.6rem;justify-content:flex-end;flex-wrap:wrap">
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/admin/listings.php">Listings</a>
        <a class="btn" href="<?= APP_BASE_URL ?>/index.php">Public browse</a>
      </div>
    </div>
  </div>

  <div class="card pad reveal" style="overflow:auto">
    <table style="width:100%;border-collapse:collapse;min-width:980px">
      <thead>
        <tr style="background:var(--bg2);text-align:left">
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">ID</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">When</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Lead</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Listing</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Interest</th>
          <th style="padding:.85rem;border-bottom:1px solid var(--line)">Message</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= (int)$r['id'] ?></td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <div style="font-weight:900"><?= h((string)$r['created_at']) ?></div>
            </td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <div style="font-weight:900"><?= h((string)($r['name'] ?? '')) ?></div>
              <div class="sub" style="font-size:.9rem"><?= h((string)$r['phone']) ?></div>
            </td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <?php if (!empty($r['listing_id'])): ?>
                <a href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$r['listing_id'] ?>" target="_blank" rel="noreferrer" style="font-weight:900;text-decoration:none">
                  <?= h((string)$r['listing_title']) ?>
                </a>
                <div class="sub" style="font-size:.9rem">#<?= (int)$r['listing_id'] ?></div>
              <?php else: ?>
                <span class="pill neutral">General</span>
              <?php endif; ?>
            </td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= h((string)($r['interest'] ?? '')) ?></td>
            <td style="padding:.85rem;border-bottom:1px solid var(--line)">
              <div class="sub" style="color:rgba(20,12,7,.75)"><?= h((string)($r['message'] ?? '')) ?></div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php
$content = ob_get_clean();
$title = 'Admin. Enquiries';
require __DIR__ . '/../_layout.php';

