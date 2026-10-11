<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT image_file FROM uploads WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $uid]);
$row = $stmt->fetch();
if (!$row) fail('Not found', 404);

$path = cfg('upload_dir') . '/' . basename($row['image_file']);
if (!is_file($path)) fail('Not found', 404);

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($path));
readfile($path);
