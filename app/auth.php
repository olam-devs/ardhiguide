<?php

declare(strict_types=1);

function current_user(): ?array {
  session_start_safe();
  if (!isset($_SESSION['user'])) return null;
  return is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_auth(): array {
  $u = current_user();
  if ($u) return $u;
  header('Location: ' . APP_BASE_URL . '/login.php');
  exit;
}

function require_role(string $role): array {
  $u = require_auth();
  if (($u['role'] ?? '') === $role) return $u;
  http_response_code(403);
  echo "Forbidden";
  exit;
}

/**
 * Look a user up by either email address or phone number. Returns null if
 * the identifier matches no account.
 */
function find_user_by_identifier(string $identifier): ?array {
  $identifier = trim($identifier);
  if ($identifier === '') return null;
  if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    $stmt = db()->prepare('SELECT id,email,full_name,phone,role,password_hash,is_active,verification_status,is_super_admin FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$identifier]);
  } else {
    $phone = normalize_phone($identifier);
    if ($phone === '') return null;
    $stmt = db()->prepare('SELECT id,email,full_name,phone,role,password_hash,is_active,verification_status,is_super_admin FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);
  }
  $row = $stmt->fetch();
  return $row ?: null;
}

function login(string $identifier, string $password): bool {
  $u = find_user_by_identifier($identifier);
  if (!$u) return false;
  if (!(int)$u['is_active']) return false;
  if (!password_verify($password, (string)$u['password_hash'])) return false;

  session_start_safe();
  $_SESSION['user'] = [
    'id' => (int)$u['id'],
    'email' => (string)($u['email'] ?? ''),
    'phone' => (string)($u['phone'] ?? ''),
    'full_name' => (string)$u['full_name'],
    'role' => (string)$u['role'],
    'verification_status' => (string)($u['verification_status'] ?? 'pending'),
    'is_super_admin' => (int)($u['is_super_admin'] ?? 0),
  ];
  return true;
}

function logout(): void {
  session_start_safe();
  $_SESSION = [];
  session_destroy();
}

/** Redirect after successful login based on role. */
function redirect_after_login(): void {
  $u = current_user();
  if (!$u) {
    redirect('/login.php');
    return;
  }
  session_start_safe();
  $next = trim((string)($_SESSION['login_redirect'] ?? ''));
  unset($_SESSION['login_redirect']);
  if ($next !== '' && str_starts_with($next, '/') && !str_contains($next, '//')) {
    redirect($next);
    return;
  }
  $role = (string)($u['role'] ?? 'buyer');
  if ($role === 'admin') {
    redirect('/admin/listings.php');
  }
  if (in_array($role, ['seller', 'agent'], true)) {
    redirect('/my-listings.php');
  }
  if ($role === 'expert') redirect('/expert-assignments.php');
  redirect('/index.php');
}

/**
 * Register a new buyer, seller, agent, or expert with KYC details.
 * @param array<string,string> $data
 * @param array<string,mixed> $passport
 */
function register_user(array $data, array $passport): ?string {
  $email = trim((string)($data['email'] ?? ''));
  if ($email === '') $email = null;
  $password = (string)($data['password'] ?? '');
  $fullName = trim((string)($data['full_name'] ?? ''));
  $phone = normalize_phone((string)($data['phone'] ?? ''));
  $role = (string)($data['role'] ?? 'buyer');
  $nida = preg_replace('/\s+/', '', trim((string)($data['nida_number'] ?? ''))) ?? '';
  $address = trim((string)($data['address_text'] ?? ''));
  $accountType = (string)($data['account_type'] ?? 'individual');
  $companyName = trim((string)($data['company_name'] ?? ''));
  $brela = trim((string)($data['brela_number'] ?? ''));
  $tin = trim((string)($data['tin_number'] ?? ''));
  $expertType = (string)($data['expert_type'] ?? '');
  $regionCode = trim((string)($data['region_code'] ?? ''));
  $districtCode = trim((string)($data['district_code'] ?? ''));
  $wardCode = trim((string)($data['ward_code'] ?? ''));

  if ($phone === '' || strlen($phone) < 9 || strlen($phone) > 15) {
    return 'Enter a valid phone number (we use it as your login).';
  }
  if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return 'Enter a valid email address, or leave it blank.';
  }
  if (strlen($fullName) < 2) {
    return 'Enter your full name.';
  }
  if (strlen($password) < 8) {
    return 'Password must be at least 8 characters.';
  }
  if (!in_array($role, ['buyer', 'seller', 'agent', 'expert'], true)) {
    return 'Invalid account type.';
  }
  if (strlen($nida) < 8 || strlen($nida) > 40) return 'Enter a valid NIDA number.';
  if (strlen($address) < 5) return 'Enter your address.';
  if (empty($passport['ok']) || empty($passport['stored_name'])) return 'A passport photo is required.';
  if (!in_array($accountType, ['individual', 'company'], true)) $accountType = 'individual';
  if ($role !== 'agent') $accountType = 'individual';
  if ($role === 'agent' && $accountType === 'company' && ($companyName === '' || $brela === '' || $tin === '')) {
    return 'Company agents must provide company name, BRELA registration number, and TIN.';
  }
  if ($role === 'expert') {
    if (!in_array($expertType, ['surveyor', 'valuer', 'town_planner', 'advocate'], true)) return 'Choose your expert profession.';
    if (!location_selection_valid($regionCode, $districtCode, $wardCode)) return 'Choose a valid region, district, and ward.';
  } else {
    $expertType = '';
    $regionCode = $districtCode = $wardCode = '';
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $verification = $role === 'buyer' ? 'not_required' : 'pending';
  $pdo = db();
  try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
      'INSERT INTO users
       (email,full_name,phone,role,password_hash,is_active,verification_status,nida_number,address_text,
        passport_photo_path,account_type,company_name,brela_number,tin_number,expert_type,region_code,district_code,ward_code)
       VALUES (?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
      $email, $fullName, $phone, $role, $hash, $verification, $nida, $address,
      (string)$passport['stored_name'], $accountType,
      $companyName !== '' ? $companyName : null, $brela !== '' ? $brela : null, $tin !== '' ? $tin : null,
      $expertType !== '' ? $expertType : null,
      $regionCode !== '' ? $regionCode : null, $districtCode !== '' ? $districtCode : null, $wardCode !== '' ? $wardCode : null,
    ]);
    $id = (int)$pdo->lastInsertId();
    $doc = $pdo->prepare(
      'INSERT INTO user_documents (user_id,document_type,original_name,stored_name,mime,size_bytes) VALUES (?,\'passport_photo\',?,?,?,?)'
    );
    $doc->execute([$id, (string)$passport['original_name'], (string)$passport['stored_name'], (string)$passport['mime'], (int)$passport['size_bytes']]);
    $pdo->commit();
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      $msg = strtolower((string)($e->errorInfo[2] ?? ''));
      if (strpos($msg, 'phone') !== false) {
        return 'An account with this phone number already exists.';
      }
      if (strpos($msg, 'email') !== false) {
        return 'An account with this email already exists.';
      }
      return 'An account with these details already exists.';
    }
    return 'Registration failed. Please try again.';
  }

  session_start_safe();
  $_SESSION['user'] = [
    'id' => $id,
    'email' => $email ?? '',
    'phone' => $phone,
    'full_name' => $fullName,
    'role' => $role,
    'verification_status' => $verification,
    'is_super_admin' => 0,
  ];
  return null;
}

