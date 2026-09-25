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

    'frontend' => [
        'enabled' => (bool) env('SIGNATURE_FRONTEND_ENABLED', false),
    ],

    'verification' => [
        'cache_store' => env('SIGNATURE_VERIFICATION_CACHE_STORE'),
        'cache_ttl_minutes' => (int) env('SIGNATURE_VERIFICATION_CACHE_TTL_MINUTES', 60),
        'policy_version' => 'bsre-v2-v1',
    ],

    'visible_editor' => [
        'prepared_disk' => env('SIGNATURE_PREPARED_DISK', 'private'),
        'prepared_root' => env('SIGNATURE_PREPARED_ROOT', 'esign-prepared'),
        'prepared_retention_minutes' => (int) env('SIGNATURE_PREPARED_RETENTION_MINUTES', 120),
        'pdfinfo_binary' => env('SIGNATURE_PDFINFO_BINARY', 'pdfinfo'),
        'qpdf_binary' => env('SIGNATURE_QPDF_BINARY', 'qpdf'),
        'process_timeout_seconds' => (int) env('SIGNATURE_PDF_RENDER_TIMEOUT_SECONDS', 120),
        'renderer_version' => 'qpdf-overlay-v3',
        'verification_base_url' => env(
            'SIGNATURE_VERIFY_BASE_URL',
            'https://sitangkas.malangkota.go.id/verify',
        ),
        'coordinate_origin' => 'top_left',
        'max_pages' => (int) env('SIGNATURE_EDITOR_MAX_PAGES', 500),
        'max_operations' => (int) env('SIGNATURE_EDITOR_MAX_OPERATIONS', 5),
        'safe_margin_pt' => (float) env('SIGNATURE_EDITOR_SAFE_MARGIN_PT', 18),
        'minimum_gap_pt' => (float) env('SIGNATURE_EDITOR_MINIMUM_GAP_PT', 6),
        'qr_minimum_size_pt' => (float) env('SIGNATURE_EDITOR_QR_MINIMUM_SIZE_PT', 54),
        'qr_maximum_size_pt' => (float) env('SIGNATURE_EDITOR_QR_MAXIMUM_SIZE_PT', 180),
        'qr_image_size_pixels' => (int) env('SIGNATURE_EDITOR_QR_IMAGE_SIZE_PIXELS', 300),
        'qr_image_margin_pixels' => (int) env('SIGNATURE_EDITOR_QR_IMAGE_MARGIN_PIXELS', 12),
        'qr_profile_version' => 'malangkota-logo-v1',
        'qr_logo_relative_path' => 'assets/img/logokotamalang.png',
        'qr_logo_sha256' => '91a121d914ccc147420090ac99a20396db5fbc3f7e8b3c368cdaeaef49abb91c',
        'qr_logo_width_ratio' => 0.22,
        'qr_logo_punchout_background' => true,
        'footer_height_pt' => (float) env('SIGNATURE_EDITOR_FOOTER_HEIGHT_PT', 32),
        'footer_default_text' => env(
            'SIGNATURE_EDITOR_FOOTER_TEXT',
            'Dokumen ini telah ditandatangani secara elektronik menggunakan sertifikat elektronik.',
        ),
        'footer_default_font' => 'helvetica',
        'footer_default_font_size_pt' => 7.5,
        'footer_font_size_min_pt' => 6.0,
        'footer_font_size_max_pt' => 14.0,
        'allowed_fonts' => [
            'helvetica' => 'Helvetica',
            'times' => 'Times',
            'courier' => 'Courier',
        ],
    ],

    'processing' => [
        'multi_operation_enabled' => (bool) env('SIGNATURE_MULTI_OPERATION_ENABLED', false),
        'queue_connection' => env('SIGNATURE_QUEUE_CONNECTION', 'signatures'),
        'queue' => env('SIGNATURE_QUEUE', 'signatures'),
        'job_timeout_seconds' => (int) env('SIGNATURE_JOB_TIMEOUT_SECONDS', 900),
        'provisioning_job_timeout_seconds' => (int) env('SIGNATURE_PROVISIONING_JOB_TIMEOUT_SECONDS', 180),
        'secret_cache_store' => env('SIGNATURE_SECRET_CACHE_STORE'),
        'secret_ttl_minutes' => (int) env('SIGNATURE_ASYNC_SECRET_TTL_MINUTES', 30),
        'max_file_size_mb' => (int) env('SIGNATURE_MAX_FILE_SIZE_MB', 50),
    ],

    'contract_proof' => [
        'enabled' => (bool) env('SIGNATURE_CONTRACT_PROOF_ENABLED', false),
        'disk' => env('SIGNATURE_CONTRACT_PROOF_DISK', 'private'),
        'root' => env('SIGNATURE_CONTRACT_PROOF_ROOT', 'esign-contract-proofs'),
        'verification_base_url' => env(
            'SIGNATURE_CONTRACT_PROOF_VERIFY_BASE_URL',
            'https://sitangkas.malangkota.go.id/verify',
        ),
        'max_operations' => (int) env('SIGNATURE_CONTRACT_PROOF_MAX_OPERATIONS', 5),
        'qr_size_pixels' => (int) env('SIGNATURE_CONTRACT_PROOF_QR_SIZE_PIXELS', 300),
        'qr_margin_pixels' => (int) env('SIGNATURE_CONTRACT_PROOF_QR_MARGIN_PIXELS', 12),
    ],
];
