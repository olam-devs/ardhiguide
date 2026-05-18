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
function snippe_idempotency_key(int $listingId, string $kind = LISTING_PAY_LISTING_FEE, bool $freshAttempt = false): string {
  $prefix = $kind === LISTING_PAY_LAND ? 'agL' : 'agF';
  $base = $prefix . $listingId;
  if (!$freshAttempt) {
    return substr($base . 'P', 0, 30);
  }
  return substr($base . substr(bin2hex(random_bytes(4)), 0, 30 - strlen($base)), 0, 30);
}

function snippe_listing_snippe_status(array $listing, string $kind): string {
  return $kind === LISTING_PAY_LAND
    ? (string)($listing['land_snippe_status'] ?? 'none')
    : (string)($listing['snippe_status'] ?? 'none');
}

function snippe_listing_snippe_reference(array $listing, string $kind): string {
  return $kind === LISTING_PAY_LAND
    ? (string)($listing['land_snippe_reference'] ?? '')
    : (string)($listing['snippe_reference'] ?? '');
}

function snippe_listing_is_paid(array $listing, string $kind): bool {
  if ($kind === LISTING_PAY_LAND) {
    return (string)($listing['land_payment_status'] ?? '') === 'paid';
  }
  return in_array((string)($listing['payment_status'] ?? ''), ['paid', 'waived'], true);
}

function snippe_reload_listing(int $listingId): ?array {
  $st = db()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
  $st->execute([$listingId]);
  $row = $st->fetch();
  return $row ?: null;
}

/**
 * Block a second payment while one is pending or already paid. Syncs pending with Snippe first.
 *
 * @return string|null Error message, or null if a new payment may be started
 */
function snippe_prepare_new_payment(int $listingId, string $kind): ?string {
  $listing = snippe_reload_listing($listingId);
  if (!$listing) {
    return 'Listing not found.';
  }

  if (snippe_listing_is_paid($listing, $kind)) {
    return $kind === LISTING_PAY_LAND
      ? 'Payment for this plot has already been received.'
      : 'This listing fee is already settled.';
  }

  $status = snippe_listing_snippe_status($listing, $kind);
  if ($status === 'pending' && snippe_enabled()) {
    snippe_sync_listing_payment($listingId, $kind);
    $listing = snippe_reload_listing($listingId) ?? $listing;
    if (snippe_listing_is_paid($listing, $kind)) {
      return $kind === LISTING_PAY_LAND
        ? 'Payment for this plot has already been received.'
        : 'This listing fee is already settled.';
    }
    $status = snippe_listing_snippe_status($listing, $kind);
  }

  if ($status === 'pending') {
    return 'A payment is already in progress. Use “Start over” on the pay page if the phone prompt did not work.';
  }

  return null;
}

/** After failed/expired, clear Snippe reference so the next attempt is a new payment. */
function snippe_normalize_for_retry(int $listingId, string $kind): void {
  if ($kind === LISTING_PAY_LAND) {
    db()->prepare(
      "UPDATE listings SET land_snippe_status = 'none', land_snippe_reference = NULL
       WHERE id = ? AND land_payment_status = 'pending' AND land_snippe_status IN ('failed', 'expired')"
    )->execute([$listingId]);
    return;
  }
  db()->prepare(
    "UPDATE listings SET snippe_status = 'none', snippe_reference = NULL
     WHERE id = ? AND payment_status = 'pending' AND snippe_status IN ('failed', 'expired')"
  )->execute([$listingId]);
}

