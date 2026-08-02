<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
            'remember' => max(1, (int) env('AUTH_REMEMBER_ME_DURATION_MINUTES', 1440)),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Remember Me Policy
    |--------------------------------------------------------------------------
    |
    | SITANGKAS allows remember-me only as a convenience for non Admin Super
    | real positions. Admin Super and acting Admin Super contexts must remain
    | session-only. Single-device enforcement rotates remember tokens on each
    | credential login so older devices cannot authenticate again.
    |
    */

    'remember_me' => [
        'enabled' => env('AUTH_REMEMBER_ME_ENABLED', true),
        'duration_minutes' => max(1, (int) env('AUTH_REMEMBER_ME_DURATION_MINUTES', 1440)),
        'single_device' => env('AUTH_REMEMBER_ME_SINGLE_DEVICE', true),
        'admin_super_allowed' => env('AUTH_REMEMBER_ME_ADMIN_SUPER_ALLOWED', false),
        'restore_non_admin_context' => env('AUTH_REMEMBER_ME_RESTORE_NON_ADMIN_CONTEXT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Factor Authentication Policy
    |--------------------------------------------------------------------------
    |
    | MFA uses TOTP compatible with Google Authenticator. Admin Super real
    | positions must use MFA before acting context or internal access. Non Admin
    | Super users may enroll now and can be required later by changing policy.
    |
    */

    'mfa' => [
        'enabled' => env('MFA_ENABLED', true),
        'method' => env('MFA_METHOD', 'totp'),
        'admin_super' => [
            'required' => env('MFA_ADMIN_SUPER_REQUIRED', true),
            'verified_ttl_minutes' => max(1, (int) env('MFA_ADMIN_SUPER_VERIFIED_TTL_MINUTES', 30)),
            'allow_trusted_device' => env('MFA_ADMIN_SUPER_ALLOW_TRUSTED_DEVICE', false),
        ],
        'non_admin' => [
            'available' => env('MFA_NON_ADMIN_AVAILABLE', true),
            'required' => env('MFA_NON_ADMIN_REQUIRED', false),
            'enforce_when_enabled' => env('MFA_NON_ADMIN_ENFORCE_WHEN_ENABLED', true),
            'verified_ttl_minutes' => max(1, (int) env('MFA_NON_ADMIN_VERIFIED_TTL_MINUTES', 30)),
            'allow_trusted_device' => env('MFA_NON_ADMIN_ALLOW_TRUSTED_DEVICE', true),
        ],
        'totp' => [
            'digits' => (int) env('MFA_TOTP_DIGITS', 6),
            'period_seconds' => (int) env('MFA_TOTP_PERIOD_SECONDS', 30),
            'window' => (int) env('MFA_TOTP_WINDOW', 1),
        ],
        'recovery_codes' => [
            'count' => (int) env('MFA_RECOVERY_CODE_COUNT', 8),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Audit Hash Key
    |--------------------------------------------------------------------------
    |
    | Used to HMAC authentication identifiers and session IDs before writing
    | them to audit tables. Production should set AUDIT_HASH_KEY separately
    | from APP_KEY. Local development may fall back to APP_KEY, but production
    | code must fail closed when this value is missing.
    |
    */

    'audit_hash_key' => env('AUDIT_HASH_KEY'),

];
