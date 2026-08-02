<?php

namespace App\Http\Middleware;

use App\Actions\Auth\EnforceSingleDeviceAuthentication;
use App\Actions\Auth\RecordAuthenticationEvent;
use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\RememberMePolicy;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleDeviceSession
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private RememberMePolicy $rememberMePolicy,
        private EnforceSingleDeviceAuthentication $singleDeviceAuthentication,
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

        $authenticatedViaRemember = $this->authenticatedViaRememberCookie();

        if ($authenticatedViaRemember) {
            $this->singleDeviceAuthentication->markCurrentSession($request);
        } else {
            $this->singleDeviceAuthentication->markLegacySessionAsCurrent($request, $user);
        }

        if ($this->singleDeviceAuthentication->currentSessionIsRevoked($request, $user)) {
            return $this->logoutRevokedSession(
                $request,
                $user,
                'Sesi Anda sudah dipindahkan ke device lain. Silakan login kembali.',
                'single_device_session_revoked'
            );
        }

        if ($authenticatedViaRemember && $this->rememberMePolicy->tokenHasExpired($user)) {
            $expiredAt = $user->remember_token_expires_at;

            $this->singleDeviceAuthentication->disableRememberMe($request, $user);

            return $this->logoutRevokedSession(
                $request,
                $user,
                'Remember me sudah kedaluwarsa. Silakan login kembali.',
                'remember_me_expired',
                [
                    'remember_token_expires_at' => $expiredAt?->toISOString(),
                    'remember_me_duration_minutes' => $this->rememberMePolicy->durationMinutes(),
                ]
            );
        }

        if ($authenticatedViaRemember) {
            $this->singleDeviceAuthentication->markRememberedSession($request, $user->remember_token_expires_at);
        }

        if ($this->singleDeviceAuthentication->rememberedSessionHasExpired($request, $user)) {
            $rememberSessionExpiresAt = $this->singleDeviceAuthentication->rememberedSessionExpiresAt($request);
            $rememberTokenExpiresAt = $user->remember_token_expires_at;

            $this->singleDeviceAuthentication->disableRememberMe($request, $user);

            return $this->logoutRevokedSession(
                $request,
                $user,
                'Remember me sudah kedaluwarsa. Silakan login kembali.',
                'remember_me_expired',
                [
                    'remember_session_expires_at' => $rememberSessionExpiresAt?->toISOString(),
                    'remember_token_expires_at' => $rememberTokenExpiresAt?->toISOString(),
                    'remember_me_duration_minutes' => $this->rememberMePolicy->durationMinutes(),
                    'remembered_session' => true,
                    'remember_session_enforced' => true,
                ]
            );
        }

        if ($authenticatedViaRemember && ! $this->restoreRememberedNonAdminContext($request, $user)) {
            $this->singleDeviceAuthentication->disableRememberMe($request, $user);

            return $this->logoutRevokedSession(
                $request,
                $user,
                'Remember me tidak dapat memulihkan konteks kerja. Silakan login kembali.',
                'remember_me_context_restore_denied'
            );
        }

        $realActiveUserPosition = $this->currentUserContext->realActivePosition($request);

        if (
            ($authenticatedViaRemember || $this->singleDeviceAuthentication->rememberedSessionExpiresAt($request) !== null)
            && $realActiveUserPosition instanceof UserPosition
            && ! $this->rememberMePolicy->allows($realActiveUserPosition)
        ) {
            $rememberTokenExpiresAt = $user->remember_token_expires_at?->toISOString();

            $this->singleDeviceAuthentication->disableRememberMe($request, $user);

            return $this->logoutRevokedSession(
                $request,
                $user,
                'Remember me tidak berlaku untuk konteks Admin Super. Silakan login kembali.',
                'remember_me_admin_super_denied',
                [
                    'remember_token_expires_at' => $rememberTokenExpiresAt,
                    'remembered_session' => true,
                ]
            );
        }

        return $next($request);
    }

    private function restoreRememberedNonAdminContext(Request $request, User $user): bool
    {
        if (! $this->rememberMePolicy->restoreNonAdminContext()) {
            return true;
        }

        if ($this->currentUserContext->hasSessionContext($request)) {
            return true;
        }

        $userPosition = $this->currentUserContext
            ->selectablePositionsQuery($user)
            ->first();

        if (! $userPosition instanceof UserPosition) {
            return false;
        }

        if (! $this->rememberMePolicy->allows($userPosition)) {
            return false;
        }

        $this->currentUserContext->activatePosition($request, $userPosition);
        $userPosition->markAsUsed();
        $request->session()->put(EnforceSingleDeviceAuthentication::REMEMBER_CONTEXT_RESTORED_SESSION_KEY, true);

        $this->recordRememberedLogin($request, $user, $userPosition);

        return true;
    }

    private function recordRememberedLogin(Request $request, User $user, UserPosition $userPosition): void
    {
        if ($request->session()->has(EnforceSingleDeviceAuthentication::REMEMBER_LOGIN_AUDITED_SESSION_KEY)) {
            return;
        }

        $request->session()->put(EnforceSingleDeviceAuthentication::REMEMBER_LOGIN_AUDITED_SESSION_KEY, true);

        $this->recordAuthenticationEvent->handle($request, $user, $userPosition, [
            'event_type' => LoginEvent::EVENT_LOGIN,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Login otomatis melalui remember me berhasil.',
            'auth_method' => 'remember_token',
            'http_status' => 302,
            'remember_me' => true,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'remember_context_restored' => true,
                'single_device_enforced' => $this->rememberMePolicy->singleDeviceEnabled(),
                'active_user_position_id' => $userPosition->getKey(),
                'tahun_aktif' => $this->currentUserContext->activeYear($request),
            ],
        ]);
    }

    private function logoutRevokedSession(
        Request $request,
        User $user,
        string $message,
        string $failureCode,
        array $metadata = []
    ): RedirectResponse|JsonResponse {
        $authenticatedViaRemember = $this->authenticatedViaRememberCookie();
        $rememberedSession = $this->singleDeviceAuthentication->rememberedSessionExpiresAt($request) !== null
            || ($metadata['remembered_session'] ?? false) === true;

        $this->recordAuthenticationEvent->handle($request, $user, $this->currentUserContext->realActivePosition($request), [
            'event_type' => LoginEvent::EVENT_SESSION_REVOKED,
            'result' => LoginEvent::RESULT_REVOKED,
            'failure_code' => $failureCode,
            'message' => $message,
            'auth_method' => ($authenticatedViaRemember || $rememberedSession) ? 'remember_token' : 'session',
            'http_status' => $request->expectsJson() ? 401 : 302,
            'remember_me' => ($authenticatedViaRemember || $rememberedSession) ? true : null,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'single_device_enforced' => $this->rememberMePolicy->singleDeviceEnabled(),
                'via_remember' => $authenticatedViaRemember,
                'remembered_session' => $rememberedSession,
                'active_user_position_id' => $this->currentUserContext->activeUserPositionId($request),
            ] + $metadata,
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 401);
        }

        return redirect()->route('login')->with('status', $message);
    }

    private function authenticatedViaRememberCookie(): bool
    {
        return Auth::guard('web')->viaRemember();
    }
}
