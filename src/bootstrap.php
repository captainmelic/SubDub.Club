<?php
declare(strict_types=1);

$GLOBALS['cfg'] = require __DIR__ . '/../config.php';
date_default_timezone_set($GLOBALS['cfg']['timezone']);

session_name('subsess');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function cfg(string $key) { return $GLOBALS['cfg'][$key]; }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = cfg('db');
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
            $c['user'], $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function json_out($data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function fail(string $msg, int $code = 400): never {
    json_out(['error' => $msg], $code);
}

function body_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function require_post_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $sent)) fail('Bad CSRF token', 403);
}

function require_login(): int {
    if (empty($_SESSION['uid'])) fail('Not logged in', 401);
    return (int)$_SESSION['uid'];
}

function today(): string {
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

function yesterday(): string {
    return (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d');
}

/** A streak only counts if the user uploaded today or yesterday. */
function effective_streak(int $current, ?string $lastDate): int {
    return ($lastDate !== null && $lastDate >= yesterday()) ? $current : 0;
}
