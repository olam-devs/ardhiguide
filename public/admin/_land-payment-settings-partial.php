<?php
/** @var array<string,mixed> $listing */
/** @var int $listingId */
$landOpen = listing_land_payment_open($listing);
$landPaid = (string)($listing['land_payment_status'] ?? 'none') === 'paid';
?>
  <div class="card pad reveal" style="margin-top:1rem">
    <div class="kicker">Buyer plot payment</div>
    <p class="sub" style="margin:.35rem 0 1rem">Amount a logged-in buyer pays for this approved plot. Optionally restrict to one user ID and/or lock the USSD prompt phone.</p>
    <form method="post" class="stack">
      <input type="hidden" name="action" value="save_land_payment_settings">
      <div class="row">
        <div>
          <label>Payment amount (TZS)</label>
          <input name="land_payment_amount_tzs" type="text" required value="<?= h((string)(int)($listing['land_payment_amount_tzs'] ?? 0)) ?>" placeholder="50000">
        </div>
        <div>
          <label>Assigned buyer user ID (optional)</label>
          <input name="land_payment_user_id" type="number" min="0" step="1" placeholder="Any logged-in buyer" value="<?= (int)($listing['land_payment_user_id'] ?? 0) > 0 ? (int)$listing['land_payment_user_id'] : '' ?>">
        </div>
      </div>
      <div class="row">
        <div>
          <label>Push prompt phone</label>
          <input name="land_payment_push_phone" type="tel" placeholder="255712345678" value="<?= h((string)($listing['land_payment_push_phone'] ?? '')) ?>">
        </div>
        <div style="display:flex;align-items:flex-end">
          <label style="display:flex;align-items:center;gap:.5rem;font-weight:600;cursor:pointer;margin:0">
            <input type="checkbox" name="land_payment_push_enabled" value="1" <?= (int)($listing['land_payment_push_enabled'] ?? 0) ? 'checked' : '' ?>>
            Lock prompt to assigned phone
          </label>
        </div>
      </div>
      <?php if (!$landPaid): ?>
        <label style="display:flex;align-items:center;gap:.5rem;font-weight:600;cursor:pointer">
          <input type="checkbox" name="land_payment_open" value="1" <?= $landOpen ? 'checked' : '' ?>>
          Accept online payment from buyers (shows Pay on public listing)
        </label>
      <?php else: ?>
        <p class="sub" style="margin:0">Buyer payment received<?= !empty($listing['land_paid_at']) ? ' · ' . h((string)$listing['land_paid_at']) : '' ?>.</p>
      <?php endif; ?>
      <?php if (!empty($listing['land_payment_reference'])): ?>
        <p class="sub" style="margin:0;font-size:.88rem">Buyer payment code: <code><?= h((string)$listing['land_payment_reference']) ?></code></p>
      <?php endif; ?>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <button class="btn" type="submit">Save buyer payment settings</button>
        <?php if ($landPaid): ?>
          <button class="btn secondary" type="submit" name="action" value="mark_land_waived" formnovalidate onclick="return confirm('Clear buyer payment (for testing)?');">Clear buyer payment</button>
        <?php endif; ?>
        <a class="btn secondary" href="<?= APP_BASE_URL ?>/pay-listing.php?id=<?= $listingId ?>&for=land" target="_blank" rel="noreferrer">Preview pay page</a>
      </div>
    </form>
  </div>
