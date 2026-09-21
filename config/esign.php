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

    'processing' => [
        'queue_connection' => env('SIGNATURE_QUEUE_CONNECTION', 'signatures'),
        'queue' => env('SIGNATURE_QUEUE', 'signatures'),
        'job_timeout_seconds' => (int) env('SIGNATURE_JOB_TIMEOUT_SECONDS', 900),
        'secret_cache_store' => env('SIGNATURE_SECRET_CACHE_STORE'),
        'secret_ttl_minutes' => (int) env('SIGNATURE_ASYNC_SECRET_TTL_MINUTES', 30),
        'max_file_size_mb' => (int) env('SIGNATURE_MAX_FILE_SIZE_MB', 50),
    ],
];
