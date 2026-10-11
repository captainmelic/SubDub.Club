<?php
require __DIR__ . '/../../src/bootstrap.php';
$uid = require_login();
require_post_csrf();

// ---------- validate input ----------
$rating = (int)($_POST['rating'] ?? 0);
if ($rating < 1 || $rating > 5) fail('Please choose a star rating (1-5).');

$note = trim((string)($_POST['note'] ?? ''));
if (mb_strlen($note) > 255) fail('Note is too long (255 characters max).');
$note = $note === '' ? null : $note;

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    fail('No image received (or it was too large).');
}
$tmp = $_FILES['image']['tmp_name'];
if ($_FILES['image']['size'] > cfg('max_bytes')) fail('Image is too large.');

$info = @getimagesize($tmp);
if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
    fail('Please upload a JPEG, PNG or WebP image.');
}
if ($info[0] * $info[1] > 25_000_000) fail('Image dimensions are too large.');

// ---------- re-encode to a clean, resized JPEG ----------
$dir = cfg('upload_dir');
if (!is_dir($dir)) mkdir($dir, 0750, true);
$filename = bin2hex(random_bytes(16)) . '.jpg';
$dest = $dir . '/' . $filename;

$src = match ($info[2]) {
    IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
    IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
    IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
};
if (!$src) fail('Could not read that image.');

if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $exif = @exif_read_data($tmp);
    $orient = $exif['Orientation'] ?? 1;
    $rot = match ($orient) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
    if ($rot) $src = imagerotate($src, $rot, 0);
}
if (imagesx($src) > 1024 || imagesy($src) > 1024) {
    $src = imagescale($src, imagesx($src) >= imagesy($src) ? 1024 : -1, imagesx($src) >= imagesy($src) ? -1 : 1024);
}
imagejpeg($src, $dest, 85);
imagedestroy($src);

// ---------- ask the Python classifier ----------
$ch = curl_init(cfg('classifier_url'));
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => ['image' => new CURLFile($dest, 'image/jpeg', 'sub.jpg')],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 45,
]);
$resp = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

$result = $resp ? json_decode($resp, true) : null;
if ($status !== 200 || !is_array($result)) {
    @unlink($dest);
    fail('The sub detector is unavailable right now. Try again shortly.', 503);
}
if (empty($result['is_sub'])) {
    @unlink($dest);
    fail("That doesn't look like a sub sandwich. Try a clearer photo of your sub!", 422);
}
$subType = substr((string)$result['sub_type'], 0, 40);
$confidence = max(0, min(1, (float)$result['confidence']));

// ---------- save: transaction keeps streak + stats consistent ----------
$pdo = db();
$oldFile = null;
try {
    $pdo->beginTransaction();

    // Lock the user row so two simultaneous uploads can't double-count a streak.
    $stmt = $pdo->prepare('SELECT current_streak, longest_streak, last_upload_date FROM users WHERE id = ? FOR UPDATE');
    $stmt->execute([$uid]);
    $u = $stmt->fetch();

    $today = today();

    $stmt = $pdo->prepare('SELECT id, sub_type, rating, image_file FROM uploads WHERE user_id = ? AND upload_date = ? FOR UPDATE');
    $stmt->execute([$uid, $today]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Same-day re-upload replaces the old one: back out its stats.
        $pdo->prepare('UPDATE user_sub_stats SET upload_count = GREATEST(upload_count,1) - 1,
                       rating_total = GREATEST(rating_total, ?) - ?
                       WHERE user_id = ? AND sub_type = ?')
            ->execute([(int)$existing['rating'], (int)$existing['rating'], $uid, $existing['sub_type']]);
        $pdo->prepare('UPDATE uploads SET sub_type = ?, confidence = ?, rating = ?, note = ?, image_file = ? WHERE id = ?')
            ->execute([$subType, $confidence, $rating, $note, $filename, $existing['id']]);
        $oldFile = $existing['image_file'];
    } else {
        $pdo->prepare('INSERT INTO uploads (user_id, upload_date, sub_type, confidence, rating, note, image_file)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $today, $subType, $confidence, $rating, $note, $filename]);
    }

    // Add the new upload to the per-sub running totals.
    $pdo->prepare('INSERT INTO user_sub_stats (user_id, sub_type, upload_count, rating_total) VALUES (?, ?, 1, ?)
                   ON DUPLICATE KEY UPDATE upload_count = upload_count + 1, rating_total = rating_total + VALUES(rating_total)')
        ->execute([$uid, $subType, $rating]);

    // Streak: unchanged if already counted today, +1 if yesterday, else restart at 1.
    $last = $u['last_upload_date'];
    $streak = (int)$u['current_streak'];
    if ($last !== $today) {
        $streak = ($last === yesterday()) ? $streak + 1 : 1;
    }
    $longest = max((int)$u['longest_streak'], $streak);
    $pdo->prepare('UPDATE users SET current_streak = ?, longest_streak = ?, last_upload_date = ? WHERE id = ?')
        ->execute([$streak, $longest, $today, $uid]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    @unlink($dest);
    error_log('upload failed: ' . $e->getMessage());
    fail('Could not save your upload.', 500);
}

if ($oldFile) @unlink($dir . '/' . basename($oldFile));

json_out([
    'ok'         => true,
    'sub_type'   => $subType,
    'confidence' => round($confidence, 3),
    'streak'     => $streak,
]);
