<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_role('admin');
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT original_name, stored_name, mime FROM user_documents WHERE id = ? LIMIT 1');
$st->execute([$id]);
$doc = $st->fetch();
if (!$doc) { http_response_code(404); exit('Document not found.'); }
$stored = basename((string)$doc['stored_name']);
$path = storage_private_dir() . DIRECTORY_SEPARATOR . $stored;
if (!is_file($path)) { http_response_code(404); exit('File not found.'); }
header('Content-Type: ' . ((string)$doc['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', (string)$doc['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
