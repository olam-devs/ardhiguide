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
        <?php if ($u): ?>
          <a href="<?= APP_BASE_URL ?>/my-account.php">My account</a>
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

      <div class="contact-buttons">
        <a class="contact-btn phone lg" href="tel:+255657925368">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92V21a1 1 0 0 1-1.11 1A19 19 0 0 1 2 4.11 1 1 0 0 1 3 3h4.09a1 1 0 0 1 1 .75l1 4a1 1 0 0 1-.29 1L7 10.5a16 16 0 0 0 6.5 6.5l1.75-1.8a1 1 0 0 1 1-.29l4 1a1 1 0 0 1 .75 1z"/></svg>
          Call +255 657 925 368
        </a>
        <a class="contact-btn wa lg" href="https://wa.me/255657925368" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163A11.867 11.867 0 0 1 .096 11.86C.099 5.334 5.43.003 11.954.003a11.815 11.815 0 0 1 8.413 3.488 11.821 11.821 0 0 1 3.48 8.414c-.003 6.526-5.335 11.857-11.86 11.857a11.9 11.9 0 0 1-5.674-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>
          Chat on WhatsApp
        </a>
      </div>

      <div class="footer-copy">&copy; <?= date('Y') ?> Ardhi Guide. Dar es Salaam, Tanzania.</div>
    </div>
  </footer>

  <?php $__jsVer = @filemtime(__DIR__ . '/assets/app.js') ?: time(); ?>
  <script src="<?= APP_BASE_URL ?>/assets/app.js?v=<?= (int)$__jsVer ?>" defer></script>
</body>
</html>
