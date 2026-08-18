<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EnforceSingleDeviceAuthentication;
use App\Actions\Auth\RecordAuthenticationEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreLoginContextRequest;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class LoginContextController extends Controller
{
    public function __construct(private CurrentUserContext $currentUserContext) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->currentUserContext->user($request);

        $userPositions = $user instanceof User
            ? $this->currentUserContext->selectablePositions($user)
            : collect();

        if ($user instanceof User && $userPositions->isEmpty() && Route::has('login.no_active_position')) {
            return redirect()->route('login.no_active_position');
        }

        return view('auth.context', [
            'user' => $user,
            'userPositions' => $userPositions,
            'activeUserPositionId' => $this->currentUserContext->activeUserPositionId($request),
            'activeYear' => $this->currentUserContext->activeYear($request),
        ]);
    }

    public function store(
        StoreLoginContextRequest $request,
        RecordAuthenticationEvent $recordAuthenticationEvent,
        EnforceSingleDeviceAuthentication $singleDeviceAuthentication
    ): RedirectResponse {
        $user = $this->currentUserContext->user($request);
        $selectedUserPosition = $request->selectedUserPosition();
        $previousUserPositionId = $this->currentUserContext->activeUserPositionId($request);
        $selectedPositionIsAdminSuper = $this->currentUserContext->isAdminSuperPosition($selectedUserPosition);

        DB::transaction(function () use ($request, $recordAuthenticationEvent, $singleDeviceAuthentication, $user, $selectedUserPosition, $previousUserPositionId, $selectedPositionIsAdminSuper): void {
            $this->currentUserContext->activatePosition($request, $selectedUserPosition);
            $selectedUserPosition->markAsUsed();

            if ($selectedPositionIsAdminSuper && $user instanceof User) {
                $singleDeviceAuthentication->disableRememberMe($request, $user);
            }

            $recordAuthenticationEvent->handle($request, $user instanceof User ? $user : null, $selectedUserPosition, [
                'event_type' => LoginEvent::EVENT_CONTEXT_SWITCHED,
                'result' => LoginEvent::RESULT_SUCCESS,
                'message' => 'Konteks kerja berhasil dipilih.',
                'auth_method' => 'session',
                'http_status' => 302,
                'remember_me' => null,
                'session_id_hash' => $recordAuthenticationEvent->sessionIdHash($request),
                'metadata' => [
                    'previous_user_position_id' => $previousUserPositionId,
                    'selected_user_position_id' => $selectedUserPosition->getKey(),
                    'tahun_aktif' => $this->currentUserContext->activeYear($request),
                    'remember_me_disabled' => $selectedPositionIsAdminSuper,
                ],
            ]);
        }, attempts: 3);

        if ($selectedPositionIsAdminSuper && Route::has('login.post')) {
            return redirect()
                ->route('login.post')
                ->with('status', 'Konteks nyata Admin Super berhasil dipilih. Silakan pilih acting context.');
        }

        return redirect()
            ->intended(route('dashboard'))
            ->with('status', 'Konteks kerja berhasil dipilih.');
    }
}
