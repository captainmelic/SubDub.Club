<?php
require __DIR__ . '/../../src/bootstrap.php';
require_post_csrf();

$in = body_json();
$username = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');

$stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ?');
$stmt->execute([$username]);
$u = $stmt->fetch();

if (!$u || !password_verify($password, $u['password_hash'])) {
    usleep(300000); // slow down guessing a little
    fail('Wrong username or password.', 401);
}

session_regenerate_id(true);
$_SESSION['uid'] = (int)$u['id'];
json_out(['ok' => true]);
