<?php

declare(strict_types=1);

/** Minimum listing fee accepted by Snippe (TZS). */
function snippe_min_amount_tzs(): int {
  return 500;
}

function snippe_enabled(): bool {
  return SNIPPE_ENABLED && SNIPPE_API_KEY !== '';
}

function snippe_webhook_url(): string {
  return APP_BASE_URL . '/webhooks/snippe.php';
}

/** Idempotency-Key must be <= 30 characters (Snippe PAY_001). */
function snippe_idempotency_key(int $listingId): string {
  return 'ag' . $listingId . substr(bin2hex(random_bytes(4)), 0, 8);
}

/**
 * @return array{ok:bool,reference?:string,status?:string,expires_at?:string,payment_url?:string,err?:string,http_code?:int}
 */
function snippe_api_request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array {
  if (!snippe_enabled()) {
    return ['ok' => false, 'err' => 'Snippe payments are not configured on this server.'];
  }

  $url = rtrim(SNIPPE_API_BASE, '/') . $path;
  $headers = [
    'Authorization: Bearer ' . SNIPPE_API_KEY,
    'Accept: application/json',
    'Content-Type: application/json',
  ];
  if ($idempotencyKey !== null && $idempotencyKey !== '') {
    $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
  }

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_CUSTOMREQUEST => strtoupper($method),
    CURLOPT_HTTPHEADER => $headers,
  ]);

  if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
  }

  $raw = curl_exec($ch);
  $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlErr = curl_error($ch);
  curl_close($ch);

  if ($raw === false) {
    return ['ok' => false, 'err' => 'Could not reach Snippe: ' . $curlErr, 'http_code' => $httpCode];
  }

  $decoded = json_decode((string)$raw, true);
  if (!is_array($decoded)) {
    return ['ok' => false, 'err' => 'Invalid response from Snippe.', 'http_code' => $httpCode];
  }

  if (($decoded['status'] ?? '') === 'success' && isset($decoded['data']) && is_array($decoded['data'])) {
    return ['ok' => true] + $decoded['data'] + ['http_code' => $httpCode];
  }

  $msg = (string)($decoded['message'] ?? 'Payment request failed.');
  if (!empty($decoded['error_code'])) {
    $msg .= ' (' . $decoded['error_code'] . ')';
  }
  return ['ok' => false, 'err' => $msg, 'http_code' => $httpCode];
}

/**
 * Build customer block for Snippe from a user row or session user.
 *
 * @param array<string,mixed> $user
 * @return array{firstname:string,lastname:string,email:string}
 */
function snippe_customer_from_user(array $user): array {
  $full = trim((string)($user['full_name'] ?? 'Customer'));
  $parts = preg_split('/\s+/', $full, 2) ?: [];
  $first = $parts[0] !== '' ? $parts[0] : 'Customer';
  $last = $parts[1] ?? 'User';
  $email = trim((string)($user['email'] ?? ''));
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = 'payments@ardhiguide.local';
  }
  return [
    'firstname' => $first,
    'lastname' => $last,
    'email' => $email,
  ];
}

/**
 * @param array<string,mixed> $listing
 * @param array<string,mixed> $payerUser logged-in user paying
 */
function snippe_create_mobile_payment(array $listing, array $payerUser, string $phone): array {
  $listingId = (int)$listing['id'];
  $amount = (int)($listing['payment_amount_tzs'] ?? 0);
  if ($amount < snippe_min_amount_tzs()) {
    return ['ok' => false, 'err' => 'Amount must be at least ' . snippe_min_amount_tzs() . ' TZS.'];
  }

  $phoneNorm = normalize_phone($phone);
  if ($phoneNorm === '' || strlen($phoneNorm) < 9) {
    return ['ok' => false, 'err' => 'Enter a valid phone number for the payment prompt.'];
  }

  $ref = (string)($listing['payment_reference'] ?? '');
  $body = [
    'payment_type' => 'mobile',
    'details' => [
      'amount' => $amount,
      'currency' => 'TZS',
    ],
    'phone_number' => $phoneNorm,
    'customer' => snippe_customer_from_user($payerUser),
    'webhook_url' => snippe_webhook_url(),
    'metadata' => [
      'listing_id' => (string)$listingId,
      'payment_reference' => $ref,
    ],
  ];

  $res = snippe_api_request('POST', '/v1/payments', $body, snippe_idempotency_key($listingId));
  if (!$res['ok']) {
    listing_snippe_mark_failed($listingId, (string)($res['err'] ?? 'Unknown error'));
    return $res;
  }

  $snippeRef = (string)($res['reference'] ?? '');
  listing_snippe_mark_pending($listingId, $snippeRef, $phoneNorm);
  return $res;
}

