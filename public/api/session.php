<?php
require __DIR__ . '/../../src/bootstrap.php';
json_out([
    'loggedIn' => !empty($_SESSION['uid']),
    'csrf'     => csrf_token(),
]);
