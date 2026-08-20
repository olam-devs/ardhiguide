<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$u = require_auth();
$uid = (int)$u['id'];
$role = (string)($u['role'] ?? 'buyer');

$pubFees = user_pending_publication_fees($uid);
$landPays = user_open_land_payments($uid);
$canList = user_can_manage_listings($u);

ob_start();
?>
  <div class="card pad reveal" style="max-width:820px;margin:0 auto">
    <div class="kicker">Payments</div>
    <h1>What you can pay online</h1>
    <p class="sub" style="line-height:1.7">
      <?php if ($role === 'buyer'): ?>
        As a <strong>buyer</strong>, you pay for plots when admin enables payment on an approved listing.
        To list your own land, switch to <strong>Seller</strong> on <a href="<?= APP_BASE_URL ?>/my-account.php">My account</a>.
      <?php elseif (in_array($role, ['seller', 'agent'], true)): ?>
        Pay your <strong>publication fee</strong> below, or browse plots and pay where admin opened buyer payment.
      <?php else: ?>
        Manage seller fees and buyer plot payments from this page.
      <?php endif; ?>
    </p>

    <?php if ($pubFees): ?>
      <div class="card pad" style="margin-top:1.25rem;background:var(--gold-50);border-color:rgba(165,120,38,.25)">
        <div class="kicker">Your listing fees</div>
        <h2 style="margin:.35rem 0 .75rem;font-size:1.15rem">Publication fees due</h2>
        <ul class="sub" style="margin:0;padding:0;list-style:none">
          <?php foreach ($pubFees as $row): ?>
            <li style="padding:.75rem 0;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
              <div>
                <strong><?= h((string)$row['title']) ?></strong><br>
                <span style="font-size:.88rem"><?= h(format_tzs((string)$row['payment_amount_tzs'])) ?> · code <?= h((string)$row['payment_reference']) ?></span>
              </div>
              <a class="btn" href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= (int)$row['id'] ?>">Pay now</a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($landPays): ?>
      <div class="card pad" style="margin-top:1.25rem;background:var(--brand-50);border-color:rgba(14,92,74,.22)">
        <div class="kicker">Plot payments</div>
        <h2 style="margin:.35rem 0 .75rem;font-size:1.15rem">Plots you can pay for</h2>
        <ul class="sub" style="margin:0;padding:0;list-style:none">
          <?php foreach ($landPays as $row): ?>
            <li style="padding:.75rem 0;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
              <div>
                <strong><?= h((string)$row['title']) ?></strong><br>
                <span style="font-size:.88rem"><?= h(format_tzs((string)$row['land_payment_amount_tzs'])) ?></span>
              </div>
              <a class="btn" href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= (int)$row['id'] ?>&for=land">Pay online</a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (!$pubFees && !$landPays): ?>
      <div class="card pad" style="margin-top:1.25rem;background:var(--bg2)">
        <p class="sub" style="margin:0;line-height:1.65">
          <strong>Nothing to pay right now.</strong><br>
          <?php if ($role === 'buyer'): ?>
            Open an approved listing from Browse — if admin enabled payment, you will see <strong>Pay online</strong> on that plot.
          <?php else: ?>
            Submit a listing first, or wait for admin to open buyer payment on a plot.
          <?php endif; ?>
        </p>
        <div style="margin-top:1rem;display:flex;gap:.6rem;flex-wrap:wrap">
          <a class="btn secondary" href="<?= APP_BASE_URL ?>/index.php">Browse plots</a>
          <?php if ($canList): ?>
            <a class="btn" href="<?= APP_BASE_URL ?>/my-listings.php">My listings</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php
$content = ob_get_clean();
$title = 'Payments. Ardhi Way';
require __DIR__ . '/_layout.php';