/** Reserve the payment slot in DB so two parallel requests cannot both call Snippe. */
function snippe_claim_payment_slot(int $listingId, string $kind): bool {
  if ($kind === LISTING_PAY_LAND) {
    $sql = "UPDATE listings SET land_snippe_status = 'pending', land_snippe_reference = NULL, land_snippe_last_error = NULL
            WHERE id = ? AND land_payment_status = 'pending'
            AND land_snippe_status IN ('none', 'failed', 'expired')";
  } else {
    $sql = "UPDATE listings SET snippe_status = 'pending', snippe_reference = NULL, snippe_last_error = NULL
            WHERE id = ? AND payment_status = 'pending'
            AND snippe_status IN ('none', 'failed', 'expired')";
  }
  $st = db()->prepare($sql);
  $st->execute([$listingId]);
  return $st->rowCount() > 0;
}

/** Undo claim when Snippe API fails before a reference is stored. */
function snippe_release_payment_claim(int $listingId, string $kind): void {
  if ($kind === LISTING_PAY_LAND) {
    db()->prepare(
      "UPDATE listings SET land_snippe_status = 'none'
       WHERE id = ? AND land_snippe_status = 'pending'
       AND (land_snippe_reference IS NULL OR land_snippe_reference = '')"
    )->execute([$listingId]);
    return;
  }
  db()->prepare(
    "UPDATE listings SET snippe_status = 'none'
     WHERE id = ? AND snippe_status = 'pending'
     AND (snippe_reference IS NULL OR snippe_reference = '')"
  )->execute([$listingId]);
}

function snippe_payment_kind_from_payload(array $payload): string {
  $data = $payload['data'] ?? $payload;
  if (!is_array($data)) {
    return LISTING_PAY_LISTING_FEE;
  }
  $meta = $data['metadata'] ?? [];
  if (is_array($meta) && ($meta['payment_kind'] ?? '') === LISTING_PAY_LAND) {
    return LISTING_PAY_LAND;
  }
  return LISTING_PAY_LISTING_FEE;
}

/**
 * @return array{ok:bool,reference?:string,status?:string,expires_at?:string,payment_url?:string,err?:string,http_code?:int}
 */
