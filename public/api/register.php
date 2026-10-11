<?php
require __DIR__ . '/../../src/bootstrap.php';
require_post_csrf();

$in = body_json();
$username = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');

if (!preg_match('/^[A-Za-z0-9_]{3,24}$/', $username)) {
    fail('Username must be 3-24 characters: letters, numbers, underscore.');
}
if (strlen($password) < 8 || strlen($password) > 200) {
    fail('Password must be at least 8 characters.');
}

try {
    $stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') fail('That username is taken.', 409);
    throw $e;
}

session_regenerate_id(true);
$_SESSION['uid'] = (int)db()->lastInsertId();
json_out(['ok' => true]);
