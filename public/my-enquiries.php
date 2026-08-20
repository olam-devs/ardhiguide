<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();

$stmt = db()->prepare(
  'SELECT e.id,e.created_at,e.listing_id,e.phone,e.interest,e.message,e.request_type,e.status,
          l.title AS listing_title,l.verification_status AS listing_status,p.full_name AS provider_name,p.role AS provider_role
   FROM enquiries e
   LEFT JOIN listings l ON l.id = e.listing_id
   LEFT JOIN users p ON p.id=e.assigned_provider_user_id
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
    <h1 style="margin-bottom:.35rem">My property requests</h1>
    <div class="sub">Information, viewing, contact, and matching requests sent from your account.</div>
  </div>

  <div class="card pad reveal" style="overflow:auto">
    <?php if (!$rows): ?>
      <p class="sub">No saved requests yet. Browse approved properties and choose the next step you need.</p>
      <a class="btn" href="<?= APP_BASE_URL ?>/index.php">Browse listings</a>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse;min-width:720px">
        <thead>
          <tr style="background:var(--bg2);text-align:left">
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">When</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Listing</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Interest</th>
            <th style="padding:.85rem;border-bottom:1px solid var(--line)">Progress</th>
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
              <td style="padding:.85rem;border-bottom:1px solid var(--line)"><span class="pill <?= $r['status']==='new'?'warn':'ok' ?>"><?= h((string)$r['status']) ?></span><?php if($r['provider_name']):?><div class="sub" style="font-size:.82rem;margin-top:.3rem">Coordinated with <?= h((string)$r['provider_name']) ?></div><?php endif;?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <div style="margin-top:1rem"><a class="btn secondary" href="<?= APP_BASE_URL ?>/messages.php">Chat with Ardhi Way</a></div>
  </div>
<?php
$content = ob_get_clean();
$title = 'My property requests. Ardhi Way';
require __DIR__ . '/_layout.php';
