<?php

declare(strict_types=1);

function env_load(string $path): array {
  if (!file_exists($path)) {
    return [];
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $env = [];

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;

    $pos = strpos($line, '=');
    if ($pos === false) continue;

    $key = trim(substr($line, 0, $pos));
    $val = trim(substr($line, $pos + 1));

    if (strlen($val) >= 2 && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
      $val = substr($val, 1, -1);
    }

    $env[$key] = $val;
  }

  return $env;
}

$ENV = env_load(__DIR__ . '/.env');

function cfg(string $key, ?string $default = null): ?string {
  global $ENV;
  if (array_key_exists($key, $ENV)) return $ENV[$key];
  $v = getenv($key);
  return ($v === false) ? $default : (string)$v;
}

define('APP_NAME', cfg('APP_NAME', 'Ardhi Way'));
define('APP_BASE_URL', rtrim((string)cfg('APP_BASE_URL', ''), '/'));
define('SESSION_NAME', (string)cfg('SESSION_NAME', 'ARDHI_GUIDE_SESSION'));

define('DB_HOST', (string)cfg('DB_HOST', '127.0.0.1'));
define('DB_PORT', (string)cfg('DB_PORT', '3306'));
define('DB_NAME', (string)cfg('DB_NAME', 'ardhi_guide_mvp'));
define('DB_USER', (string)cfg('DB_USER', 'root'));
define('DB_PASS', (string)cfg('DB_PASS', ''));

define('WHATSAPP_DEFAULT_NUMBER', (string)cfg('WHATSAPP_DEFAULT_NUMBER', '255657925368'));

// Legacy env key (unused). Payment copy lives in the database payment guide (Snippe) — see payment_guide.php.

// If true, admin cannot approve listings until payment_status is paid or waived
define('REQUIRE_PAYMENT_FOR_APPROVAL', filter_var((string)cfg('REQUIRE_PAYMENT_FOR_APPROVAL', '0'), FILTER_VALIDATE_BOOLEAN));

// Snippe payment gateway (https://api.snippe.sh)
define('SNIPPE_ENABLED', filter_var((string)cfg('SNIPPE_ENABLED', '0'), FILTER_VALIDATE_BOOLEAN));
define('SNIPPE_API_KEY', (string)cfg('SNIPPE_API_KEY', ''));
define('SNIPPE_API_BASE', rtrim((string)cfg('SNIPPE_API_BASE', 'https://api.snippe.sh'), '/'));
define('SNIPPE_WEBHOOK_SECRET', (string)cfg('SNIPPE_WEBHOOK_SECRET', ''));
