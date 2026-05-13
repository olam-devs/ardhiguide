<?php

declare(strict_types=1);

function session_start_safe(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name(SESSION_NAME);
  session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
  ]);
}

function flash_set(string $key, string $msg): void {
  session_start_safe();
  $_SESSION['flash'][$key] = $msg;
}

function flash_get(string $key): ?string {
  session_start_safe();
  if (!isset($_SESSION['flash'][$key])) return null;
  $msg = (string)$_SESSION['flash'][$key];
  unset($_SESSION['flash'][$key]);
  return $msg;
}

