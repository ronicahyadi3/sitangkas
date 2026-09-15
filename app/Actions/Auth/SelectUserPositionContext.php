<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\MfaSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectUserPositionContext
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private MfaSession $mfaSession,
        private RecordAuthenticationEvent $recordAuthenticationEvent,
        private EnforceSingleDeviceAuthentication $singleDeviceAuthentication
    ) {}

    /**
     * @return array{position_changed: bool, admin_super_selected: bool}
     */
    public function handle(Request $request, User $user, UserPosition $selectedUserPosition): array
    {
        $previousUserPositionId = $this->currentUserContext->activeUserPositionId($request);
        $positionChanged = filled($previousUserPositionId)
            && (string) $previousUserPositionId !== (string) $selectedUserPosition->getKey();
        $selectedPositionIsAdminSuper = $this->currentUserContext->isAdminSuperPosition($selectedUserPosition);

        DB::transaction(function () use ($request, $user, $selectedUserPosition, $previousUserPositionId, $positionChanged, $selectedPositionIsAdminSuper): void {
            if ($positionChanged) {
                $this->mfaSession->forget($request);
            }

            $this->currentUserContext->activatePosition($request, $selectedUserPosition);
            $selectedUserPosition->markAsUsed();

            if ($selectedPositionIsAdminSuper) {
                $this->singleDeviceAuthentication->disableRememberMeForCurrentSessionOnly($request);
            }

            $this->recordAuthenticationEvent->handle($request, $user, $selectedUserPosition, [
                'event_type' => LoginEvent::EVENT_CONTEXT_SWITCHED,
                'result' => LoginEvent::RESULT_SUCCESS,
                'message' => $positionChanged
                    ? 'Posisi kerja berhasil diganti.'
                    : 'Posisi kerja berhasil dipilih.',
                'auth_method' => 'session',
                'http_status' => 302,
                'remember_me' => null,
                'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
                'metadata' => [
                    'context_type' => 'real_user_position',
                    'previous_user_position_id' => $previousUserPositionId,
                    'selected_user_position_id' => $selectedUserPosition->getKey(),
                    'position_changed' => $positionChanged,
                    'mfa_session_reset' => $positionChanged,
                    'tahun_aktif' => $this->currentUserContext->activeYear($request),
                    'remember_me_disabled' => $selectedPositionIsAdminSuper,
                ],
            ]);
        }, attempts: 3);

        $request->session()->forget('url.intended');

        return [
            'position_changed' => $positionChanged,
            'admin_super_selected' => $selectedPositionIsAdminSuper,
        ];
    }
}
