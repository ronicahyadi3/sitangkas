<?php

return [
    'enabled' => env('SECURITY_HEADERS_ENABLED', true),

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => env('SECURITY_HEADERS_FRAME_OPTIONS', 'SAMEORIGIN'),
        'Referrer-Policy' => env('SECURITY_HEADERS_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'Permissions-Policy' => env(
            'SECURITY_HEADERS_PERMISSIONS_POLICY',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
        ),
        'Cross-Origin-Opener-Policy' => env('SECURITY_HEADERS_COOP', 'same-origin'),
        'Cross-Origin-Resource-Policy' => env('SECURITY_HEADERS_CORP', 'same-origin'),
    ],

    'hsts' => [
        'enabled' => env('SECURITY_HEADERS_HSTS_ENABLED', env('APP_ENV') === 'production'),
        'max_age' => (int) env('SECURITY_HEADERS_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => env('SECURITY_HEADERS_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => env('SECURITY_HEADERS_HSTS_PRELOAD', false),
        'only_when_secure' => env('SECURITY_HEADERS_HSTS_ONLY_WHEN_SECURE', true),
    ],

    'csp' => [
        'enabled' => env('SECURITY_HEADERS_CSP_ENABLED', true),
        'report_only' => env('SECURITY_HEADERS_CSP_REPORT_ONLY', false),
        'directives' => [
            'default-src' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'self'"],
            'form-action' => ["'self'"],
            'img-src' => ["'self'", 'data:', 'blob:', 'https:'],
            'font-src' => [
                "'self'",
                'data:',
                'https://fonts.gstatic.com',
                'https://cdnjs.cloudflare.com',
                'https://cdn.jsdelivr.net',
                'https://ka-f.fontawesome.com',
            ],
            'style-src' => [
                "'self'",
                "'unsafe-inline'",
                'https://fonts.googleapis.com',
                'https://cdnjs.cloudflare.com',
                'https://cdn.datatables.net',
                'https://cdn.jsdelivr.net',
                'https://unpkg.com',
            ],
            'script-src' => [
                "'self'",
                "'unsafe-inline'",
                'https://code.jquery.com',
                'https://cdn.datatables.net',
                'https://cdn.jsdelivr.net',
                'https://cdnjs.cloudflare.com',
                'https://kit.fontawesome.com',
                'https://ka-f.fontawesome.com',
                'https://unpkg.com',
                'https://www.google.com',
                'https://www.gstatic.com',
                'https://www.recaptcha.net',
                'https://recaptcha.google.com',
            ],
            'connect-src' => [
                "'self'",
                'https://ka-f.fontawesome.com',
                'https://www.google.com',
                'https://www.gstatic.com',
                'https://www.recaptcha.net',
                'https://recaptcha.google.com',
            ],
            'frame-src' => [
                "'self'",
                'https://www.google.com',
                'https://recaptcha.google.com',
                'https://www.recaptcha.net',
            ],
            'worker-src' => ["'self'", 'blob:', 'https://unpkg.com'],
            'media-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'upgrade-insecure-requests' => env(
                'SECURITY_HEADERS_CSP_UPGRADE_INSECURE_REQUESTS',
                env('APP_ENV') === 'production',
            ),
        ],
    ],
];
