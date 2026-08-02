<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\MfaSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegenerateMfaRecoveryCodes
{
    public function __construct(
        private MfaPolicy $mfaPolicy,
        private MfaSession $mfaSession,
        private RecordAuthenticationEvent $recordAuthenticationEvent
    ) {}

    /**
     * @return array{regenerated_at: string, recovery_codes: list<string>, recovery_code_count: int, previous_recovery_code_count: int}
     */
    public function handle(Request $request, User $user, UserPosition $realActiveUserPosition): array
    {
        $this->ensureRegenerationCanRun($request, $user, $realActiveUserPosition);

        $recoveryCodes = $this->generateRecoveryCodes();
        $recoveryCodeHashes = array_map(
            static fn (string $recoveryCode): string => Hash::make($recoveryCode),
            $recoveryCodes
        );
        $regeneratedAt = now();

        $previousRecoveryCodeCount = DB::transaction(function () use ($user, $recoveryCodeHashes, $regeneratedAt): int {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $previousRecoveryCodeCount = $this->recoveryCodeCount($lockedUser->mfa_recovery_codes);

            $lockedUser->forceFill([
                'mfa_recovery_codes' => $recoveryCodeHashes,
                'mfa_recovery_codes_generated_at' => $regeneratedAt,
            ])->save();

            $user->forceFill([
                'mfa_recovery_codes' => $recoveryCodeHashes,
                'mfa_recovery_codes_generated_at' => $regeneratedAt,
            ]);

            return $previousRecoveryCodeCount;
        }, attempts: 3);

        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_RECOVERY_CODES_REGENERATED,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Recovery codes MFA berhasil dibuat ulang.',
            'auth_method' => 'mfa_recovery_codes',
            'mfa_method' => MfaPolicy::METHOD_RECOVERY_CODE,
            'mfa_result' => LoginEvent::RESULT_SUCCESS,
            'http_status' => 302,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'previous_recovery_code_count' => $previousRecoveryCodeCount,
                'new_recovery_code_count' => count($recoveryCodes),
                'mfa_session' => $this->mfaSession->stateForAudit($request),
            ],
        ]);

        return [
            'regenerated_at' => $regeneratedAt->toISOString(),
            'recovery_codes' => $recoveryCodes,
            'recovery_code_count' => count($recoveryCodes),
            'previous_recovery_code_count' => $previousRecoveryCodeCount,
        ];
    }

    private function ensureRegenerationCanRun(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        if (! $this->mfaPolicy->supportsConfiguredMethod()) {
            $this->recordBlockedRegeneration($request, $user, $realActiveUserPosition, 'mfa_method_unsupported', 'Metode MFA tidak didukung.', 422);

            throw ValidationException::withMessages([
                'recovery_codes' => 'Metode MFA tidak didukung. Hubungi administrator.',
            ]);
        }

        if (! $this->mfaPolicy->availableFor($realActiveUserPosition)) {
            $this->recordBlockedRegeneration($request, $user, $realActiveUserPosition, 'mfa_not_available', 'MFA tidak tersedia untuk konteks user ini.', 422);

            throw ValidationException::withMessages([
                'recovery_codes' => 'MFA tidak tersedia untuk konteks user ini.',
            ]);
        }

        if (! $this->mfaPolicy->isEnrolled($user)) {
            $this->recordBlockedRegeneration($request, $user, $realActiveUserPosition, 'mfa_not_enrolled', 'MFA belum aktif untuk user ini.', 422);

            throw ValidationException::withMessages([
                'recovery_codes' => 'MFA belum aktif untuk user ini.',
            ]);
        }

        if ($this->mfaPolicy->hasPendingEnrollment($user)) {
            $this->recordBlockedRegeneration($request, $user, $realActiveUserPosition, 'mfa_pending_enrollment_exists', 'Setup MFA masih pending.', 422);

            throw ValidationException::withMessages([
                'recovery_codes' => 'Setup MFA masih pending. Selesaikan atau reset MFA terlebih dahulu.',
            ]);
        }

        if (! $this->mfaSession->isVerifiedFor($request, $user, $realActiveUserPosition, MfaPolicy::METHOD_TOTP)) {
            $this->recordBlockedRegeneration($request, $user, $realActiveUserPosition, 'mfa_totp_verification_required', 'Regenerate recovery codes wajib memakai session MFA TOTP valid.', 403);

            throw ValidationException::withMessages([
                'recovery_codes' => 'Verifikasi ulang dengan Google Authenticator sebelum membuat recovery codes baru.',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, $this->mfaPolicy->recoveryCodeCount()))
            ->map(fn (): string => $this->generateRecoveryCode())
            ->all();
    }

    private function generateRecoveryCode(): string
    {
        return strtoupper(implode('-', str_split(bin2hex(random_bytes(8)), 4)));
    }

    private function recoveryCodeCount(mixed $recoveryCodes): int
    {
        if (! is_array($recoveryCodes)) {
            return 0;
        }

        return count(array_filter(
            $recoveryCodes,
            static fn (mixed $recoveryCode): bool => is_string($recoveryCode) && $recoveryCode !== ''
        ));
    }

    private function recordBlockedRegeneration(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        string $failureCode,
        string $message,
        int $httpStatus
    ): void {
        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_RECOVERY_CODES_REGENERATED,
            'result' => LoginEvent::RESULT_BLOCKED,
            'failure_code' => $failureCode,
            'message' => $message,
            'auth_method' => 'mfa_recovery_codes',
            'mfa_method' => MfaPolicy::METHOD_RECOVERY_CODE,
            'mfa_result' => LoginEvent::RESULT_BLOCKED,
            'http_status' => $httpStatus,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'mfa_session' => $this->mfaSession->stateForAudit($request),
            ],
        ]);
    }
}
