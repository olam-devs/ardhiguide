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

function login(string $email, string $password): bool {
  $stmt = db()->prepare('SELECT id,email,full_name,role,password_hash,is_active FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $u = $stmt->fetch();
  if (!$u) return false;
  if (!(int)$u['is_active']) return false;
  if (!password_verify($password, (string)$u['password_hash'])) return false;

  session_start_safe();
  $_SESSION['user'] = [
    'id' => (int)$u['id'],
    'email' => (string)$u['email'],
    'full_name' => (string)$u['full_name'],
    'role' => (string)$u['role'],
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
  $role = (string)($u['role'] ?? 'buyer');
  if ($role === 'admin') {
    redirect('/admin/listings.php');
  }
  if (in_array($role, ['seller', 'agent'], true)) {
    redirect('/my-listings.php');
  }
  redirect('/index.php');
}

/**
 * Register a new user (buyer, seller, or agent only). Returns null on success, or an error message.
 */
function register_user(string $email, string $password, string $fullName, ?string $phone, string $role): ?string {
  $email = trim($email);
  $fullName = trim($fullName);
  $phone = $phone !== null ? trim($phone) : null;
  if ($phone === '') {
    $phone = null;
  }

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return 'Enter a valid email address.';
  }
  if (strlen($fullName) < 2) {
    return 'Enter your full name.';
  }
  if (strlen($password) < 8) {
    return 'Password must be at least 8 characters.';
  }
  if (!in_array($role, ['buyer', 'seller', 'agent'], true)) {
    return 'Invalid account type.';
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);
  try {
    $stmt = db()->prepare('INSERT INTO users (email, full_name, phone, role, password_hash, is_active) VALUES (?,?,?,?,?,1)');
    $stmt->execute([$email, $fullName, $phone, $role, $hash]);
  } catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
      return 'An account with this email already exists.';
    }
    return 'Registration failed. Please try again.';
  }

  session_start_safe();
  $id = (int)db()->lastInsertId();
  $_SESSION['user'] = [
    'id' => $id,
    'email' => $email,
    'full_name' => $fullName,
    'role' => $role,
  ];
  return null;
}