/**
 * @param array<string,mixed> $listing
 * @param array<string,mixed> $payerUser
 */
function snippe_create_card_payment(array $listing, array $payerUser, ?string $phone = null): array {
  $listingId = (int)$listing['id'];
  $amount = (int)($listing['payment_amount_tzs'] ?? 0);
  if ($amount < snippe_min_amount_tzs()) {
    return ['ok' => false, 'err' => 'Amount must be at least ' . snippe_min_amount_tzs() . ' TZS.'];
  }

  $base = APP_BASE_URL;
  $ref = (string)($listing['payment_reference'] ?? '');
  $body = [
    'payment_type' => 'card',
    'details' => [
      'amount' => $amount,
      'currency' => 'TZS',
      'redirect_url' => $base . '/payment-return.php?listing_id=' . $listingId . '&status=success',
      'cancel_url' => $base . '/payment-return.php?listing_id=' . $listingId . '&status=cancel',
    ],
    'customer' => snippe_customer_from_user($payerUser) + [
      'address' => 'Dar es Salaam',
      'city' => 'Dar es Salaam',
      'state' => 'DSM',
      'postcode' => '14101',
      'country' => 'TZ',
    ],
    'webhook_url' => snippe_webhook_url(),
    'metadata' => [
      'listing_id' => (string)$listingId,
      'payment_reference' => $ref,
    ],
  ];

  if ($phone !== null && $phone !== '') {
    $pn = normalize_phone($phone);
    if ($pn !== '') {
      $body['phone_number'] = $pn;
    }
  }

  $res = snippe_api_request('POST', '/v1/payments', $body, snippe_idempotency_key($listingId));
  if (!$res['ok']) {
    listing_snippe_mark_failed($listingId, (string)($res['err'] ?? 'Unknown error'));
    return $res;
  }

  $snippeRef = (string)($res['reference'] ?? '');
  listing_snippe_mark_pending($listingId, $snippeRef, null);
  return $res;
}

function listing_snippe_mark_pending(int $listingId, string $snippeReference, ?string $pushPhoneUsed): void {
  $sql = 'UPDATE listings SET snippe_reference = ?, snippe_status = ?, snippe_last_error = NULL';
  $params = [$snippeReference, 'pending'];
  if ($pushPhoneUsed !== null) {
    $sql .= ', payment_push_phone = ?';
    $params[] = $pushPhoneUsed;
  }
  $sql .= ' WHERE id = ?';
  $params[] = $listingId;
  db()->prepare($sql)->execute($params);
}

function listing_snippe_mark_failed(int $listingId, string $error): void {
  $err = strlen($error) > 250 ? substr($error, 0, 250) : $error;
  db()->prepare('UPDATE listings SET snippe_status = ?, snippe_last_error = ? WHERE id = ?')
    ->execute(['failed', $err, $listingId]);
}

function listing_snippe_mark_completed(int $listingId): void {
  db()->prepare("UPDATE listings SET snippe_status = 'completed', snippe_last_error = NULL WHERE id = ?")
    ->execute([$listingId]);
  listing_mark_paid($listingId);
}

function listing_snippe_mark_expired(int $listingId): void {
  db()->prepare("UPDATE listings SET snippe_status = 'expired' WHERE id = ?")->execute([$listingId]);
}

/**
 * Resolve listing id from webhook payload (2026 envelope or legacy flat).
 *
 * @param array<string,mixed> $payload
 */
function snippe_listing_id_from_payload(array $payload): ?int {
  $data = $payload['data'] ?? $payload;
  if (!is_array($data)) {
    return null;
  }
  $meta = $data['metadata'] ?? [];
  if (is_array($meta) && isset($meta['listing_id'])) {
    return (int)$meta['listing_id'];
  }
  return null;
}

