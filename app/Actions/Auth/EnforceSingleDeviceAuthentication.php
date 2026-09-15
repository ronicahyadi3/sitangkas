<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Auth\RememberMePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnforceSingleDeviceAuthentication
{
    public const AUTHENTICATED_AT_SESSION_KEY = 'auth_single_device_authenticated_at';

    public const REMEMBER_CONTEXT_RESTORED_SESSION_KEY = 'auth_remember_context_restored';

    public const REMEMBER_LOGIN_AUDITED_SESSION_KEY = 'auth_remember_login_audited';

    public const REMEMBER_SESSION_EXPIRES_AT_SESSION_KEY = 'auth_remember_session_expires_at';

    public function __construct(private RememberMePolicy $rememberMePolicy) {}

    /**
     * @return array{authenticated_at: Carbon, revoked_session_count: int, remember_token_rotated: bool, remember_token_expires_at: Carbon|null}
     */
    public function renew(Request $request, User $user, bool $remember = false): array
    {
        $authenticatedAt = now();
        $rememberToken = Str::random(60);
        $rememberTokenExpiresAt = $remember
            ? $this->rememberMePolicy->expiresAt($authenticatedAt)
            : null;
        $revokedSessionCount = 0;

        DB::transaction(function () use ($request, $user, $authenticatedAt, $rememberToken, $rememberTokenExpiresAt, &$revokedSessionCount): void {
            $revokedSessionCount = $this->revokeOtherDatabaseSessions($request, $user);

            DB::table($user->getTable())
                ->where($user->getKeyName(), $user->getKey())
                ->update([
                    'remember_token' => $rememberToken,
                    'remember_token_expires_at' => $rememberTokenExpiresAt,
                    'sessions_invalidated_at' => $authenticatedAt,
                    'updated_at' => $authenticatedAt,
                ]);
        }, attempts: 3);

        $user->forceFill([
            'remember_token' => $rememberToken,
            'remember_token_expires_at' => $rememberTokenExpiresAt,
            'sessions_invalidated_at' => $authenticatedAt,
        ]);

        return [
            'authenticated_at' => $authenticatedAt,
            'revoked_session_count' => $revokedSessionCount,
            'remember_token_rotated' => true,
            'remember_token_expires_at' => $rememberTokenExpiresAt,
        ];
    }

    public function markCurrentSession(Request $request, ?Carbon $authenticatedAt = null): void
    {
        $request->session()->put(
            self::AUTHENTICATED_AT_SESSION_KEY,
            ($authenticatedAt ?? now())->getTimestamp()
        );
    }

    public function markRememberedSession(Request $request, ?Carbon $expiresAt): void
    {
        if (! $expiresAt instanceof Carbon) {
            $this->clearRememberedSession($request);

            return;
        }

        $request->session()->put(self::REMEMBER_SESSION_EXPIRES_AT_SESSION_KEY, $expiresAt->getTimestamp());
    }

    public function clearRememberedSession(Request $request): void
    {
        $request->session()->forget(self::REMEMBER_SESSION_EXPIRES_AT_SESSION_KEY);
    }

    public function markLegacySessionAsCurrent(Request $request, User $user): void
    {
        if ($request->session()->has(self::AUTHENTICATED_AT_SESSION_KEY)) {
            return;
        }

        if ($user->sessions_invalidated_at !== null) {
            return;
        }

        $this->markCurrentSession($request);
    }

    public function currentSessionIsRevoked(Request $request, User $user): bool
    {
        if ($user->sessions_invalidated_at === null) {
            return false;
        }

        $authenticatedAt = $this->authenticatedAt($request);

        if ($authenticatedAt === null) {
            return true;
        }

        return $user->sessions_invalidated_at->getTimestamp() > $authenticatedAt;
    }

    public function rememberedSessionHasExpired(Request $request, User $user): bool
    {
        $expiresAt = $this->rememberedSessionExpiresAt($request);

        if ($expiresAt === null) {
            return false;
        }

        return $expiresAt->isPast()
            || $this->rememberMePolicy->tokenHasExpired($user);
    }

    public function rememberedSessionExpiresAt(Request $request): ?Carbon
    {
        $expiresAt = $request->session()->get(self::REMEMBER_SESSION_EXPIRES_AT_SESSION_KEY);

        if (is_int($expiresAt)) {
            return Carbon::createFromTimestamp($expiresAt);
        }

        if (is_string($expiresAt) && ctype_digit($expiresAt)) {
            return Carbon::createFromTimestamp((int) $expiresAt);
        }

        return null;
    }

    public function disableRememberMe(Request $request, User $user): void
    {
        $singleDeviceState = $this->renew($request, $user, remember: false);

        $this->markCurrentSession($request, $singleDeviceState['authenticated_at']);
        $this->clearRememberedSession($request);
        $this->forgetRememberCookie();
    }

    public function disableRememberMeForCurrentSessionOnly(Request $request): void
    {
        $this->clearRememberedSession($request);
        $this->forgetRememberCookie();
    }

    public function clearRememberTokenExpiry(User $user): void
    {
        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update([
                'remember_token_expires_at' => null,
            ]);

        $user->forceFill([
            'remember_token_expires_at' => null,
        ]);
    }

    public function forgetRememberCookie(): void
    {
        $guard = Auth::guard('web');

        if (! method_exists($guard, 'getRecallerName')) {
            return;
        }

        Cookie::queue(Cookie::forget(
            $guard->getRecallerName(),
            (string) config('session.path', '/'),
            config('session.domain')
        ));
    }

    private function authenticatedAt(Request $request): ?int
    {
        $authenticatedAt = $request->session()->get(self::AUTHENTICATED_AT_SESSION_KEY);

        if (is_int($authenticatedAt)) {
            return $authenticatedAt;
        }

        if (is_string($authenticatedAt) && ctype_digit($authenticatedAt)) {
            return (int) $authenticatedAt;
        }

        return null;
    }

    private function revokeOtherDatabaseSessions(Request $request, User $user): int
    {
        if ((string) config('session.driver', 'database') !== 'database') {
            return 0;
        }

        $sessionTable = $this->sessionTable();
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        return DB::table($sessionTable)
            ->where('user_id', $user->getKey())
            ->when(is_string($currentSessionId) && $currentSessionId !== '', function ($query) use ($currentSessionId): void {
                $query->where('id', '!=', $currentSessionId);
            })
            ->delete();
    }

    private function sessionTable(): string
    {
        $sessionTable = config('session.table', 'sessions');

        return is_string($sessionTable) && $sessionTable !== '' ? $sessionTable : 'sessions';
    }
}
