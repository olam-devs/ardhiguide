<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

session_start_safe();
$viewer = current_user();
$canList = $viewer && in_array(($viewer['role'] ?? ''), ['seller', 'agent', 'admin'], true);

$q = trim((string)($_GET['q'] ?? ''));
$region = trim((string)($_GET['region'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));

$sql = "SELECT id,title,category,region,location_text,size_text,price_tzs,is_featured,verification_badge
        FROM listings
        WHERE verification_status = 'approved'";
$params = [];

if ($q !== '') {
  $sql .= " AND (title LIKE ? OR location_text LIKE ? OR region LIKE ?)";
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($region !== '') {
  $sql .= " AND region = ?";
  $params[] = $region;
}
if ($category !== '') {
  $sql .= " AND category = ?";
  $params[] = $category;
}

$sql .= " ORDER BY is_featured DESC, published_at DESC, id DESC LIMIT 60";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

$regions = db()->query("SELECT DISTINCT region FROM listings WHERE verification_status='approved' ORDER BY region ASC")->fetchAll();

$regionCounts = [];
try {
  $rc = db()->query("SELECT region, COUNT(*) AS c FROM listings WHERE verification_status='approved' GROUP BY region ORDER BY c DESC LIMIT 12")->fetchAll();
  foreach ($rc as $row) {
    $regionCounts[(string)$row['region']] = (int)$row['c'];
  }
} catch (Throwable $e) { /* table might be empty */ }

// Listing thumbnails (first image)
$thumbStmt = db()->prepare("SELECT listing_id, MIN(id) AS min_id FROM listing_images GROUP BY listing_id");
$thumbStmt->execute();
$thumbRows = $thumbStmt->fetchAll();
$thumbMinByListing = [];
foreach ($thumbRows as $tr) {
  $thumbMinByListing[(int)$tr['listing_id']] = (int)$tr['min_id'];
}
$thumbsByListing = [];
if ($thumbMinByListing) {
  $ids = array_values($thumbMinByListing);
  $in = implode(',', array_fill(0, count($ids), '?'));
  $imgStmt = db()->prepare("SELECT id, listing_id, file_path FROM listing_images WHERE id IN ($in)");
  $imgStmt->execute($ids);
  foreach ($imgStmt->fetchAll() as $im) {
    $thumbsByListing[(int)$im['listing_id']] = (string)$im['file_path'];
  }
}

ob_start();
?>
  <section class="hero reveal" aria-label="Welcome">
    <div class="hero-slides" data-hero-slides>

      <article class="hero-slide is-active" data-hero-slide>
        <div class="hero-text">
          <span class="kicker">Tanzania land marketplace</span>
          <h1>Find the <em>right plot</em>, with a clear path to verify it.</h1>
          <p class="lead">Browse approved listings across Tanzania, send enquiries straight to sellers on WhatsApp, and read internal review badges before you commit.</p>
          <div class="hero-cta">
            <a class="btn" href="#browse">Browse approved land</a>
            <a class="btn ghost" href="<?= APP_BASE_URL ?>/how-it-works.php">How it works</a>
          </div>
        </div>
        <div class="hero-art">
          <div class="hero-bg-grid"></div>
          <div class="hero-art-inner">
            <div class="hero-emblem">
              <span class="ring"></span><span class="ring two"></span>
              <span class="hero-emblem-letter">A</span>
            </div>
          </div>
        </div>
      </article>

      <article class="hero-slide" data-hero-slide>
        <div class="hero-text">
          <span class="kicker">For sellers &amp; agents</span>
          <h1>List your land. <em>Reach real buyers.</em></h1>
          <p class="lead">Upload photos and optional documents, pay a small listing fee, and our team reviews everything before it goes live, so your plot stands out as serious.</p>
          <div class="hero-cta">
            <?php if ($canList): ?>
              <a class="btn" href="<?= APP_BASE_URL ?>/submit-listing.php">Submit a listing</a>
            <?php else: ?>
              <a class="btn" href="<?= APP_BASE_URL ?>/register.php?role=seller">List your land</a>
            <?php endif; ?>
            <a class="btn ghost" href="<?= APP_BASE_URL ?>/how-it-works.php#sellers">Seller guide</a>
          </div>
        </div>
        <div class="hero-art">
          <div class="hero-bg-grid"></div>
          <div class="hero-art-inner">
            <div class="hero-emblem">
              <span class="ring"></span><span class="ring two"></span>
              <span class="hero-emblem-letter" style="font-size:clamp(4rem,7vw,7rem);transform:rotate(-4deg)">TZS</span>
            </div>
          </div>
        </div>
      </article>

      <article class="hero-slide" data-hero-slide>
        <div class="hero-text">
          <span class="kicker">Trust &amp; verification</span>
          <h1>Every listing <em>reviewed</em> before it goes public.</h1>
          <p class="lead">We capture documents, set review statuses, and add badges so buyers can see at a glance how far each listing has progressed through our internal checks.</p>
          <div class="hero-cta">
            <a class="btn" href="<?= APP_BASE_URL ?>/how-it-works.php">Read the guide</a>
            <?php if (!$viewer): ?>
              <a class="btn ghost" href="<?= APP_BASE_URL ?>/register.php">Create account</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="hero-art">
          <div class="hero-bg-grid"></div>
          <div class="hero-art-inner">
            <div class="hero-emblem">
              <span class="ring"></span><span class="ring two"></span>
              <span class="hero-emblem-letter" style="font-size:clamp(3.5rem,6vw,5.5rem)">&#10004;</span>
            </div>
          </div>
        </div>
      </article>

    </div>

    <button class="hero-arrow prev" type="button" aria-label="Previous slide" data-hero-prev>
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 6 9 12 15 18"></polyline></svg>
    </button>
    <button class="hero-arrow next" type="button" aria-label="Next slide" data-hero-next>
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg>
    </button>

    <div class="hero-controls" data-hero-dots></div>
  </section>

  <div class="stats stats-2 reveal">
    <div class="stat">
      <div class="n-row">
        <span class="n" data-count="100">0</span><span class="n-suffix">+</span>
      </div>
      <div class="l">Approved listings</div>
    </div>
    <div class="stat">
      <div class="n-row">
        <span class="n" data-count="500">0</span><span class="n-suffix">+</span>
      </div>
      <div class="l">Buyer enquiries</div>
    </div>
  </div>

  <div class="grid reveal" style="align-items:start">
    <div class="col-7">
      <div class="kicker">Why Ardhi Guide</div>
      <h2>Buying land doesn&rsquo;t have to feel <em>like a gamble.</em></h2>
      <p class="lead">Most plots in Tanzania change hands through WhatsApp and word of mouth. We add a structured layer on top of that, one place to browse, review, and follow up.</p>

      <div class="feature-strip">
        <div class="feature">
          <div class="ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"></circle><path d="M12 2a8 8 0 0 0-8 8c0 5 8 12 8 12s8-7 8-12a8 8 0 0 0-8-8Z"></path></svg>
          </div>
          <h3>Regional coverage</h3>
          <p>Listings tagged by region and category so you can narrow down quickly to where you actually want to buy.</p>
        </div>
        <div class="feature gold">
          <div class="ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-12V5l-8-3-8 3v5c0 8 8 12 8 12Z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
          </div>
          <h3>Review &amp; badges</h3>
          <p>Each listing moves through submitted, under review, and approved, with optional verification badges to indicate progress.</p>
        </div>
        <div class="feature terra">
          <div class="ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12a10 10 0 1 1-3-7"></path><path d="M22 4v6h-6"></path></svg>
          </div>
          <h3>Fast follow-up</h3>
          <p>One tap to WhatsApp the seller. Your enquiries are also saved to your account so you can come back later.</p>
        </div>
      </div>
    </div>

    <div class="col-5">
      <div class="search-card" id="search">
        <div class="kicker" style="color:var(--muted)">Search</div>
        <h3 style="margin:.25rem 0 .85rem">Find a plot</h3>
        <form method="get" class="stack" action="<?= APP_BASE_URL ?>/index.php#browse">
          <div>
            <label>Keywords</label>
            <input name="q" value="<?= h($q) ?>" placeholder="Title, location, or size">
          </div>
          <div class="row">
            <div>
              <label>Region</label>
              <select name="region">
                <option value="">All regions</option>
                <?php foreach ($regions as $r): $rv=(string)$r['region']; ?>
                  <option value="<?= h($rv) ?>" <?= $region===$rv?'selected':'' ?>><?= h($rv) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Category</label>
              <select name="category">
                <option value="">Any</option>
                <?php foreach (['residential'=>'Residential','agricultural'=>'Agricultural','commercial'=>'Commercial','industrial'=>'Industrial','other'=>'Other'] as $k=>$v): ?>
                  <option value="<?= h($k) ?>" <?= $category===$k?'selected':'' ?>><?= h($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button class="btn lg" type="submit">Search listings</button>
        </form>
      </div>
    </div>
  </div>

  <?php if ($regionCounts): ?>
  <div class="reveal">
    <div class="section-head">
      <div>
        <div class="kicker">Browse by region</div>
        <h2 style="margin-bottom:.15rem">Where would you like land?</h2>
      </div>
    </div>
    <div class="region-chips">
      <a class="chip <?= $region==='' ? 'is-active' : '' ?>" href="<?= APP_BASE_URL ?>/index.php#browse">All<span class="count"><?= (int)array_sum($regionCounts) ?></span></a>
      <?php foreach ($regionCounts as $rn => $rc): ?>
        <a class="chip <?= $region===$rn ? 'is-active' : '' ?>" href="<?= APP_BASE_URL ?>/index.php?region=<?= urlencode($rn) ?>#browse"><?= h($rn) ?><span class="count"><?= (int)$rc ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div id="browse" style="height:1px"></div>
  <div class="section-head reveal">
    <div>
      <div class="kicker">Browse</div>
      <h2 style="margin-bottom:.15rem">Approved listings</h2>
      <div class="sub" style="font-size:.95rem">Only admin-approved plots appear here. Submissions are reviewed before going public.</div>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
      <?php if (!$viewer): ?>
        <a class="btn ghost" href="<?= APP_BASE_URL ?>/login.php">Login</a>
      <?php endif; ?>
      <?php if ($canList): ?>
        <a class="btn" href="<?= APP_BASE_URL ?>/submit-listing.php">Submit listing</a>
      <?php elseif (!$viewer): ?>
        <a class="btn" href="<?= APP_BASE_URL ?>/register.php?role=seller">Become a seller</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="cards" style="margin-top:1rem">
    <?php if (!$listings): ?>
      <div class="card pad reveal" style="grid-column:1/-1">
        <div class="kicker">Nothing here yet</div>
        <h3 style="margin:.35rem 0 .35rem">No approved listings match your filters.</h3>
        <p class="sub">Try a wider region, clear the search box, or come back soon. New plots are added regularly.</p>
        <div style="margin-top:1rem">
          <a class="btn secondary" href="<?= APP_BASE_URL ?>/index.php">Reset filters</a>
        </div>
      </div>
    <?php endif; ?>
    <?php foreach ($listings as $l): $lid=(int)$l['id']; $thumb=$thumbsByListing[$lid] ?? null; ?>
      <a class="listing reveal" href="<?= APP_BASE_URL ?>/listing.php?id=<?= $lid ?>">
        <div class="thumb">
          <?php if ($thumb): ?>
            <img src="<?= APP_BASE_URL ?>/<?= h(public_file($thumb)) ?>" alt="Listing photo" loading="lazy">
          <?php endif; ?>
          <div class="badges">
            <?php if ((int)$l['is_featured'] === 1): ?>
              <span class="pill warn">Featured</span>
            <?php endif; ?>
          </div>
          <div class="badges right">
            <?php if (($l['verification_badge'] ?? 'none') !== 'none'): ?>
              <span class="pill ok"><?= h((string)$l['verification_badge']) ?></span>
            <?php else: ?>
              <span class="pill neutral">No badge</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="body">
          <div class="meta" style="font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--brand)"><?= h((string)$l['region']) ?></div>
          <div class="title"><?= h((string)$l['title']) ?></div>
          <div class="meta"><?= h(ucfirst((string)$l['category'])) ?><?php if (!empty($l['size_text'])): ?> &middot; <?= h((string)$l['size_text']) ?><?php endif; ?><?php if (!empty($l['location_text'])): ?> &middot; <?= h((string)$l['location_text']) ?><?php endif; ?></div>
          <div class="price"><?= h(format_tzs((string)($l['price_tzs'] ?? ''))) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="marquee reveal" aria-hidden="true">
    <div class="marquee-track">
      <span class="marquee-item">Arusha<span class="sep"></span></span>
      <span class="marquee-item">Dar es Salaam<span class="sep"></span></span>
      <span class="marquee-item">Mwanza<span class="sep"></span></span>
      <span class="marquee-item">Mbeya<span class="sep"></span></span>
      <span class="marquee-item">Dodoma<span class="sep"></span></span>
      <span class="marquee-item">Iringa<span class="sep"></span></span>
      <span class="marquee-item">Tanga<span class="sep"></span></span>
      <span class="marquee-item">Morogoro<span class="sep"></span></span>
      <span class="marquee-item">Zanzibar<span class="sep"></span></span>
      <span class="marquee-item">Kilimanjaro<span class="sep"></span></span>
      <span class="marquee-item">Arusha<span class="sep"></span></span>
      <span class="marquee-item">Dar es Salaam<span class="sep"></span></span>
      <span class="marquee-item">Mwanza<span class="sep"></span></span>
      <span class="marquee-item">Mbeya<span class="sep"></span></span>
      <span class="marquee-item">Dodoma<span class="sep"></span></span>
      <span class="marquee-item">Iringa<span class="sep"></span></span>
      <span class="marquee-item">Tanga<span class="sep"></span></span>
      <span class="marquee-item">Morogoro<span class="sep"></span></span>
      <span class="marquee-item">Zanzibar<span class="sep"></span></span>
      <span class="marquee-item">Kilimanjaro<span class="sep"></span></span>
    </div>
  </div>

  <div class="card pad reveal" style="margin-top:2rem">
    <div class="kicker">Quick instructions</div>
    <h2 style="margin-top:.35rem">Three roles, <em>one workflow.</em></h2>
    <div class="instr-columns" style="margin-top:1rem">
      <div class="instr-card">
        <h3>Buyers</h3>
        <p class="sub" style="margin:0">Search approved listings, open a plot, tap <strong>Enquire</strong>. Log in first if you want enquiries saved under <strong>My enquiries</strong>.</p>
      </div>
      <div class="instr-card">
        <h3>Sellers</h3>
        <p class="sub" style="margin:0">Register as seller, submit details and photos, pay the listing fee when prompted, then wait for admin approval before the public page goes live.</p>
      </div>
      <div class="instr-card">
        <h3>Trust &amp; review</h3>
        <p class="sub" style="margin:0">Statuses and badges reflect <strong>internal review</strong>, not a legal title guarantee. Always verify land with qualified professionals.</p>
      </div>
    </div>
    <div style="margin-top:1.25rem;display:flex;gap:.7rem;flex-wrap:wrap">
      <a class="btn" href="<?= APP_BASE_URL ?>/how-it-works.php">Read the full guide</a>
      <a class="btn ghost" href="#browse">Back to listings</a>
    </div>
  </div>

  <section class="cta-banner reveal">
    <div>
      <div class="kicker" style="color:var(--gold-100);letter-spacing:.28em">Ready to start?</div>
      <h2>Got a plot to sell? <em>Reach buyers today.</em></h2>
      <p>Create a free account, submit your land with photos and optional documents, and our team will review it before publishing. Pay your listing fee with M-Pesa and follow our published payment guide.</p>
    </div>
    <div class="btns">
      <?php if ($canList): ?>
        <a class="btn" href="<?= APP_BASE_URL ?>/submit-listing.php">Submit a listing</a>
        <a class="btn ghost" href="<?= APP_BASE_URL ?>/my-listings.php">My listings</a>
      <?php elseif ($viewer): ?>
        <a class="btn" href="<?= APP_BASE_URL ?>/how-it-works.php">How it works</a>
        <a class="btn ghost" href="#browse">Keep browsing</a>
      <?php else: ?>
        <a class="btn" href="<?= APP_BASE_URL ?>/register.php?role=seller">Create seller account</a>
        <a class="btn ghost" href="<?= APP_BASE_URL ?>/login.php">Login</a>
      <?php endif; ?>
    </div>
  </section>
<?php
$content = ob_get_clean();
$title = 'Ardhi Guide. Tanzania land marketplace';
require __DIR__ . '/_layout.php';
