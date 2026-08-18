<?php

namespace App\Http\Middleware;

use App\Actions\Auth\RecordAuthenticationEvent;
use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\MfaSession;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private MfaSession $mfaSession,
        private RecordAuthenticationEvent $recordAuthenticationEvent
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isBypassedRoute($request)) {
            return $next($request);
        }

        $user = $this->currentUserContext->user($request);

        if (! $user instanceof User) {
            return $this->redirectOrJson($request, 'login', 'Session pengguna tidak valid.', 401);
        }

        if (! $this->currentUserContext->hasSessionContext($request)) {
            if (! $this->currentUserContext->hasSelectablePositions($user)) {
                return $this->redirectOrJson(
                    $request,
                    'login.no_active_position',
                    'Akun Anda belum memiliki posisi aktif.'
                );
            }

            return $this->redirectOrJson(
                $request,
                'login.context',
                'Silakan pilih konteks kerja sebelum verifikasi MFA.'
            );
        }

        $realActiveUserPosition = $this->currentUserContext->realActivePosition($request);

        if (! $realActiveUserPosition instanceof UserPosition) {
            $this->currentUserContext->forgetActivePosition($request);

            if (! $this->currentUserContext->hasSelectablePositions($user)) {
                return $this->redirectOrJson(
                    $request,
                    'login.no_active_position',
                    'Akun Anda belum memiliki posisi aktif.'
                );
            }

            return $this->redirectOrJson(
                $request,
                'login.context',
                'Konteks kerja sudah tidak aktif. Silakan pilih kembali sebelum verifikasi MFA.'
            );
        }

        $this->recordExpiredMfaSession($request, $user, $realActiveUserPosition);

        if ($this->mfaSession->shouldSetup($request, $user, $realActiveUserPosition)) {
            return $this->redirectOrJson(
                $request,
                'login.mfa.setup',
                'Aktifkan MFA sebelum melanjutkan.'
            );
        }

        if ($this->mfaSession->shouldChallenge($request, $user, $realActiveUserPosition)) {
            return $this->redirectOrJson(
                $request,
                'login.mfa',
                'Verifikasi MFA sebelum melanjutkan.',
                shouldStoreIntendedUrl: true
            );
        }

        return $next($request);
    }

    private function isBypassedRoute(Request $request): bool
    {
        return $request->routeIs(
            'login.mfa',
            'login.mfa.store',
            'login.mfa.setup',
            'login.mfa.setup.store',
            'login.context',
            'login.context.store',
            'login.no_active_position',
            'logout'
        );
    }

    private function redirectOrJson(
        Request $request,
        string $routeName,
        string $message,
        int $status = 409,
        bool $shouldStoreIntendedUrl = false
    ): RedirectResponse|JsonResponse {
        $redirectTo = $this->routeUrl($routeName);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'redirect_to' => $redirectTo,
            ], $status);
        }

        $redirect = $shouldStoreIntendedUrl
            ? redirect()->guest($redirectTo)
            : redirect()->to($redirectTo);

        return $redirect->with('status', $message);
    }

    private function routeUrl(string $routeName): string
    {
        if (Route::has($routeName)) {
            return route($routeName);
        }

        return Route::has('login') ? route('login') : url('/');
    }

    private function recordExpiredMfaSession(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        $state = $this->mfaSession->state($request);

        if ($state === null) {
            return;
        }

        if ($state['user_id'] !== (string) $user->getKey()) {
            return;
        }

        if ($state['real_user_position_id'] !== (string) $realActiveUserPosition->getKey()) {
            return;
        }

        if (! $state['expires_at']->isPast()) {
            return;
        }

        $this->mfaSession->forget($request);

        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_CHALLENGE,
            'result' => LoginEvent::RESULT_EXPIRED,
            'failure_code' => 'mfa_session_expired',
            'message' => 'Session MFA sudah kedaluwarsa.',
            'auth_method' => 'mfa_challenge',
            'mfa_method' => $state['method'],
            'mfa_result' => LoginEvent::RESULT_EXPIRED,
            'http_status' => 409,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'verified_at' => $state['verified_at']->toISOString(),
                'expires_at' => $state['expires_at']->toISOString(),
            ],
        ]);
    }
}
