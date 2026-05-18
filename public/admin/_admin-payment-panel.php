<?php
/** @var array<string,mixed> $listing */
/** @var int $listingId */
$paymentFormAction = $paymentFormAction ?? (APP_BASE_URL . '/admin/view-listing.php?id=' . $listingId);
$isApproved = (string)($listing['verification_status'] ?? '') === 'approved';
$landStatus = (string)($listing['land_payment_status'] ?? 'none');
$landOpen = listing_land_payment_open($listing);
$landPaid = $landStatus === 'paid';
$sellerStatus = (string)($listing['payment_status'] ?? 'pending');
?>
<style>
  .admin-pay-panel .admin-check{
    display:flex;align-items:flex-start;gap:.55rem;
    margin:0;padding:.65rem .75rem;
    border:1px solid var(--line);border-radius:10px;
    background:#fff;cursor:pointer;
    font-weight:500;font-size:.9rem;line-height:1.45;
    max-width:36rem;
  }
  .admin-pay-panel .admin-check input[type=checkbox]{
    width:1rem;height:1rem;margin:.12rem 0 0;flex-shrink:0;
    accent-color:var(--brand);cursor:pointer;
  }
  .admin-pay-panel .pay-section{
    padding:1rem 0;border-bottom:1px solid var(--line);
  }
  .admin-pay-panel .pay-section:last-child{border-bottom:none;padding-bottom:0}
  .admin-pay-panel .pay-status-row{
    display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.75rem;
  }
