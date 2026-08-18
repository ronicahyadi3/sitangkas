<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\AuthenticationEventContext;
use App\Services\Auth\LoginEventIntegrity;
use App\Services\Auth\LoginEventRetention;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

class RecordAuthenticationEvent
{
    public function __construct(
        private AuthenticationEventContext $authenticationEventContext,
        private LoginEventRetention $loginEventRetention,
        private LoginEventIntegrity $loginEventIntegrity
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function handle(Request $request, ?User $user = null, ?UserPosition $userPosition = null, array $overrides = []): LoginEvent
    {
        $identifierType = $this->loginIdentifierType($request, $user, $overrides);
        $identifier = $this->loginIdentifier($request, $user, $overrides);
        $eventContext = $this->authenticationEventContext->defaults($request, $overrides);
        $occurredAt = $this->dateTimeValue($overrides, 'occurred_at');
        $createdAt = $this->dateTimeValue($overrides, 'created_at');

        $attributes = [
            'event_uuid' => $overrides['event_uuid'] ?? (string) Str::uuid(),
            'user_id' => $user?->getKey(),
            'actor_user_id' => $overrides['actor_user_id'] ?? null,
            'login_identifier_type' => $identifierType,
            'login_identifier' => $identifier,
            'login_identifier_hash' => $this->identifierHash($identifierType, $identifier),
            'user_context' => $overrides['user_context'] ?? $this->userContext($user, $userPosition),
            'event_type' => $overrides['event_type'],
            'result' => $overrides['result'],
            'failure_code' => $overrides['failure_code'] ?? null,
            'message' => $overrides['message'] ?? null,
            'attempt_number' => $overrides['attempt_number'] ?? $this->attemptNumber($request),
            'auth_guard' => $overrides['auth_guard'] ?? 'web',
            'auth_method' => $overrides['auth_method'] ?? 'password',
            'auth_provider' => $overrides['auth_provider'] ?? 'users',
            'remember_me' => $overrides['remember_me'] ?? $this->rememberMe($request),
            'mfa_method' => $overrides['mfa_method'] ?? null,
            'mfa_result' => $overrides['mfa_result'] ?? null,
            'request_id' => $this->contextValue($overrides, $eventContext, 'request_id'),
            'correlation_id' => $this->contextValue($overrides, $eventContext, 'correlation_id'),
            'session_id_hash' => $overrides['session_id_hash'] ?? null,
            'token_id_hash' => $overrides['token_id_hash'] ?? null,
            'source_channel' => $overrides['source_channel'] ?? 'web',
            'route_name' => $overrides['route_name'] ?? $request->route()?->getName(),
            'request_path' => $overrides['request_path'] ?? Str::limit($request->path(), 500, ''),
            'http_method' => $overrides['http_method'] ?? $request->method(),
            'http_status' => $overrides['http_status'] ?? null,
            'ip_address' => $this->contextValue($overrides, $eventContext, 'ip_address'),
            'proxy_ip_address' => $this->contextValue($overrides, $eventContext, 'proxy_ip_address'),
            'forwarded_for' => $this->contextValue($overrides, $eventContext, 'forwarded_for'),
            'network_asn' => $this->contextValue($overrides, $eventContext, 'network_asn'),
            'network_organization' => $this->contextValue($overrides, $eventContext, 'network_organization'),
            'country_code' => $this->contextValue($overrides, $eventContext, 'country_code'),
            'region' => $this->contextValue($overrides, $eventContext, 'region'),
            'city' => $this->contextValue($overrides, $eventContext, 'city'),
            'is_vpn' => $this->contextValue($overrides, $eventContext, 'is_vpn'),
            'is_proxy' => $this->contextValue($overrides, $eventContext, 'is_proxy'),
            'is_tor' => $this->contextValue($overrides, $eventContext, 'is_tor'),
            'risk_score' => $this->contextValue($overrides, $eventContext, 'risk_score'),
            'user_agent' => $overrides['user_agent'] ?? $request->userAgent(),
            'device_type' => $this->contextValue($overrides, $eventContext, 'device_type'),
            'device_name' => $this->contextValue($overrides, $eventContext, 'device_name'),
            'browser_name' => $this->contextValue($overrides, $eventContext, 'browser_name'),
            'browser_version' => $this->contextValue($overrides, $eventContext, 'browser_version'),
            'platform_name' => $this->contextValue($overrides, $eventContext, 'platform_name'),
            'platform_version' => $this->contextValue($overrides, $eventContext, 'platform_version'),
            'client_timezone' => $this->contextValue($overrides, $eventContext, 'client_timezone'),
            'accept_language' => $overrides['accept_language'] ?? $request->header('Accept-Language'),
            'device_fingerprint_hash' => $overrides['device_fingerprint_hash'] ?? null,
            'captcha_provider' => $overrides['captcha_provider'] ?? ($this->captchaIsConfigured($request) ? 'recaptcha_v3' : null),
            'captcha_score' => $overrides['captcha_score'] ?? null,
            'captcha_action' => $overrides['captcha_action'] ?? $this->captchaAction($request),
            'captcha_success' => $overrides['captcha_success'] ?? null,
            'captcha_error_codes' => $overrides['captcha_error_codes'] ?? null,
            'application_version' => $this->contextValue($overrides, $eventContext, 'application_version'),
            'environment' => $overrides['environment'] ?? config('app.env'),
            'server_node' => $overrides['server_node'] ?? (gethostname() ?: null),
            'metadata' => $overrides['metadata'] ?? null,
            'event_hash' => null,
            'occurred_at' => $occurredAt,
            'retention_until' => $this->retentionUntil($overrides, $occurredAt),
            'created_at' => $createdAt,
        ];

        $attributes['event_hash'] = $this->eventHash($overrides, $attributes);

        return LoginEvent::create($attributes);
    }

    public function sessionIdHash(Request $request): ?string
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return hash_hmac('sha256', $sessionId, $this->auditHashKey());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function loginIdentifierType(Request $request, ?User $user, array $overrides): ?string
    {
        if (array_key_exists('login_identifier_type', $overrides)) {
            return $overrides['login_identifier_type'];
        }

        if ($this->hasConcreteMethod($request, 'identifierType')) {
            return $request->identifierType();
        }

        if ($user?->email !== null) {
            return 'email';
        }

        return $user?->nik !== null ? 'nik' : null;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function loginIdentifier(Request $request, ?User $user, array $overrides): ?string
    {
        if (array_key_exists('login_identifier', $overrides)) {
            return $overrides['login_identifier'];
        }

        if ($this->hasConcreteMethod($request, 'identifier')) {
            return $request->identifier();
        }

        return $user?->email ?? $user?->nik;
    }

    private function identifierHash(?string $identifierType, ?string $identifier): ?string
    {
        if ($identifierType === null || $identifier === null || $identifier === '') {
            return null;
        }

        return hash_hmac(
            'sha256',
            $identifierType.'|'.$identifier,
            $this->auditHashKey()
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userContext(?User $user, ?UserPosition $userPosition): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'nama' => $user->nama,
            'account_type' => $user->account_type,
            'status' => $user->status,
            'tahun_aktif' => $user->tahun_aktif,
            'position' => $userPosition?->toAuthenticationContext(),
        ];
    }

    private function attemptNumber(Request $request): int
    {
        if (! $this->hasConcreteMethod($request, 'throttleKey')) {
            return 1;
        }

        return max(1, RateLimiter::attempts($request->throttleKey()));
    }

    private function rememberMe(Request $request): ?bool
    {
        if (! $this->hasConcreteMethod($request, 'remember')) {
            return null;
        }

        return $request->remember();
    }

    private function captchaIsConfigured(Request $request): bool
    {
        if (! $this->hasConcreteMethod($request, 'captchaIsConfigured')) {
            return false;
        }

        return $request->captchaIsConfigured();
    }

    private function captchaAction(Request $request): ?string
    {
        if (! $this->captchaIsConfigured($request) || ! $this->hasConcreteMethod($request, 'captchaAction')) {
            return null;
        }

        return $request->captchaAction();
    }

    private function hasConcreteMethod(Request $request, string $method): bool
    {
        return method_exists($request, $method);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $eventContext
     */
    private function contextValue(array $overrides, array $eventContext, string $key): mixed
    {
        if (array_key_exists($key, $overrides)) {
            return $overrides[$key];
        }

        return $eventContext[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function dateTimeValue(array $overrides, string $key): CarbonImmutable
    {
        $value = $overrides[$key] ?? null;

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return CarbonImmutable::parse($value);
        }

        return CarbonImmutable::instance(now());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function retentionUntil(array $overrides, DateTimeInterface $occurredAt): mixed
    {
        if (array_key_exists('retention_until', $overrides)) {
            return $overrides['retention_until'];
        }

        return $this->loginEventRetention->until($occurredAt);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $attributes
     */
    private function eventHash(array $overrides, array $attributes): ?string
    {
        if (array_key_exists('event_hash', $overrides)) {
            return is_string($overrides['event_hash']) || $overrides['event_hash'] === null
                ? $overrides['event_hash']
                : null;
        }

        return $this->loginEventIntegrity->hash($attributes);
    }

    private function auditHashKey(): string
    {
        $auditHashKey = config('auth.audit.hash_key', config('auth.audit_hash_key'));

        if (is_string($auditHashKey) && $auditHashKey !== '') {
            return $auditHashKey;
        }

        if (app()->environment('local', 'testing')) {
            $appKey = config('app.key');

            if (is_string($appKey) && $appKey !== '') {
                return $appKey;
            }
        }

        throw new RuntimeException('AUDIT_HASH_KEY must be configured for authentication audit hashing.');
    }
}
