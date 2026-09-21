<?php

return [
    'artifacts' => [
        'disk' => env('ESIGN_ARTIFACT_DISK', 'private'),
        'root' => env('ESIGN_ARTIFACT_ROOT', 'documents'),
        'timezone' => env('ESIGN_ARTIFACT_TIMEZONE', 'Asia/Jakarta'),
    ],

    'signing_session' => [
        'cache_store' => env('ESIGN_SIGNING_SESSION_CACHE_STORE'),
        'ttl_minutes' => (int) env('ESIGN_SIGNING_SESSION_TTL_MINUTES', 15),
    ],
];