/**
 * Change the current user's password. Verifies the current password first.
 * Returns null on success or an error message.
 */
function change_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): ?string {
  if (strlen($newPassword) < 8) {
    return 'New password must be at least 8 characters.';
  }
  if ($newPassword !== $confirmPassword) {
    return 'New passwords do not match.';
  }
  if ($currentPassword === $newPassword) {
    return 'New password must be different from the current password.';
  }
  $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $row = $stmt->fetch();
  if (!$row) {
    return 'Account not found.';
  }
  if (!password_verify($currentPassword, (string)$row['password_hash'])) {
    return 'Current password is incorrect.';
  }
  $hash = password_hash($newPassword, PASSWORD_DEFAULT);
  $up = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
  $up->execute([$hash, $userId]);
  return null;
}

/**
 * Admin: force-set another user's password without knowing the old one.
 * Caller MUST already be authorised as admin (use require_role('admin') first).
 * Returns null on success or an error message.
 */
function admin_reset_password(int $targetUserId, string $newPassword): ?string {
  if (strlen($newPassword) < 8) {
    return 'New password must be at least 8 characters.';
  }
  $stmt = db()->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$targetUserId]);
  if (!$stmt->fetch()) {
    return 'User not found.';
  }
  $hash = password_hash($newPassword, PASSWORD_DEFAULT);
  $up = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
  $up->execute([$hash, $targetUserId]);
  return null;
}

/** Admin: toggle a user's is_active flag. Returns the new value. */
function admin_set_user_active(int $targetUserId, bool $active): void {
  $up = db()->prepare('UPDATE users SET is_active = ? WHERE id = ?');
  $up->execute([$active ? 1 : 0, $targetUserId]);
}

/** Admin: set a user's role. */
function admin_set_user_role(int $targetUserId, string $role): ?string {
  if (!in_array($role, ['buyer', 'seller', 'agent', 'expert', 'admin'], true)) {
    return 'Invalid role.';
  }
  $up = db()->prepare('UPDATE users SET role = ? WHERE id = ?');
  $up->execute([$role, $targetUserId]);
  return null;
}

