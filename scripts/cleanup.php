<?php
// Daily cron: delete uploads (rows + image files) older than 7 days.
// Keeps today + the 6 previous days. Aggregate stats in user_sub_stats are untouched.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$cfg = require __DIR__ . '/../config.php';
date_default_timezone_set($cfg['timezone']);

$c = $cfg['db'];
$pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

$cutoff = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d');

$rows = $pdo->prepare('SELECT id, image_file FROM uploads WHERE upload_date < ?');
$rows->execute([$cutoff]);
$n = 0;
foreach ($rows as $r) {
    @unlink($cfg['upload_dir'] . '/' . basename($r['image_file']));
    $pdo->prepare('DELETE FROM uploads WHERE id = ?')->execute([$r['id']]);
    $n++;
}
echo "Purged $n old uploads\n";
