<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$cats = db()->query("SELECT id, title, subtitle FROM payment_categories WHERE is_published = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

$stepsByCat = [];
if ($cats) {
  $ids = array_map(static fn($c) => (int)$c['id'], $cats);
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = db()->prepare("SELECT id, category_id, body FROM payment_steps WHERE category_id IN ($in) ORDER BY sort_order ASC, id ASC");
  $st->execute($ids);
  foreach ($st->fetchAll() as $row) {
    $stepsByCat[(int)$row['category_id']][] = $row;
  }
}

ob_start();
?>
  <div class="card pad reveal" style="margin-bottom:1rem;border-color:rgba(14,92,74,.18);background:linear-gradient(180deg, var(--surface), var(--bg2))">
    <div class="kicker">Payment guide</div>
    <h1 style="margin:.25rem 0 .55rem">How to pay your listing fee</h1>
    <p class="lead">Follow the steps that match how you want to pay. If anything is unclear, send us a quick message on WhatsApp.</p>
    <div style="margin-top:1.1rem;display:flex;gap:.7rem;flex-wrap:wrap">
      <a class="btn" href="https://wa.me/255657925368" target="_blank" rel="noopener">Open WhatsApp</a>
      <a class="btn ghost" href="<?= APP_BASE_URL ?>/how-it-works.php">Back to the full guide</a>
    </div>
  </div>

  <?php if (!$cats): ?>
    <div class="card pad reveal">
      <div class="kicker">Coming soon</div>
      <h2 style="margin:.25rem 0 .55rem">Payment instructions will appear here.</h2>
      <p class="sub">Our admin team is preparing the payment categories. Please check back shortly, or reach out on WhatsApp for help right now.</p>
    </div>
  <?php else: ?>
    <div class="pi-grid reveal">
      <?php foreach ($cats as $i => $c): $cid = (int)$c['id']; $cs = $stepsByCat[$cid] ?? []; ?>
        <article class="pi-card">
          <div class="pi-card-head">
            <span class="pi-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
            <div>
              <h2 class="pi-title"><?= h((string)$c['title']) ?></h2>
              <?php if (!empty($c['subtitle'])): ?>
                <p class="pi-subtitle"><?= h((string)$c['subtitle']) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($cs): ?>
            <ol class="instr-steps">
              <?php foreach ($cs as $s): ?>
                <li><?= nl2br(h((string)$s['body'])) ?></li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?>
            <p class="sub">Steps will appear here once the admin team adds them.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="cta-banner reveal" style="margin-top:2rem">
    <div>
      <div class="kicker" style="color:var(--gold-100);letter-spacing:.28em">Need help?</div>
      <h2>Send us a quick <em>WhatsApp</em> and we will walk you through it.</h2>
      <p>Our team responds during business hours and helps with reference numbers, screenshots of payment, and matching your payment to your listing.</p>
    </div>
    <div class="btns">
      <a class="btn" href="https://wa.me/255657925368" target="_blank" rel="noopener">Chat on WhatsApp</a>
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
$title = 'Payment guide. Ardhi Guide';
require __DIR__ . '/_layout.php';
