<?php
/** @var array<string,mixed> $listing */
/** @var int $listingId */
?>
  <div class="card pad reveal" style="margin-top:1rem">
    <div class="kicker">Seller publication fee</div>
    <p class="sub" style="margin:.35rem 0 1rem">Fee for the seller to publish this listing. Assign a phone for the USSD prompt, or leave blank so the seller enters their own number.</p>
    <form method="post" class="stack">
      <input type="hidden" name="action" value="save_payment_settings">
      <div class="row">
        <div>
          <label>Listing fee (TZS)</label>
          <input name="payment_amount_tzs" type="text" required value="<?= h((string)(int)($listing['payment_amount_tzs'] ?? 0)) ?>" placeholder="5000">
          <div class="sub" style="font-size:.85rem;margin-top:.3rem">Minimum <?= (int)snippe_min_amount_tzs() ?> TZS for Snippe online pay.</div>
        </div>
        <div>
          <label>Push prompt phone</label>
          <input name="payment_push_phone" type="tel" placeholder="255712345678" value="<?= h((string)($listing['payment_push_phone'] ?? '')) ?>">
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;font-weight:600;cursor:pointer">
        <input type="checkbox" name="payment_push_enabled" value="1" <?= (int)($listing['payment_push_enabled'] ?? 0) ? 'checked' : '' ?>>
        Lock payment prompt to assigned phone (seller cannot change it)
      </label>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <button class="btn" type="submit">Save payment settings</button>
        <button class="btn secondary" type="submit" name="unassign_phone" value="1" onclick="return confirm('Clear push phone and turn off locked push?');">Unassign push phone</button>
      </div>
    </form>
    <?php if (snippe_enabled()): ?>
      <p class="sub" style="margin-top:.85rem;font-size:.88rem">Snippe webhook URL: <code style="word-break:break-all"><?= h(snippe_webhook_url()) ?></code></p>
    <?php else: ?>
      <p class="sub" style="margin-top:.85rem;font-size:.88rem">Enable Snippe in app/.env (SNIPPE_ENABLED=1, API key, webhook secret).</p>
    <?php endif; ?>
  </div>
