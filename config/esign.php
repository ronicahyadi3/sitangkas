<?php

return [
    'artifacts' => [
        'disk' => env('ESIGN_ARTIFACT_DISK', 'private'),
        'root' => env('ESIGN_ARTIFACT_ROOT', 'documents'),
        'timezone' => env('ESIGN_ARTIFACT_TIMEZONE', 'Asia/Jakarta'),
    ],
];
