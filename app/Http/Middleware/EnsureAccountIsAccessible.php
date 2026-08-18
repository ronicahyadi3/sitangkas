<?php

namespace App\Http\Middleware;

use App\Actions\Auth\RecordAuthenticationEvent;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Auth\CurrentUserContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsAccessible
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private RecordAuthenticationEvent $recordAuthenticationEvent
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $this->releaseExpiredLock($user);

        $failureCode = $this->blockedFailureCode($user);

        if ($failureCode !== null) {
            return $this->logoutBlockedAccount($request, $user, $failureCode);
        }

        return $next($request);
    }

    private function releaseExpiredLock(User $user): void
    {
        if ($user->status !== User::STATUS_LOCKED || $user->locked_until === null || $user->locked_until->isFuture()) {
            return;
        }

        $updatedAttributes = [
            'status' => User::STATUS_ACTIVE,
            'status_changed_at' => now(),
            'status_reason' => 'Kunci akun otomatis telah kedaluwarsa.',
            'locked_at' => null,
            'locked_until' => null,
            'lock_reason' => null,
        ];

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update($updatedAttributes);

        $user->forceFill($updatedAttributes);
    }

    private function blockedFailureCode(User $user): ?string
    {
        if ($user->account_type === User::ACCOUNT_TYPE_SERVICE) {
            return 'service_account_web_login_denied';
        }

        if ($user->isLocked()) {
            return 'account_locked';
        }

        if ($user->status === User::STATUS_PENDING) {
            return 'account_pending';
        }

        if ($user->status === User::STATUS_INACTIVE) {
            return 'account_inactive';
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            return 'account_suspended';
        }

        if (! $user->isActive()) {
            return 'account_not_active';
        }

        return null;
    }

    private function logoutBlockedAccount(Request $request, User $user, string $failureCode): Response
    {
        $message = $this->messageForFailureCode($failureCode);
        $httpStatus = $this->httpStatusForFailureCode($failureCode);
        $viaRemember = Auth::guard('web')->viaRemember();

        $this->recordAuthenticationEvent->handle($request, $user, $this->currentUserContext->realActivePosition($request), [
            'event_type' => LoginEvent::EVENT_SESSION_REVOKED,
            'result' => LoginEvent::RESULT_REVOKED,
            'failure_code' => $failureCode,
            'message' => $message,
            'auth_method' => $viaRemember ? 'remember_token' : 'session',
            'http_status' => $request->expectsJson() ? $httpStatus : 302,
            'remember_me' => $viaRemember ? true : null,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'account_status' => $user->status,
                'locked_at' => $user->locked_at?->toISOString(),
                'locked_until' => $user->locked_until?->toISOString(),
                'lock_reason' => $user->lock_reason,
                'status_reason' => $user->status_reason,
                'active_user_position_id' => $this->currentUserContext->activeUserPositionId($request),
            ],
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], $httpStatus);
        }

        return redirect()->route('login')->with('status', $message);
    }

    private function messageForFailureCode(string $failureCode): string
    {
        return match ($failureCode) {
            'account_locked' => 'Akun Anda sedang terkunci. Silakan hubungi administrator.',
            'account_pending' => 'Akun Anda masih berstatus pending. Silakan hubungi administrator.',
            'account_inactive' => 'Akun Anda sedang nonaktif. Silakan hubungi administrator.',
            'account_suspended' => 'Akun Anda sedang disuspended. Silakan hubungi administrator.',
            'service_account_web_login_denied' => 'Akun service tidak dapat mengakses aplikasi web.',
            default => 'Akun Anda tidak dapat mengakses aplikasi. Silakan hubungi administrator.',
        };
    }

    private function httpStatusForFailureCode(string $failureCode): int
    {
        return $failureCode === 'account_locked' ? 423 : 403;
    }
}
