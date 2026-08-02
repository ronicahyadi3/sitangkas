<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\MfaPolicy;
use Illuminate\Http\Request;

class StartMfaChallenge
{
    public function __construct(private RecordAuthenticationEvent $recordAuthenticationEvent) {}

    public function handle(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_CHALLENGE,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Challenge MFA dimulai.',
            'auth_method' => 'mfa_challenge',
            'http_status' => 200,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'available_mfa_methods' => [
                    MfaPolicy::METHOD_TOTP,
                    MfaPolicy::METHOD_RECOVERY_CODE,
                ],
                'real_user_position_id' => $realActiveUserPosition->getKey(),
            ],
        ]);
    }
}
