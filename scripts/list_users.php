<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$rows = db()->query('SELECT id, email, full_name, role, is_active, created_at FROM users ORDER BY id ASC')->fetchAll();

echo str_pad('ID', 4) . ' | ' . str_pad('ROLE', 8) . ' | ' . str_pad('EMAIL', 36) . ' | ' . 'NAME' . PHP_EOL;
echo str_repeat('-', 80) . PHP_EOL;
foreach ($rows as $r) {
  echo str_pad((string)$r['id'], 4)
    . ' | ' . str_pad((string)$r['role'], 8)
    . ' | ' . str_pad((string)$r['email'], 36)
    . ' | ' . (string)$r['full_name']
    . PHP_EOL;
}
echo PHP_EOL . 'Total users: ' . count($rows) . PHP_EOL;
