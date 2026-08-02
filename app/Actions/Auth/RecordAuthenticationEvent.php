<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

class RecordAuthenticationEvent
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    public function handle(Request $request, ?User $user = null, ?UserPosition $userPosition = null, array $overrides = []): LoginEvent
    {
        $identifierType = $this->loginIdentifierType($request, $user, $overrides);
        $identifier = $this->loginIdentifier($request, $user, $overrides);

        return LoginEvent::create([
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
            'request_id' => $overrides['request_id'] ?? $this->uuidHeader($request, 'X-Request-Id'),
            'correlation_id' => $overrides['correlation_id'] ?? $this->uuidHeader($request, 'X-Correlation-Id'),
            'session_id_hash' => $overrides['session_id_hash'] ?? null,
            'token_id_hash' => $overrides['token_id_hash'] ?? null,
            'source_channel' => $overrides['source_channel'] ?? 'web',
            'route_name' => $overrides['route_name'] ?? $request->route()?->getName(),
            'request_path' => $overrides['request_path'] ?? Str::limit($request->path(), 500, ''),
            'http_method' => $overrides['http_method'] ?? $request->method(),
            'http_status' => $overrides['http_status'] ?? null,
            'ip_address' => $overrides['ip_address'] ?? $request->ip(),
            'proxy_ip_address' => $overrides['proxy_ip_address'] ?? null,
            'forwarded_for' => $overrides['forwarded_for'] ?? $this->forwardedFor($request),
            'network_asn' => $overrides['network_asn'] ?? null,
            'network_organization' => $overrides['network_organization'] ?? null,
            'country_code' => $overrides['country_code'] ?? null,
            'region' => $overrides['region'] ?? null,
            'city' => $overrides['city'] ?? null,
            'is_vpn' => $overrides['is_vpn'] ?? null,
            'is_proxy' => $overrides['is_proxy'] ?? null,
            'is_tor' => $overrides['is_tor'] ?? null,
            'risk_score' => $overrides['risk_score'] ?? null,
            'user_agent' => $overrides['user_agent'] ?? $request->userAgent(),
            'device_type' => $overrides['device_type'] ?? null,
            'device_name' => $overrides['device_name'] ?? null,
            'browser_name' => $overrides['browser_name'] ?? null,
            'browser_version' => $overrides['browser_version'] ?? null,
            'platform_name' => $overrides['platform_name'] ?? null,
            'platform_version' => $overrides['platform_version'] ?? null,
            'client_timezone' => $overrides['client_timezone'] ?? null,
            'accept_language' => $overrides['accept_language'] ?? $request->header('Accept-Language'),
            'device_fingerprint_hash' => $overrides['device_fingerprint_hash'] ?? null,
            'captcha_provider' => $overrides['captcha_provider'] ?? ($this->captchaIsConfigured($request) ? 'recaptcha_v3' : null),
            'captcha_score' => $overrides['captcha_score'] ?? null,
            'captcha_action' => $overrides['captcha_action'] ?? $this->captchaAction($request),
            'captcha_success' => $overrides['captcha_success'] ?? null,
            'captcha_error_codes' => $overrides['captcha_error_codes'] ?? null,
            'application_version' => $overrides['application_version'] ?? null,
            'environment' => $overrides['environment'] ?? config('app.env'),
            'server_node' => $overrides['server_node'] ?? (gethostname() ?: null),
            'metadata' => $overrides['metadata'] ?? null,
            'event_hash' => $overrides['event_hash'] ?? null,
            'occurred_at' => $overrides['occurred_at'] ?? now(),
            'retention_until' => $overrides['retention_until'] ?? null,
        ]);
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

    private function auditHashKey(): string
    {
        $auditHashKey = config('auth.audit_hash_key');

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

    private function uuidHeader(Request $request, string $header): ?string
    {
        $value = $request->header($header);

        if (! is_string($value) || ! Str::isUuid($value)) {
            return null;
        }

        return $value;
    }

    /**
     * @return list<string>|null
     */
    private function forwardedFor(Request $request): ?array
    {
        $forwardedFor = $request->headers->get('X-Forwarded-For');

        if (! is_string($forwardedFor) || trim($forwardedFor) === '') {
            return null;
        }

        return collect(explode(',', $forwardedFor))
            ->map(fn (string $ipAddress): string => trim($ipAddress))
            ->filter()
            ->values()
            ->all();
    }
}
