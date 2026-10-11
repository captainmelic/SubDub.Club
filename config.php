<?php
// Lives OUTSIDE the web root (public/). Edit the DB password before going live.
return [
    'db' => [
        'host' => getenv('SUB_DB_HOST') ?: '127.0.0.1',
        'name' => getenv('SUB_DB_NAME') ?: 'subtracker',
        'user' => getenv('SUB_DB_USER') ?: 'subapp',
        'pass' => getenv('SUB_DB_PASS') ?: 'SubDubs',
    ],
    // "Day" boundaries for streaks are computed in this timezone.
    'timezone'       => 'America/New_York',
    'classifier_url' => 'http://127.0.0.1:5001/classify',
    'upload_dir'     => __DIR__ . '/storage/uploads',
    'max_bytes'      => 8 * 1024 * 1024,
];
