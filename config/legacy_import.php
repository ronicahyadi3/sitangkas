<?php

return [
    'users' => [
        'chunk_size' => 500,
        'expected' => [
            'row_count' => 1514,
            'distinct_nik_count' => 1053,
            'minimum_id' => 1,
            'maximum_id' => 1514,
            'account_count' => 1053,
            'canonical_account_ids_sha256' => 'fdf4b66ce4980887fc7e693773a56c3a3eeb92d4888e5f7e849561e6431c15f8',
            'canonical_position_count' => 1143,
            'alias_position_count' => 371,
            'position_classification_sha256' => '7efe79d9528bb96ea9284ef5c078292d65ae6e0b8c1d7caaee1d7082c741873a',
            'synthesized_alias_soft_delete_count' => 34,
            'duplicate_context_count' => 239,
            'active_duplicate_context_count' => 34,
            'organization_correction_count' => 51,
            'used_columns_sha256' => 'db31cc474ae465954797422f204143f669c125f1363e218e59796cb5e9f7ccf3',
        ],
    ],

    'execution' => [
        'enabled' => false,
        'advisory_lock_name' => 'sitangkas:legacy-users-import',
        'advisory_lock_timeout_seconds' => 0,
        'transaction_attempts' => 1,
        'insert_chunk_size' => 250,
        'decisions' => [
            'password_strategy' => 'preserve_legacy_hash_force_change',
            'account_status_strategy' => null,
            'file_sk_strategy' => null,
        ],
        'development_password_override' => [
            'enabled' => (bool) env('LEGACY_IMPORT_DEV_SHARED_PASSWORD_ENABLED', false),
            'password' => env('LEGACY_IMPORT_DEV_SHARED_PASSWORD'),
            'force_change' => (bool) env('LEGACY_IMPORT_DEV_FORCE_PASSWORD_CHANGE', false),
        ],
    ],

    'organization' => [
        'allowed_instansi_corrections' => [
            24 => ['source_instansi_id' => 1, 'target_instansi_id' => 5],
            25 => ['source_instansi_id' => 1, 'target_instansi_id' => 6],
            26 => ['source_instansi_id' => 1, 'target_instansi_id' => 7],
            27 => ['source_instansi_id' => 1, 'target_instansi_id' => 8],
            28 => ['source_instansi_id' => 1, 'target_instansi_id' => 9],
        ],
    ],

    'reports' => [
        'disk' => 'local',
        'directory' => 'legacy-import/users',
    ],

    'pending_decisions' => [
        'Status akun yang seluruh posisi canonical-nya tidak aktif atau terhapus.',
        'Strategi import dan validasi file SK legacy.',
    ],
];
