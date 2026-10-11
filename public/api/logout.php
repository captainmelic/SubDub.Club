<?php
require __DIR__ . '/../../src/bootstrap.php';
require_post_csrf();
$_SESSION = [];
session_destroy();
json_out(['ok' => true]);
