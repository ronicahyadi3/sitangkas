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
            'active_account_count' => 930,
            'inactive_account_count' => 123,
            'inactive_non_deleted_account_count' => 62,
            'all_source_rows_deleted_account_count' => 61,
            'account_status_resolution_sha256' => 'abc04401cdbb24b113b2b013edcc34edf9cdc43ee354e3a078560e961d00c569',
            'used_columns_sha256' => 'db31cc474ae465954797422f204143f669c125f1363e218e59796cb5e9f7ccf3',
        ],
    ],

    'execution' => [
        'enabled' => true,
        'advisory_lock_name' => 'sitangkas:legacy-users-import',
        'advisory_lock_timeout_seconds' => 0,
        'transaction_attempts' => 1,
        'insert_chunk_size' => 250,
        'decisions' => [
            'password_strategy' => 'preserve_legacy_hash_force_change',
            'account_status_strategy' => 'active_if_any_active_position_else_inactive',
            'file_sk_strategy' => 'defer',
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

    'position_documents' => [
        'source_directory' => env(
            'LEGACY_IMPORT_SK_SOURCE_DIRECTORY',
            public_path('SuratKeterangan'),
        ) ?: public_path('SuratKeterangan'),
        'pdfinfo_binary' => env('LEGACY_IMPORT_PDFINFO_BINARY', 'pdfinfo') ?: 'pdfinfo',
        'pdfinfo_timeout_seconds' => 15,
        'allowed_source_prefixes' => ['', 'file_sk'],
        'expected' => [
            'source_row_count' => 1514,
            'populated_reference_count' => 1514,
            'unique_reference_count' => 1514,
            'physical_file_count' => 741,
            'physical_pdf_count' => 740,
            'physical_incomplete_count' => 1,
            'matched_reference_count' => 522,
            'missing_reference_count' => 992,
            'available_valid_count' => 518,
            'invalid_pdf_count' => 4,
            'incomplete_reference_count' => 0,
            'orphan_physical_file_count' => 219,
            'physical_duplicate_content_group_count' => 61,
            'physical_duplicate_content_file_count' => 615,
            'referenced_duplicate_content_group_count' => 40,
            'referenced_duplicate_content_file_count' => 388,
            'reference_sha256' => 'aeadb2ad456c67e9d19ebb3c10f4c3ee431b7cb2ed9401edd719df661316d911',
            'manifest_sha256' => 'ee93c6fb181222bd1466d294d7371321f933f6a9631b1812212937b6f1e4c39e',
        ],
    ],

    'reports' => [
        'disk' => 'local',
        'directory' => 'legacy-import/users',
        'commit_directory' => 'legacy-import/users/commits',
        'position_documents_directory' => 'legacy-import/user-position-documents',
    ],

    'pending_decisions' => [],
];
