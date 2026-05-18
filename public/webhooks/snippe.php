<?php

declare(strict_types=1);

/**
 * Snippe webhook endpoint. Must receive the raw JSON body for signature verification.
 * URL: {APP_BASE_URL}/webhooks/snippe.php
 */

require_once __DIR__ . '/../../app/bootstrap.php';

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
  http_response_code(400);
  echo 'Empty body';
  exit;
}

$timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? null;
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? null;

if (!snippe_verify_webhook_signature($rawBody, $timestamp, $signature)) {
  http_response_code(400);
  echo 'Invalid signature';
  exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
  http_response_code(400);
  echo 'Invalid JSON';
  exit;
}

http_response_code(200);
header('Content-Type: text/plain; charset=UTF-8');
echo 'OK';

if (function_exists('fastcgi_finish_request')) {
  fastcgi_finish_request();
} else {
  if (ob_get_level()) {
    ob_end_flush();
  }
  flush();
}

try {
  snippe_process_webhook_payload($payload, $rawBody);
} catch (Throwable $e) {
  error_log('Snippe webhook error: ' . $e->getMessage());
}
