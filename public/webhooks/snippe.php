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

$timestamp = snippe_request_header('X-Webhook-Timestamp');
$signature = snippe_request_header('X-Webhook-Signature');
$headerEvent = snippe_request_header('X-Webhook-Event');

if (!snippe_verify_webhook_signature($rawBody, $timestamp, $signature)) {
  error_log('Snippe webhook: invalid signature (check SNIPPE_WEBHOOK_SECRET matches Snippe dashboard)');
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
  if ($headerEvent !== null && $headerEvent !== '' && empty($payload['type'])) {
    $payload['type'] = $headerEvent;
  }
  snippe_process_webhook_payload($payload, $rawBody);
} catch (Throwable $e) {
  error_log('Snippe webhook error: ' . $e->getMessage());
}
