<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoginEvent extends Model
{
    public const EVENT_ACCOUNT_LOCKED = 'account_locked';

    public const EVENT_ACCOUNT_UNLOCKED = 'account_unlocked';

    public const EVENT_LOCKOUT = 'lockout';

    public const EVENT_LOGIN = 'login';

    public const EVENT_PASSWORD_CHANGE_FORCED = 'password_change_forced';

    public const EVENT_PASSWORD_RESET_BY_ADMIN = 'password_reset_by_admin';

    public const EVENT_CONTEXT_SWITCHED = 'context_switched';

    public const EVENT_LOGOUT = 'logout';

    public const EVENT_MFA_CHALLENGE = 'mfa_challenge';

    public const EVENT_MFA_RECOVERY_CODES_REGENERATED = 'mfa_recovery_codes_regenerated';

    public const EVENT_MFA_RESET = 'mfa_reset';

    public const EVENT_MFA_VERIFIED = 'mfa_verified';

    public const EVENT_SESSION_REVOKED = 'session_revoked';

    public const EVENT_SESSION_TIMEOUT = 'session_timeout';

    public const RESULT_BLOCKED = 'blocked';

    public const RESULT_EXPIRED = 'expired';

    public const RESULT_FAILED = 'failed';

    public const RESULT_REVOKED = 'revoked';

    public const RESULT_SUCCESS = 'success';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_uuid',
        'user_id',
        'actor_user_id',
        'login_identifier_type',
        'login_identifier',
        'login_identifier_hash',
        'user_context',
        'event_type',
        'result',
        'failure_code',
        'message',
        'attempt_number',
        'auth_guard',
        'auth_method',
        'auth_provider',
        'remember_me',
        'mfa_method',
        'mfa_result',
        'request_id',
        'correlation_id',
        'session_id_hash',
        'token_id_hash',
        'source_channel',
        'route_name',
        'request_path',
        'http_method',
        'http_status',
        'ip_address',
        'proxy_ip_address',
        'forwarded_for',
        'network_asn',
        'network_organization',
        'country_code',
        'region',
        'city',
        'is_vpn',
        'is_proxy',
        'is_tor',
        'risk_score',
        'user_agent',
        'device_type',
        'device_name',
        'browser_name',
        'browser_version',
        'platform_name',
        'platform_version',
        'client_timezone',
        'accept_language',
        'device_fingerprint_hash',
        'captcha_provider',
        'captcha_score',
        'captcha_action',
        'captcha_success',
        'captcha_error_codes',
        'application_version',
        'environment',
        'server_node',
        'metadata',
        'event_hash',
        'occurred_at',
        'retention_until',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (LoginEvent $loginEvent): void {
            $loginEvent->event_uuid ??= (string) Str::uuid();
            $loginEvent->occurred_at ??= now();
            $loginEvent->created_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'captcha_error_codes' => 'array',
            'captcha_score' => 'decimal:3',
            'captcha_success' => 'boolean',
            'created_at' => 'datetime',
            'forwarded_for' => 'array',
            'is_proxy' => 'boolean',
            'is_tor' => 'boolean',
            'is_vpn' => 'boolean',
            'login_identifier' => 'encrypted',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'remember_me' => 'boolean',
            'retention_until' => 'datetime',
            'user_context' => 'array',
        ];
    }
}
