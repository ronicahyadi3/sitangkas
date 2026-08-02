<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\TotpAuthenticator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartTotpEnrollment
{
    public function __construct(
        private MfaPolicy $mfaPolicy,
        private TotpAuthenticator $totpAuthenticator,
        private RecordAuthenticationEvent $recordAuthenticationEvent
    ) {}

    /**
     * @return array{manual_entry_key: string, provisioning_uri: string, inline_qr_code: string|null, pending_secret_created_at: string}
     */
    public function handle(Request $request, User $user, UserPosition $realActiveUserPosition): array
    {
        $this->ensureTotpEnrollmentCanStart($request, $user, $realActiveUserPosition);

        $pendingSecretWasReused = filled($user->mfa_pending_secret);
        $pendingSecretCreatedAt = $user->mfa_pending_secret_created_at;
        $pendingSecret = $user->mfa_pending_secret;

        if (! is_string($pendingSecret) || $pendingSecret === '' || $pendingSecretCreatedAt === null) {
            $pendingSecretWasReused = false;
            $pendingSecret = $this->totpAuthenticator->generateSecret();
            $pendingSecretCreatedAt = now();

            DB::transaction(function () use ($user, $pendingSecret, $pendingSecretCreatedAt): void {
                $user->forceFill([
                    'mfa_pending_secret' => $pendingSecret,
                    'mfa_pending_secret_created_at' => $pendingSecretCreatedAt,
                ])->save();
            }, attempts: 3);
        }

        $inlineQrCode = $this->totpAuthenticator->inlineQrCode($user, $pendingSecret);

        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_CHALLENGE,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Setup MFA TOTP dimulai.',
            'auth_method' => 'mfa_setup',
            'mfa_method' => MfaPolicy::METHOD_TOTP,
            'http_status' => 200,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'pending_secret_reused' => $pendingSecretWasReused,
                'pending_secret_created_at' => $pendingSecretCreatedAt->toISOString(),
                'totp_digits' => $this->mfaPolicy->totpDigits(),
                'totp_period_seconds' => $this->mfaPolicy->totpPeriodSeconds(),
                'totp_window' => $this->mfaPolicy->totpWindow(),
                'inline_qr_available' => is_string($inlineQrCode),
            ],
        ]);

        return [
            'manual_entry_key' => $pendingSecret,
            'provisioning_uri' => $this->totpAuthenticator->provisioningUri($user, $pendingSecret),
            'inline_qr_code' => $inlineQrCode,
            'pending_secret_created_at' => $pendingSecretCreatedAt->toISOString(),
        ];
    }

    private function ensureTotpEnrollmentCanStart(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        if (! $this->mfaPolicy->supportsConfiguredMethod()) {
            $this->recordBlockedEnrollment($request, $user, $realActiveUserPosition, 'mfa_method_unsupported', 'Metode MFA tidak didukung.');

            throw ValidationException::withMessages([
                'mfa' => 'Metode MFA tidak didukung. Hubungi administrator.',
            ]);
        }

        if (! $this->mfaPolicy->availableFor($realActiveUserPosition)) {
            $this->recordBlockedEnrollment($request, $user, $realActiveUserPosition, 'mfa_not_available', 'MFA tidak tersedia untuk konteks user ini.');

            throw ValidationException::withMessages([
                'mfa' => 'MFA tidak tersedia untuk konteks user ini.',
            ]);
        }

        if ($this->mfaPolicy->isEnrolled($user)) {
            $this->recordBlockedEnrollment($request, $user, $realActiveUserPosition, 'mfa_already_enabled', 'MFA sudah aktif untuk user ini.');

            throw ValidationException::withMessages([
                'mfa' => 'MFA sudah aktif untuk user ini.',
            ]);
        }
    }

    private function recordBlockedEnrollment(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        string $failureCode,
        string $message
    ): void {
        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_CHALLENGE,
            'result' => LoginEvent::RESULT_BLOCKED,
            'failure_code' => $failureCode,
            'message' => $message,
            'auth_method' => 'mfa_setup',
            'mfa_method' => MfaPolicy::METHOD_TOTP,
            'mfa_result' => LoginEvent::RESULT_FAILED,
            'http_status' => 422,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
            ],
        ]);
    }
}