function snippe_reference_from_payload(array $payload): ?string {
  $data = $payload['data'] ?? $payload;
  if (!is_array($data)) {
    return null;
  }
  $ref = $data['reference'] ?? null;
  return $ref !== null ? (string)$ref : null;
}

function snippe_event_type_from_payload(array $payload, ?string $headerEvent): ?string {
  if ($headerEvent !== null && $headerEvent !== '') {
    return $headerEvent;
  }
  if (!empty($payload['type'])) {
    return (string)$payload['type'];
  }
  if (!empty($payload['event'])) {
    return (string)$payload['event'];
  }
  return null;
}

function snippe_event_id_from_payload(array $payload): string {
  if (!empty($payload['id'])) {
    return (string)$payload['id'];
  }
  $ref = snippe_reference_from_payload($payload) ?? 'unknown';
  $type = snippe_event_type_from_payload($payload, null) ?? 'event';
  return $type . ':' . $ref . ':' . ($payload['created_at'] ?? time());
}

/**
 * Verify Snippe webhook HMAC. Returns true if valid or if secret not configured (dev only).
 */
function snippe_verify_webhook_signature(string $rawBody, ?string $timestamp, ?string $signature): bool {
  if (SNIPPE_WEBHOOK_SECRET === '') {
    return true;
  }
  if ($timestamp === null || $signature === null || $signature === '') {
    return false;
  }
  $ts = (int)$timestamp;
  if ($ts > 0 && abs(time() - $ts) > 300) {
    return false;
  }
  $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, SNIPPE_WEBHOOK_SECRET);
  return hash_equals($expected, $signature);
}

/**
 * Process a verified webhook payload. Returns true if handled.
 */
function snippe_process_webhook_payload(array $payload, string $rawJson): bool {
  $eventId = snippe_event_id_from_payload($payload);
  $eventType = snippe_event_type_from_payload($payload, null) ?? 'unknown';
  $snippeRef = snippe_reference_from_payload($payload);
  $listingId = snippe_listing_id_from_payload($payload);

  $chk = db()->prepare('SELECT id FROM snippe_webhook_events WHERE event_id = ? LIMIT 1');
  $chk->execute([$eventId]);
  if ($chk->fetch()) {
    return true;
  }

  db()->prepare(
    'INSERT INTO snippe_webhook_events (event_id, event_type, snippe_reference, listing_id, payload_json)
     VALUES (?,?,?,?,?)'
  )->execute([$eventId, $eventType, $snippeRef, $listingId > 0 ? $listingId : null, $rawJson]);

  if ($listingId === null || $listingId <= 0) {
    if ($snippeRef !== null) {
      $st = db()->prepare('SELECT id FROM listings WHERE snippe_reference = ? LIMIT 1');
      $st->execute([$snippeRef]);
      $row = $st->fetch();
      if ($row) {
        $listingId = (int)$row['id'];
      }
    }
  }

  if ($listingId === null || $listingId <= 0) {
    return false;
  }

  if ($eventType === 'payment.completed') {
    listing_snippe_mark_completed($listingId);
  } elseif (in_array($eventType, ['payment.failed', 'payment.voided'], true)) {
    listing_snippe_mark_failed($listingId, 'Payment ' . str_replace('payment.', '', $eventType));
  } elseif ($eventType === 'payment.expired') {
    listing_snippe_mark_expired($listingId);
  } else {
    return true;
  }

  db()->prepare('UPDATE snippe_webhook_events SET processed_at = NOW() WHERE event_id = ?')
    ->execute([$eventId]);
  return true;
}

/** Phone to use for Snippe push: admin-assigned if enabled, else user input. */
function listing_payment_push_phone(array $listing, ?string $userInput = null): ?string {
  if ((int)($listing['payment_push_enabled'] ?? 0) === 1) {
    $assigned = trim((string)($listing['payment_push_phone'] ?? ''));
    if ($assigned !== '') {
      return normalize_phone($assigned) ?: null;
    }
  }
  if ($userInput !== null && trim($userInput) !== '') {
    $n = normalize_phone($userInput);
    return $n !== '' ? $n : null;
  }
  return null;
}
