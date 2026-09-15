<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateSession;
use App\Actions\Auth\EnforceSingleDeviceAuthentication;
use App\Actions\Auth\RecordAuthenticationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreAuthenticatedSessionRequest;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        $currentYear = (int) now()->year;

        return view('auth.login', [
            'currentYear' => $currentYear,
            'selectedYear' => old('tahun', $currentYear),
        ]);
    }

    public function store(StoreAuthenticatedSessionRequest $request, AuthenticateSession $authenticateSession): RedirectResponse
    {
        $authenticateSession->handle($request);

        return redirect()->route(
            Route::has('positions.index') ? 'positions.index' : 'dashboard'
        );
    }

    public function destroy(
        Request $request,
        RecordAuthenticationEvent $recordAuthenticationEvent,
        CurrentUserContext $currentUserContext,
        EnforceSingleDeviceAuthentication $singleDeviceAuthentication
    ): RedirectResponse {
        $user = $request->user();
        $userPosition = $currentUserContext->activePosition($request);
        $authenticatedUser = $user instanceof User ? $user : null;

        $recordAuthenticationEvent->handle($request, $authenticatedUser, $userPosition, [
            'event_type' => LoginEvent::EVENT_LOGOUT,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Logout berhasil.',
            'auth_method' => 'session',
            'http_status' => 302,
            'login_identifier_type' => $this->logoutIdentifierType($authenticatedUser),
            'login_identifier' => $this->logoutIdentifier($authenticatedUser),
            'remember_me' => null,
            'session_id_hash' => $recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'tahun_aktif' => $currentUserContext->activeYear($request),
                'active_user_position_id' => $currentUserContext->activeUserPositionId($request),
            ],
        ]);

        if ($authenticatedUser instanceof User) {
            $singleDeviceAuthentication->clearRememberTokenExpiry($authenticatedUser);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar dari sistem.');
    }

    private function logoutIdentifierType(?User $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        return filled($user->nik) ? 'nik' : 'email';
    }

    private function logoutIdentifier(?User $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        return filled($user->nik) ? $user->nik : $user->email;
    }
}
