<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$cats = payment_guide_load_published();
$snippeOn = snippe_enabled();

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem;border-color:rgba(14,92,74,.18);background:linear-gradient(180deg, var(--surface), var(--bg2))">
    <div class="kicker">Payment guide</div>
    <h1 style="margin:.25rem 0 .55rem">How to pay your listing fee</h1>
    <p class="lead">
      <?php if ($snippeOn): ?>
        Pay securely online with <strong>mobile money</strong> (USSD prompt on your phone) or <strong>card</strong>.
        Enter your PIN only on the prompt on your phone — never share it with anyone. Your payment is confirmed automatically when it succeeds.
      <?php else: ?>
        Online checkout is being configured. Use the steps below and contact us on WhatsApp if you need help paying.
      <?php endif; ?>
    </p>
    <div style="margin-top:1.1rem;display:flex;gap:.7rem;flex-wrap:wrap">
      <?php if ($u = current_user()): ?>
        <a class="btn" href="<?= APP_BASE_URL ?>/my-listings.php">My listings</a>
      <?php else: ?>
        <a class="btn" href="<?= APP_BASE_URL ?>/login.php">Log in to pay</a>
      <?php endif; ?>
      <a class="btn secondary" href="<?= h(whatsapp_link('Hello Ardhi Way, I need help paying my listing fee.')) ?>" target="_blank" rel="noopener">WhatsApp help</a>
      <a class="btn ghost" href="<?= APP_BASE_URL ?>/how-it-works.php">How it works</a>
    </div>
  </div>

  <?php if (!$cats): ?>
    <div class="card pad reveal">
      <div class="kicker">Setting up</div>
      <h2 style="margin:.25rem 0 .55rem">Payment instructions are being published.</h2>
      <p class="sub">Please check back shortly or message us on WhatsApp. If you already submitted a listing, open <strong>My listings</strong> and tap <strong>Pay</strong> when online checkout is ready.</p>
    </div>
  <?php else: ?>
    <?php if ($snippeOn): ?>
      <div class="card pad reveal" style="margin-bottom:1rem;background:var(--brand-50);border-color:rgba(14,92,74,.22)">
        <p class="sub" style="margin:0;line-height:1.65">
          <strong>Quick path:</strong> Log in → <strong>My listings</strong> → <strong>Pay</strong> on your listing → choose
          <strong>Pay with mobile money</strong> or <strong>Pay with card</strong> on the pay page. No till number or manual transfer needed.
        </p>
      </div>
    <?php endif; ?>
    <div class="pi-grid reveal">
      <?php foreach ($cats as $i => $c): ?>
        <article class="pi-card">
          <div class="pi-card-head">
            <span class="pi-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <div>
              <h2 class="pi-title"><?= h($c['title']) ?></h2>
              <?php if (!empty($c['subtitle'])): ?>
                <p class="pi-subtitle"><?= h($c['subtitle']) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php if (!empty($c['steps'])): ?>
            <ol class="instr-steps">
              <?php foreach ($c['steps'] as $s): ?>
                <li><?= payment_guide_format_step((string)$s['body']) ?></li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?>
            <p class="sub">Steps will appear here once added.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="cta-banner reveal" style="margin-top:2rem">
    <div>
      <div class="kicker" style="color:var(--gold-100);letter-spacing:.28em">Need help?</div>
      <h2>Payment issue? <em>Message us on WhatsApp.</em></h2>
      <p>Send your listing payment code and a screenshot of any error or confirmation SMS. We respond during business hours.</p>
    </div>
    <div class="btns">
      <a class="btn" href="<?= h(whatsapp_link('Hello Ardhi Way, I need help with my listing payment.')) ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
      <a class="btn ghost" href="tel:+255657925368">Call us</a>
    </div>
  </section>

  <style>
    .pi-grid{ display:grid; grid-template-columns: 1fr 1fr; gap: 1rem }
    @media (max-width: 800px){ .pi-grid{ grid-template-columns: 1fr } }
    .pi-card{
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 1.4rem 1.5rem 1.5rem;
      box-shadow: var(--shadow-sm);
      transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
    }
    .pi-card:hover{ transform: translateY(-3px); box-shadow: var(--shadow); border-color: rgba(14,92,74,.22) }
    .pi-card-head{ display:flex; gap: .85rem; align-items: flex-start; margin-bottom: .8rem }
    .pi-num{
      flex: 0 0 auto;
      width: 2.4rem; height: 2.4rem; border-radius: 12px;
      display:flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg, var(--gold-50), #fff);
      border: 1px solid rgba(165,120,38,.30);
      color: var(--gold-700);
      font-family: var(--font-display); font-weight: 800; font-size: 1rem;
    }
    .pi-title{ margin: 0; font-size: 1.35rem; line-height: 1.2 }
    .pi-subtitle{ margin: .25rem 0 0; color: var(--muted); font-size: .94rem; line-height: 1.55 }
  </style>
<?php
$content = ob_get_clean();
$title = 'Payment guide. Ardhi Way';
require __DIR__ . '/_layout.php';
