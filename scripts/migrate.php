<?php

declare(strict_types=1);

/**
 * Apply database/migration_*.sql files in order, one statement at a time.
 * Skips errors like duplicate column / existing constraint so re-runs are safe.
 *
 * Usage: php scripts/migrate.php
 */
$root = dirname(__DIR__);
require $root . '/app/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
$mysqli->set_charset('utf8mb4');

/**
 * @return list<string>
 */
function split_sql_statements(string $sql): array {
  $buf = '';
  $out = [];
  foreach (explode("\n", $sql) as $line) {
    if (preg_match('/^\s*--/', $line)) {
      continue;
    }
    $buf .= $line . "\n";
    $t = trim($line);
    if ($t !== '' && str_ends_with($t, ';')) {
      $stmt = trim(rtrim($buf, " \t\n\r\0\x0B;"));
      if ($stmt !== '') {
        $out[] = $stmt;
      }
      $buf = '';
    }
  }
  $stmt = trim($buf);
  if ($stmt !== '') {
    $out[] = $stmt;
  }
  return $out;
}

$files = glob($root . '/database/migration_*.sql') ?: [];
sort($files);

foreach ($files as $file) {
  echo '==> ' . basename($file) . PHP_EOL;
  $sql = (string)file_get_contents($file);
  $stmts = split_sql_statements($sql);
  foreach ($stmts as $stmt) {
    try {
      $mysqli->query($stmt);
    } catch (mysqli_sql_exception $e) {
      $msg = $e->getMessage();
      $code = (int)$e->getCode();
      if (
        $code === 1060
        || $code === 1061
        || $code === 1022
        || $code === 121
        || $code === 1826
        || str_contains($msg, 'Duplicate column')
        || str_contains($msg, 'Duplicate key name')
        || str_contains($msg, 'already exists')
        || str_contains($msg, 'Duplicate foreign key')
        || str_contains($msg, 'Duplicate key on write')
        || str_contains($msg, 'errno: 121')
      ) {
        echo '   (skip) ' . $msg . PHP_EOL;
        continue;
      }
      echo '   ERROR: ' . $msg . PHP_EOL;
    }
  }
}

echo 'Done.' . PHP_EOL;
