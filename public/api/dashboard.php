<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_login();
$pdo = db();

// --- user + streak -------------------------------------------------
$stmt = $pdo->prepare('SELECT username, current_streak, longest_streak, last_upload_date FROM users WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) { $_SESSION = []; fail('Account not found', 401); }

// --- last 7 days (today + 6 previous) -----------------------------
$days = [];
$start = (new DateTimeImmutable('today'))->modify('-6 days');
for ($i = 0; $i < 7; $i++) {
    $d = $start->modify("+$i days")->format('Y-m-d');
    $days[$d] = ['date' => $d, 'upload' => null];
}
$stmt = $pdo->prepare(
    'SELECT id, upload_date, sub_type, rating, note FROM uploads
     WHERE user_id = ? AND upload_date >= ? ORDER BY upload_date'
);
$stmt->execute([$uid, $start->format('Y-m-d')]);
foreach ($stmt as $row) {
    if (isset($days[$row['upload_date']])) {
        $days[$row['upload_date']]['upload'] = [
            'sub_type' => $row['sub_type'],
            'rating'   => (int)$row['rating'],
            'note'     => $row['note'],
            'image'    => 'api/image.php?id=' . (int)$row['id'],
        ];
    }
}

// --- this user's top subs (by average rating) ---------------------
$stmt = $pdo->prepare(
    'SELECT sub_type, upload_count, ROUND(rating_total / upload_count, 1) AS avg_rating
     FROM user_sub_stats WHERE user_id = ? AND upload_count > 0
     ORDER BY rating_total / upload_count DESC, upload_count DESC, sub_type
     LIMIT 5'
);
$stmt->execute([$uid]);
$top = array_map(fn($r) => [
    'sub_type' => $r['sub_type'],
    'count'    => (int)$r['upload_count'],
    'avg'      => (float)$r['avg_rating'],
], $stmt->fetchAll());

// --- leaderboard: name + streak ONLY ------------------------------
$stmt = $pdo->prepare(
    'SELECT username, current_streak AS streak FROM users
     WHERE last_upload_date >= ? AND current_streak > 0
     ORDER BY current_streak DESC, username ASC LIMIT 10'
);
$stmt->execute([yesterday()]);
$board = array_map(fn($r) => ['username' => $r['username'], 'streak' => (int)$r['streak']], $stmt->fetchAll());

json_out([
    'username'       => $user['username'],
    'streak'         => effective_streak((int)$user['current_streak'], $user['last_upload_date']),
    'longest'        => (int)$user['longest_streak'],
    'uploaded_today' => $user['last_upload_date'] === today(),
    'week'           => array_values($days),
    'top_subs'       => $top,
    'leaderboard'    => $board,
]);
