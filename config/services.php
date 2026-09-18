<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bsre_esign' => [
        'enabled' => env('BSRE_ESIGN_ENABLED', false),
        'base_url' => env('BSRE_ESIGN_BASE_URL'),
        'username' => env('BSRE_ESIGN_USERNAME'),
        'password' => env('BSRE_ESIGN_PASSWORD'),
        'verify_tls' => env('BSRE_ESIGN_VERIFY_TLS', true),
        'allow_insecure_http' => env('BSRE_ESIGN_ALLOW_INSECURE_HTTP', false),
        'connect_timeouts' => [
            'default' => (int) env('BSRE_ESIGN_CONNECT_TIMEOUT', 10),
            'verify' => (int) env('BSRE_ESIGN_VERIFY_CONNECT_TIMEOUT', 5),
        ],
        'timeouts' => [
            'status' => (int) env('BSRE_ESIGN_STATUS_TIMEOUT', 30),
            'totp' => (int) env('BSRE_ESIGN_TOTP_TIMEOUT', 30),
            'certificate' => (int) env('BSRE_ESIGN_CERTIFICATE_TIMEOUT', 30),
            'sign' => (int) env('BSRE_ESIGN_SIGN_TIMEOUT', 30),
            'verify' => (int) env('BSRE_ESIGN_VERIFY_TIMEOUT', 120),
        ],
        'location' => env('BSRE_ESIGN_LOCATION', 'Pemerintah Kota Malang'),
        'default_reason' => env(
            'BSRE_ESIGN_DEFAULT_REASON',
            'Dokumen telah disetujui dan ditandatangani secara elektronik'
        ),
        'endpoints' => [
            'sign' => env('BSRE_ESIGN_SIGN_ENDPOINT', '/api/v2/sign/pdf'),
            'verify' => env('BSRE_ESIGN_VERIFY_ENDPOINT', '/api/v2/verify/pdf'),
            'user_status' => env('BSRE_ESIGN_USER_STATUS_ENDPOINT', '/api/v2/user/check/status'),
            'totp' => env('BSRE_ESIGN_TOTP_ENDPOINT', '/api/v2/sign/get/totp'),
            'certificate_chain' => env(
                'BSRE_ESIGN_CERTIFICATE_CHAIN_ENDPOINT',
                '/api/v2/user/certificate/chain'
            ),
        ],
    ],

];