</style>
<div class="card pad reveal admin-pay-panel" style="margin-top:1rem" id="payments">
  <div class="kicker">Payments</div>
  <p class="sub" style="margin:.35rem 0 1rem">Seller publication fee and buyer plot payment. Save each section separately.</p>

  <div class="pay-section">
    <h3 style="margin:0 0 .5rem;font-size:1.05rem">Seller publication fee</h3>
    <div class="pay-status-row">
      <span class="pill <?= $sellerStatus === 'paid' ? 'ok' : ($sellerStatus === 'waived' ? 'neutral' : 'warn') ?>"><?= h($sellerStatus) ?></span>
      <span class="pill neutral"><?= h(format_tzs((string)($listing['payment_amount_tzs'] ?? '0'))) ?></span>
      <?php if (!empty($listing['payment_reference'])): ?>
        <span class="pill neutral">ref <?= h((string)$listing['payment_reference']) ?></span>
      <?php endif; ?>
    </div>
    <form method="post" action="<?= h($paymentFormAction) ?>" class="stack">
      <input type="hidden" name="action" value="save_payment_settings">
      <div class="row">
        <div>
          <label>Listing fee (TZS)</label>
          <input name="payment_amount_tzs" type="text" required value="<?= h((string)(int)($listing['payment_amount_tzs'] ?? 0)) ?>" placeholder="5000">
        </div>
        <div>
          <label>Push prompt phone</label>
          <input name="payment_push_phone" type="tel" placeholder="255712345678" value="<?= h((string)($listing['payment_push_phone'] ?? '')) ?>">
        </div>
      </div>
      <label class="admin-check">
        <input type="checkbox" name="payment_push_enabled" value="1" <?= (int)($listing['payment_push_enabled'] ?? 0) ? 'checked' : '' ?>>
        <span>Lock USSD prompt to the phone above (seller cannot change it on the pay page)</span>
      </label>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <button class="btn" type="submit">Save seller fee settings</button>
        <button class="btn secondary" type="submit" name="unassign_phone" value="1" onclick="return confirm('Clear push phone and unlock prompt?');">Clear push phone</button>
        <?php if ($sellerStatus === 'pending'): ?>
          <a class="btn ghost" href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= $listingId ?>" target="_blank" rel="noreferrer">Preview pay page</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="pay-section">
    <h3 style="margin:0 0 .5rem;font-size:1.05rem">Buyer plot payment (public Pay button)</h3>
    <?php if (!$isApproved): ?>
      <p class="sub" style="margin:0 0 .75rem;padding:.65rem;background:var(--bg2);border-radius:8px">
        Set the amount now if you like. The <strong>Pay</strong> button on the public listing appears only after you approve this listing and enable buyer payment below.
      </p>
    <?php endif; ?>
    <div class="pay-status-row">
      <span class="pill <?= $landPaid ? 'ok' : ($landStatus === 'pending' ? 'warn' : 'neutral') ?>"><?= h($landStatus) ?></span>
      <span class="pill neutral"><?= h(format_tzs((string)($listing['land_payment_amount_tzs'] ?? '0'))) ?></span>
      <?php if ($landOpen): ?><span class="pill ok">Pay button live</span><?php endif; ?>
      <?php if ($landPaid && !empty($listing['land_paid_at'])): ?>
        <span class="pill neutral"><?= h((string)$listing['land_paid_at']) ?></span>
      <?php endif; ?>
      <?php if (!empty($listing['land_payment_reference'])): ?>
        <span class="pill neutral">ref <?= h((string)$listing['land_payment_reference']) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($landPaid): ?>
      <p class="sub" style="margin:0 0 .75rem">Plot marked paid. Uncheck buyer payment below and save to stop new payments, or use <strong>Reset buyer payment</strong> to clear the record entirely.</p>
    <?php endif; ?>
    <form method="post" action="<?= h($paymentFormAction) ?>" class="stack">
      <input type="hidden" name="action" value="save_land_payment_settings">
      <div class="row">
        <div>
          <label>Buyer payment amount (TZS)</label>
          <input name="land_payment_amount_tzs" type="text" required value="<?= h((string)(int)($listing['land_payment_amount_tzs'] ?? 0)) ?>" placeholder="50000">
        </div>
        <div>
          <label>Assigned buyer user ID (optional)</label>
          <input name="land_payment_user_id" type="number" min="0" step="1" placeholder="Any logged-in buyer" value="<?= (int)($listing['land_payment_user_id'] ?? 0) > 0 ? (int)$listing['land_payment_user_id'] : '' ?>">
        </div>
      </div>
      <div class="row">
        <div>
          <label>Buyer push phone</label>
          <input name="land_payment_push_phone" type="tel" placeholder="255712345678" value="<?= h((string)($listing['land_payment_push_phone'] ?? '')) ?>">
        </div>
      </div>
      <label class="admin-check">
        <input type="checkbox" name="land_payment_push_enabled" value="1" <?= (int)($listing['land_payment_push_enabled'] ?? 0) ? 'checked' : '' ?>>
        <span>Lock buyer USSD prompt to the phone above</span>
      </label>
      <?php if ($isApproved): ?>
        <label class="admin-check">
          <input type="checkbox" name="land_payment_open" value="1" <?= $landOpen ? 'checked' : '' ?>>
          <span>Allow buyers to pay online (shows <strong>Pay</strong> on the public listing)</span>
        </label>
        <p class="sub" style="margin:0;font-size:.85rem">Uncheck and save to remove payment access while the listing stays approved.</p>
      <?php else: ?>
        <input type="hidden" name="land_payment_open" value="0">
      <?php endif; ?>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <button class="btn" type="submit">Save buyer payment settings</button>
        <?php if ($landStatus === 'pending' && $isApproved): ?>
          <button class="btn secondary" type="submit" name="action" value="mark_land_paid" formnovalidate onclick="return confirm('Mark as paid manually?');">Mark paid manually</button>
        <?php endif; ?>
        <?php if ($landPaid || $landStatus === 'waived'): ?>
          <button class="btn secondary" type="submit" name="action" value="mark_land_waived" formnovalidate onclick="return confirm('Reset buyer payment record?');">Reset buyer payment</button>
        <?php endif; ?>
        <?php if ($isApproved && (int)($listing['land_payment_amount_tzs'] ?? 0) >= snippe_min_amount_tzs()): ?>
          <a class="btn ghost" href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= $listingId ?>&for=land" target="_blank" rel="noreferrer">Preview buyer pay page</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
