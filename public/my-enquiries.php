<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();

$stmt = db()->prepare(
  'SELECT e.id, e.created_at, e.listing_id, e.phone, e.interest, e.message,
          l.title AS listing_title, l.verification_status AS listing_status
   FROM enquiries e
   LEFT JOIN listings l ON l.id = e.listing_id
   WHERE e.user_id = ?
   ORDER BY e.id DESC
   LIMIT 100'
);
$stmt->execute([(int)$u['id']]);
$rows = $stmt->fetchAll();

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem">
    <div class="kicker">Account</div>
    <h1 style="margin-bottom:.35rem">My enquiries</h1>
    <div class="sub">Enquiries you sent while logged in (linked to your account). Older guest enquiries are not listed here.</div>
  </div>

  <div class="card pad reveal" style="overflow:auto">
    <?php if (!$rows): ?>
      <p class="sub">No saved enquiries yet. Browse listings and use <strong>Enquire</strong> while logged in.</p>
      <a class="btn" href="<?= APP_BASE_URL ?>/index.php">Browse listings</a>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse;min-width:720px">
        <thead>
          <tr style="background:var(--bg2);text-align:left">
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">When</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Listing</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Interest</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">WhatsApp</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= h((string)$r['created_at']) ?></td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)">
                <?php if (!empty($r['listing_id'])): ?>
                  <?php if (($r['listing_status'] ?? '') === 'approved'): ?>
                    <a href="<?= APP_BASE_URL ?>/listing.php?id=<?= (int)$r['listing_id'] ?>" style="font-weight:800"><?= h((string)($r['listing_title'] ?? 'Listing')) ?></a>
                  <?php else: ?>
                    <span style="font-weight:800"><?= h((string)($r['listing_title'] ?? 'Listing')) ?></span>
                    <div class="sub" style="font-size:.85rem"><?= h((string)($r['listing_status'] ?? '')) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="pill neutral">General</span>
                <?php endif; ?>
              </td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= h((string)($r['interest'] ?? '')) ?></td>
              <td style="padding:.85rem;border-bottom:1px solid var(--line)"><?= h((string)$r['phone']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$title = 'My enquiries. Ardhi Guide';
require __DIR__ . '/_layout.php';