function snippe_api_request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array {
  if (!snippe_enabled()) {
    return ['ok' => false, 'err' => 'Online payments are not configured on this server.'];
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
    return ['ok' => false, 'err' => 'Could not reach the payment service. Try again shortly.', 'http_code' => $httpCode];
  }

  $decoded = json_decode((string)$raw, true);
  if (!is_array($decoded)) {
    return ['ok' => false, 'err' => 'Invalid response from the payment service. Try again.', 'http_code' => $httpCode];
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
function snippe_create_mobile_payment(array $listing, array $payerUser, string $phone, string $kind = LISTING_PAY_LISTING_FEE): array {
  $listingId = (int)$listing['id'];

  $block = snippe_prepare_new_payment($listingId, $kind);
  if ($block !== null) {
    return ['ok' => false, 'err' => $block];
  }

  $listing = snippe_reload_listing($listingId) ?? $listing;
  $priorStatus = snippe_listing_snippe_status($listing, $kind);
  if (in_array($priorStatus, ['failed', 'expired'], true)) {
    snippe_normalize_for_retry($listingId, $kind);
    $listing = snippe_reload_listing($listingId) ?? $listing;
  }
  $freshKey = in_array($priorStatus, ['failed', 'expired'], true);
  if (!snippe_claim_payment_slot($listingId, $kind)) {
    $block = snippe_prepare_new_payment($listingId, $kind);
    return ['ok' => false, 'err' => $block ?? 'A payment is already in progress. Please wait.'];
  }

  $amount = $kind === LISTING_PAY_LAND
    ? (int)($listing['land_payment_amount_tzs'] ?? 0)
    : (int)($listing['payment_amount_tzs'] ?? 0);
  if ($amount < snippe_min_amount_tzs()) {
    snippe_release_payment_claim($listingId, $kind);
    return ['ok' => false, 'err' => 'Amount must be at least ' . snippe_min_amount_tzs() . ' TZS.'];
  }

  $phoneNorm = snippe_format_phone($phone);
  if ($phoneNorm === '' || strlen($phoneNorm) < 12) {
    return ['ok' => false, 'err' => 'Enter a valid phone number for the payment prompt.'];
  }

  $ref = $kind === LISTING_PAY_LAND
    ? (string)($listing['land_payment_reference'] ?? '')
    : (string)($listing['payment_reference'] ?? '');
  $extRef = $ref !== '' ? $ref : ('LISTING-' . $listingId);
  if ($freshKey) {
    $extRef .= '-R' . substr((string)time(), -8);
  }
  $body = [
    'payment_type' => 'mobile',
    'details' => [
      'amount' => $amount,
      'currency' => 'TZS',
    ],
    'phone_number' => $phoneNorm,
    'customer' => snippe_customer_from_user($payerUser),
    'webhook_url' => snippe_webhook_url(),
    'external_reference' => $extRef,
    'metadata' => [
      'listing_id' => (string)$listingId,
      'payment_reference' => $ref,
      'payment_kind' => $kind,
    ],
  ];

  $res = snippe_api_request('POST', '/v1/payments', $body, snippe_idempotency_key($listingId, $kind, $freshKey));
  if (!$res['ok']) {
    snippe_release_payment_claim($listingId, $kind);
    listing_snippe_mark_failed($listingId, (string)($res['err'] ?? 'Unknown error'), $kind);
    return $res;
  }

  $snippeRef = (string)($res['reference'] ?? '');
  listing_snippe_mark_pending($listingId, $snippeRef, $phoneNorm, $kind);
  return $res;
}

/**
 * @param array<string,mixed> $listing
 * @param array<string,mixed> $payerUser
 */
function snippe_create_card_payment(array $listing, array $payerUser, ?string $phone = null, string $kind = LISTING_PAY_LISTING_FEE): array {
  $listingId = (int)$listing['id'];

  $block = snippe_prepare_new_payment($listingId, $kind);
  if ($block !== null) {
    return ['ok' => false, 'err' => $block];
  }

  $listing = snippe_reload_listing($listingId) ?? $listing;
  $priorStatus = snippe_listing_snippe_status($listing, $kind);
  if (in_array($priorStatus, ['failed', 'expired'], true)) {
    snippe_normalize_for_retry($listingId, $kind);
    $listing = snippe_reload_listing($listingId) ?? $listing;
  }
  $freshKey = in_array($priorStatus, ['failed', 'expired'], true);
  if (!snippe_claim_payment_slot($listingId, $kind)) {
    $block = snippe_prepare_new_payment($listingId, $kind);
    return ['ok' => false, 'err' => $block ?? 'A payment is already in progress. Please wait.'];
  }

  $amount = $kind === LISTING_PAY_LAND
    ? (int)($listing['land_payment_amount_tzs'] ?? 0)
    : (int)($listing['payment_amount_tzs'] ?? 0);
  if ($amount < snippe_min_amount_tzs()) {
    snippe_release_payment_claim($listingId, $kind);
    return ['ok' => false, 'err' => 'Amount must be at least ' . snippe_min_amount_tzs() . ' TZS.'];
  }

  $base = APP_BASE_URL;
  $forQs = $kind === LISTING_PAY_LAND ? '&for=land' : '';
  $ref = $kind === LISTING_PAY_LAND
    ? (string)($listing['land_payment_reference'] ?? '')
    : (string)($listing['payment_reference'] ?? '');
  $body = [
    'payment_type' => 'card',
    'details' => [
      'amount' => $amount,
      'currency' => 'TZS',
      'redirect_url' => $base . '/payment-return.php?listing_id=' . $listingId . '&status=success' . $forQs,
      'cancel_url' => $base . '/payment-return.php?listing_id=' . $listingId . '&status=cancel' . $forQs,
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
      'payment_kind' => $kind,
    ],
  ];

  if ($phone !== null && $phone !== '') {
    $pn = snippe_format_phone($phone);
    if ($pn !== '') {
      $body['phone_number'] = $pn;
    }
  }

  $extRef = $ref !== '' ? $ref : ('LISTING-' . $listingId);
  if ($freshKey) {
    $extRef .= '-R' . substr((string)time(), -8);
  }
  $body['external_reference'] = $extRef;

  $res = snippe_api_request('POST', '/v1/payments', $body, snippe_idempotency_key($listingId, $kind, $freshKey));
  if (!$res['ok']) {
    snippe_release_payment_claim($listingId, $kind);
    listing_snippe_mark_failed($listingId, (string)($res['err'] ?? 'Unknown error'), $kind);
    return $res;
  }

  $snippeRef = (string)($res['reference'] ?? '');
  listing_snippe_mark_pending($listingId, $snippeRef, null, $kind);
  return $res;
}

function listing_snippe_mark_pending(int $listingId, string $snippeReference, ?string $pushPhoneUsed, string $kind = LISTING_PAY_LISTING_FEE): void {
  if ($kind === LISTING_PAY_LAND) {
    db()->prepare(
      'UPDATE listings SET land_snippe_reference = ?, land_snippe_status = ?, land_snippe_last_error = NULL WHERE id = ?'
    )->execute([$snippeReference, 'pending', $listingId]);
    if ($pushPhoneUsed !== null) {
      db()->prepare('UPDATE listings SET land_payment_push_phone = ? WHERE id = ?')
        ->execute([$pushPhoneUsed, $listingId]);
    }
    return;
  }
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

function listing_snippe_mark_failed(int $listingId, string $error, string $kind = LISTING_PAY_LISTING_FEE): void {
  $err = strlen($error) > 250 ? substr($error, 0, 250) : $error;
  if ($kind === LISTING_PAY_LAND) {
    db()->prepare('UPDATE listings SET land_snippe_status = ?, land_snippe_last_error = ? WHERE id = ?')
      ->execute(['failed', $err, $listingId]);
    return;
  }
  db()->prepare('UPDATE listings SET snippe_status = ?, snippe_last_error = ? WHERE id = ?')
    ->execute(['failed', $err, $listingId]);
}

function listing_snippe_mark_completed(int $listingId, string $kind = LISTING_PAY_LISTING_FEE): void {
  if ($kind === LISTING_PAY_LAND) {
    listing_land_mark_paid($listingId);
    return;
  }
  db()->prepare("UPDATE listings SET snippe_status = 'completed', snippe_last_error = NULL WHERE id = ?")
    ->execute([$listingId]);
  listing_mark_paid($listingId);
}

function listing_snippe_mark_expired(int $listingId, string $kind = LISTING_PAY_LISTING_FEE): void {
  if ($kind === LISTING_PAY_LAND) {
    db()->prepare(
      "UPDATE listings SET land_snippe_status = 'expired', land_snippe_reference = NULL, land_snippe_last_error = NULL WHERE id = ?"
    )->execute([$listingId]);
    return;
  }
  db()->prepare(
    "UPDATE listings SET snippe_status = 'expired', snippe_reference = NULL, snippe_last_error = NULL WHERE id = ?"
  )->execute([$listingId]);
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
/** Snippe expects 255XXXXXXXXX (no plus). */
function snippe_format_phone(string $raw): string {
  $digits = normalize_phone($raw);
  if ($digits === '') {
    return '';
  }
  if (strlen($digits) === 9) {
    $digits = '255' . $digits;
  }
  return $digits;
}

/** Read webhook header case-insensitively. */
function snippe_request_header(string $name): ?string {
  $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
  if (!empty($_SERVER[$serverKey])) {
    return (string)$_SERVER[$serverKey];
  }
  if (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (is_array($headers)) {
      foreach ($headers as $k => $v) {
        if (strcasecmp((string)$k, $name) === 0) {
          return (string)$v;
        }
      }
    }
  }
  return null;
}

/**
 * @return array{ok:bool,status?:string,expires_at?:string,failure_reason?:string,err?:string,http_code?:int}
 */
function snippe_fetch_payment_status(string $snippeReference): array {
  if ($snippeReference === '') {
    return ['ok' => false, 'err' => 'No payment reference', 'http_code' => 0];
  }
  $res = snippe_api_request('GET', '/v1/payments/' . rawurlencode($snippeReference), null, null);
  if (!$res['ok']) {
    return [
      'ok' => false,
      'err' => (string)($res['err'] ?? 'Could not fetch payment status'),
      'http_code' => (int)($res['http_code'] ?? 0),
    ];
  }
  $status = strtolower((string)($res['status'] ?? ''));
  if ($status === '') {
    return ['ok' => false, 'err' => 'Payment status missing in API response', 'http_code' => (int)($res['http_code'] ?? 200)];
  }
  return [
    'ok' => true,
    'status' => $status,
    'expires_at' => isset($res['expires_at']) ? (string)$res['expires_at'] : null,
    'failure_reason' => isset($res['failure_reason']) ? (string)$res['failure_reason'] : null,
    'http_code' => (int)($res['http_code'] ?? 200),
  ];
}

/** Clear a stuck online payment attempt so the user can try again. */
function snippe_reset_payment_attempt(int $listingId, string $kind, ?string $message = null): void {
  $msg = $message !== null && $message !== '' ? (strlen($message) > 250 ? substr($message, 0, 250) : $message) : null;
  if ($kind === LISTING_PAY_LAND) {
    db()->prepare(
      "UPDATE listings SET land_snippe_status = 'none', land_snippe_reference = NULL, land_snippe_last_error = ?
       WHERE id = ? AND land_payment_status = 'pending'"
    )->execute([$msg, $listingId]);
    return;
  }
  db()->prepare(
    "UPDATE listings SET snippe_status = 'none', snippe_reference = NULL, snippe_last_error = ?
     WHERE id = ? AND payment_status = 'pending'"
  )->execute([$msg, $listingId]);
}

/**
 * Sync with Snippe, then clear a stuck pending payment when it did not complete.
 *
 * @return string|null Flash message (success info) or error
 */
function snippe_abandon_pending_payment(int $listingId, string $kind): ?string {
  $listing = snippe_reload_listing($listingId);
  if (!$listing) {
    return 'Listing not found.';
  }
  if (snippe_listing_is_paid($listing, $kind)) {
    return 'This payment has already been completed.';
  }

  snippe_sync_listing_payment($listingId, $kind);
  $listing = snippe_reload_listing($listingId) ?? $listing;

  if (snippe_listing_is_paid($listing, $kind)) {
    return 'Payment confirmed — thank you!';
  }

  $local = snippe_listing_snippe_status($listing, $kind);
  if ($local !== 'pending') {
    return null;
  }

  $ref = snippe_listing_snippe_reference($listing, $kind);
  if ($ref !== '') {
    $api = snippe_fetch_payment_status($ref);
    if ($api['ok'] && ($api['status'] ?? '') === 'completed') {
      snippe_apply_api_status($listingId, 'completed', $kind);
      return 'Payment confirmed — thank you!';
    }
    if ($api['ok'] && ($api['status'] ?? '') === 'pending') {
      snippe_reset_payment_attempt(
        $listingId,
        $kind,
        'Previous prompt was not completed. Start a new payment below.'
      );
      return null;
    }
  }

  snippe_reset_payment_attempt(
    $listingId,
    $kind,
    'Previous payment attempt cleared. You can pay again.'
  );
  return null;
}

function snippe_apply_api_status(int $listingId, string $apiStatus, string $kind, ?string $failureReason = null): void {
  $status = strtolower($apiStatus);
  if ($status === 'completed') {
    listing_snippe_mark_completed($listingId, $kind);
    return;
  }
  if (in_array($status, ['failed', 'voided'], true)) {
    $msg = $failureReason ?? ('Payment ' . $status);
    listing_snippe_mark_failed($listingId, $msg, $kind);
    return;
  }
  if ($status === 'expired') {
    listing_snippe_mark_expired($listingId, $kind);
    return;
  }
  listing_snippe_mark_failed($listingId, $failureReason ?? ('Payment status: ' . $status), $kind);
}

/** Poll Snippe when webhook is delayed or misconfigured. Returns true if listing row may have changed. */
function snippe_sync_listing_payment(int $listingId, string $kind = LISTING_PAY_LISTING_FEE): bool {
  if (!snippe_enabled()) {
    return false;
  }
  $st = db()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
  $st->execute([$listingId]);
  $row = $st->fetch();
  if (!$row) {
    return false;
  }

  if ($kind === LISTING_PAY_LAND) {
    if (($row['land_payment_status'] ?? '') === 'paid') {
      return false;
    }
    $local = (string)($row['land_snippe_status'] ?? 'none');
    $ref = trim((string)($row['land_snippe_reference'] ?? ''));
  } else {
    if (($row['payment_status'] ?? '') === 'paid') {
      return false;
    }
    $local = (string)($row['snippe_status'] ?? 'none');
    $ref = trim((string)($row['snippe_reference'] ?? ''));
  }

  if ($local !== 'pending') {
    return false;
  }

  if ($ref === '') {
    snippe_reset_payment_attempt($listingId, $kind, 'Previous payment did not start correctly. Try again.');
    return true;
  }

  $api = snippe_fetch_payment_status($ref);
  if (!$api['ok'] || empty($api['status'])) {
    $httpCode = (int)($api['http_code'] ?? 0);
    if ($httpCode === 404) {
      snippe_reset_payment_attempt($listingId, $kind, 'Payment not found at provider. Start a new payment.');
      return true;
    }
    return false;
  }

  $apiStatus = (string)$api['status'];
  if ($apiStatus === 'pending') {
    $expiresAt = (string)($api['expires_at'] ?? '');
    if ($expiresAt !== '') {
      $expTs = strtotime($expiresAt);
      if ($expTs !== false && $expTs < time()) {
        listing_snippe_mark_expired($listingId, $kind);
        return true;
      }
    }
    return false;
  }

  $failReason = (string)($api['failure_reason'] ?? '');
  snippe_apply_api_status($listingId, $apiStatus, $kind, $failReason !== '' ? $failReason : null);
  return true;
}

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

  $payKind = snippe_payment_kind_from_payload($payload);

  if ($listingId === null || $listingId <= 0) {
    if ($snippeRef !== null) {
      $st = db()->prepare(
        'SELECT id FROM listings WHERE snippe_reference = ? OR land_snippe_reference = ? LIMIT 1'
      );
      $st->execute([$snippeRef, $snippeRef]);
      $row = $st->fetch();
      if ($row) {
        $listingId = (int)$row['id'];
      }
    }
  }

  if ($listingId === null || $listingId <= 0) {
    return false;
  }

  $data = $payload['data'] ?? $payload;
  $apiStatus = is_array($data) ? strtolower((string)($data['status'] ?? '')) : '';
  $failReason = is_array($data) ? (string)($data['failure_reason'] ?? '') : '';

  if ($eventType === 'payment.completed' || $apiStatus === 'completed') {
    listing_snippe_mark_completed($listingId, $payKind);
  } elseif ($eventType === 'payment.failed' || $apiStatus === 'failed') {
    listing_snippe_mark_failed($listingId, $failReason !== '' ? $failReason : 'Payment failed', $payKind);
  } elseif ($eventType === 'payment.voided' || $apiStatus === 'voided') {
    listing_snippe_mark_failed($listingId, 'Payment voided', $payKind);
  } elseif ($eventType === 'payment.expired' || $apiStatus === 'expired') {
    listing_snippe_mark_expired($listingId, $payKind);
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
