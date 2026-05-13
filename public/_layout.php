<?php
/** @var string $title */
/** @var string $content */
require_once __DIR__ . '/../app/bootstrap.php';
session_start_safe();
$u = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;600;700;800&family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,800;0,900;1,500;1,700;1,800&display=swap" rel="stylesheet">
  <?php $__cssVer = @filemtime(__DIR__ . '/assets/app.css') ?: time(); ?>
  <link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/app.css?v=<?= (int)$__cssVer ?>">
  <link rel="icon" type="image/svg+xml" href="<?= APP_BASE_URL ?>/assets/favicon.svg">
</head>
<body>
  <div class="topbar">
    <div class="topbar-inner">
      <div><span class="dot"></span>Dar es Salaam, Tanzania &nbsp;&middot;&nbsp; Verified land marketplace</div>
      <div style="display:flex;gap:1.1rem;flex-wrap:wrap;align-items:center">
        <a href="tel:+255657925368">+255 657 925 368</a>
        <a href="https://wa.me/255657925368" target="_blank" rel="noopener">WhatsApp us</a>
        <a href="<?= APP_BASE_URL ?>/how-it-works.php">Guide</a>
      </div>
    </div>
  </div>

  <div class="nav">
    <div class="nav-inner">
      <a class="brand" href="<?= APP_BASE_URL ?>/index.php" aria-label="Ardhi Guide home">
        <span class="mark" aria-hidden="true">
          <svg viewBox="0 0 44 44" width="44" height="44">
            <defs>
              <linearGradient id="markBg" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#0E5C4A"/>
                <stop offset="100%" stop-color="#062A21"/>
              </linearGradient>
              <linearGradient id="markA" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#F6E7C4"/>
                <stop offset="100%" stop-color="#D4A24C"/>
              </linearGradient>
            </defs>
            <rect x="1" y="1" width="42" height="42" rx="11" fill="url(#markBg)" stroke="rgba(212,162,76,.35)"/>
            <path d="M11 33 L21.5 9 L32 33" fill="none" stroke="url(#markA)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M15.5 25 L27.5 25" fill="none" stroke="url(#markA)" stroke-width="3" stroke-linecap="round"/>
            <circle cx="21.5" cy="37" r="1.4" fill="#D4A24C"/>
          </svg>
        </span>
        <span class="brand-text">
          <strong>Ardhi Guide</strong>
          <span class="brand-tag">Tanzania land</span>
        </span>
      </a>
      <div class="nav-links">
        <a href="<?= APP_BASE_URL ?>/index.php">Browse</a>
        <a href="<?= APP_BASE_URL ?>/how-it-works.php">How it works</a>
        <a href="<?= APP_BASE_URL ?>/payment-instructions.php">Payment guide</a>
        <?php if ($u && in_array(($u['role'] ?? ''), ['seller', 'agent', 'admin'], true)): ?>
          <a href="<?= APP_BASE_URL ?>/submit-listing.php">Submit listing</a>
        <?php endif; ?>
        <?php if ($u && in_array(($u['role'] ?? ''), ['seller', 'agent', 'admin'], true)): ?>
          <a href="<?= APP_BASE_URL ?>/my-listings.php">My listings</a>
        <?php endif; ?>
        <?php if ($u): ?>
          <a href="<?= APP_BASE_URL ?>/my-enquiries.php">My enquiries</a>
        <?php endif; ?>
        <?php if ($u && ($u['role'] ?? '') === 'admin'): ?>
          <a href="<?= APP_BASE_URL ?>/admin/listings.php" class="cta">Admin</a>
        <?php endif; ?>
        <?php if ($u): ?>
          <a href="<?= APP_BASE_URL ?>/logout.php">Logout (<?= h($u['full_name'] ?? 'User') ?>)</a>
        <?php else: ?>
          <a href="<?= APP_BASE_URL ?>/register.php">Register</a>
          <a href="<?= APP_BASE_URL ?>/login.php" class="cta">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="wrap">
    <?php $ok = flash_get('ok'); $err = flash_get('err'); ?>
    <?php if ($ok): ?><div class="flash"><?= h($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="flash danger"><?= h($err) ?></div><?php endif; ?>
    <?= $content ?>
  </div>

  <footer>
    <div class="footer-inner footer-center">
      <a class="footer-brand-link" href="<?= APP_BASE_URL ?>/index.php" aria-label="Ardhi Guide home">
        <span class="mark mark-lg" aria-hidden="true">
          <svg viewBox="0 0 44 44" width="56" height="56">
            <defs>
              <linearGradient id="fmarkBg" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#0E5C4A"/>
                <stop offset="100%" stop-color="#062A21"/>
              </linearGradient>
              <linearGradient id="fmarkA" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#F6E7C4"/>
                <stop offset="100%" stop-color="#D4A24C"/>
              </linearGradient>
            </defs>
            <rect x="1" y="1" width="42" height="42" rx="11" fill="url(#fmarkBg)" stroke="rgba(212,162,76,.35)"/>
            <path d="M11 33 L21.5 9 L32 33" fill="none" stroke="url(#fmarkA)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M15.5 25 L27.5 25" fill="none" stroke="url(#fmarkA)" stroke-width="3" stroke-linecap="round"/>
            <circle cx="21.5" cy="37" r="1.4" fill="#D4A24C"/>
          </svg>
        </span>
        <span class="footer-brand">Ardhi Guide</span>
      </a>
      <p class="footer-desc">A Tanzania land marketplace. Browse approved plots, send enquiries on WhatsApp, list your land with optional verification documents.</p>

      <nav class="footer-links" aria-label="Footer navigation">
        <a href="<?= APP_BASE_URL ?>/index.php">Browse</a>
        <a href="<?= APP_BASE_URL ?>/how-it-works.php">How it works</a>
        <a href="<?= APP_BASE_URL ?>/payment-instructions.php">Payment guide</a>
        <a href="<?= APP_BASE_URL ?>/register.php?role=seller">List your land</a>
        <a href="<?= APP_BASE_URL ?>/login.php">Login</a>
        <a href="<?= APP_BASE_URL ?>/register.php">Register</a>
      </nav>

      <div class="footer-contact">
        <a href="tel:+255657925368">+255 657 925 368</a>
        <span class="footer-sep" aria-hidden="true"></span>
        <a href="https://wa.me/255657925368" target="_blank" rel="noopener">WhatsApp</a>
      </div>

      <div class="footer-copy">&copy; <?= date('Y') ?> Ardhi Guide. Dar es Salaam, Tanzania.</div>
    </div>
  </footer>

  <?php $__jsVer = @filemtime(__DIR__ . '/assets/app.js') ?: time(); ?>
  <script src="<?= APP_BASE_URL ?>/assets/app.js?v=<?= (int)$__jsVer ?>" defer></script>
</body>
</html>
