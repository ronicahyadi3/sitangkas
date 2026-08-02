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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class VerifyRecoveryCodeChallenge
{
    private const DECAY_SECONDS = 900;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private MfaPolicy $mfaPolicy,
        private MfaSession $mfaSession,
        private RecordAuthenticationEvent $recordAuthenticationEvent
    ) {}

    /**
     * @return array{verified_at: string, mfa_session: array{user_id: string, real_user_position_id: string, method: string, verified_at: string, expires_at: string}, remaining_recovery_code_count: int}
     */
    public function handle(Request $request, User $user, UserPosition $realActiveUserPosition, string $recoveryCode): array
    {
        $this->ensureRecoveryCodeChallengeCanRun($request, $user, $realActiveUserPosition);
        $this->ensureRateLimitIsNotExceeded($request, $user, $realActiveUserPosition);

        $verification = $this->consumeRecoveryCode($user, $this->normalizeRecoveryCode($recoveryCode));

        if (! $verification['matched']) {
            RateLimiter::hit($this->throttleKey($request, $user, $realActiveUserPosition), self::DECAY_SECONDS);

            $this->recordFailedChallenge(
                $request,
                $user,
                $realActiveUserPosition,
                $verification['failure_code'],
                $verification['message'],
                $verification['remaining_recovery_code_count']
            );

            throw ValidationException::withMessages([
                'recovery_code' => $verification['message'],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request, $user, $realActiveUserPosition));

        $verifiedAt = $verification['verified_at'];

        if (! $verifiedAt instanceof Carbon) {
            throw new LogicException('Recovery code verification succeeded without a verified timestamp.');
        }

        $mfaSession = $this->mfaSession->markVerified(
            $request,
            $user,
            $realActiveUserPosition,
            MfaPolicy::METHOD_RECOVERY_CODE,
            $verifiedAt
        );

        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_VERIFIED,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Verifikasi MFA recovery code berhasil.',
            'auth_method' => 'mfa_challenge',
            'mfa_method' => MfaPolicy::METHOD_RECOVERY_CODE,
            'mfa_result' => LoginEvent::RESULT_SUCCESS,
            'http_status' => 302,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'remaining_recovery_code_count' => $verification['remaining_recovery_code_count'],
                'mfa_session' => $mfaSession,
            ],
        ]);

        return [
            'verified_at' => $verifiedAt->toISOString(),
            'mfa_session' => $mfaSession,
            'remaining_recovery_code_count' => $verification['remaining_recovery_code_count'],
        ];
    }

    private function ensureRecoveryCodeChallengeCanRun(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        if (! $this->mfaPolicy->supportsConfiguredMethod()) {
            $this->recordBlockedChallenge($request, $user, $realActiveUserPosition, 'mfa_method_unsupported', 'Metode MFA tidak didukung.', 422);

            throw ValidationException::withMessages([
                'recovery_code' => 'Metode MFA tidak didukung. Hubungi administrator.',
            ]);
        }

        if (! $this->mfaPolicy->requiredFor($realActiveUserPosition, $user)) {
            $this->recordBlockedChallenge($request, $user, $realActiveUserPosition, 'mfa_not_required', 'MFA tidak wajib untuk konteks user ini.', 422);

            throw ValidationException::withMessages([
                'recovery_code' => 'MFA tidak wajib untuk konteks user ini.',
            ]);
        }

        if (! $this->mfaPolicy->isEnrolled($user)) {
            $this->recordBlockedChallenge($request, $user, $realActiveUserPosition, 'mfa_not_enrolled', 'MFA belum aktif untuk user ini.', 422);

            throw ValidationException::withMessages([
                'recovery_code' => 'MFA belum aktif untuk user ini.',
            ]);
        }
    }

    private function ensureRateLimitIsNotExceeded(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $user, $realActiveUserPosition), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $user, $realActiveUserPosition));

        $this->recordBlockedChallenge($request, $user, $realActiveUserPosition, 'rate_limited', 'Terlalu banyak percobaan recovery code MFA.', 429);

        throw ValidationException::withMessages([
            'recovery_code' => "Terlalu banyak percobaan recovery code MFA. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    /**
     * @return array{matched: bool, failure_code: string, message: string, remaining_recovery_code_count: int, verified_at: \Illuminate\Support\Carbon|null}
     */
    private function consumeRecoveryCode(User $user, string $normalizedRecoveryCode): array
    {
        return DB::transaction(function () use ($user, $normalizedRecoveryCode): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedUser instanceof User) {
                return [
                    'matched' => false,
                    'failure_code' => 'user_not_found',
                    'message' => 'User tidak ditemukan.',
                    'remaining_recovery_code_count' => 0,
                    'verified_at' => null,
                ];
            }

            $recoveryCodeHashes = $this->recoveryCodeHashes($lockedUser->mfa_recovery_codes);

            if ($recoveryCodeHashes === []) {
                return [
                    'matched' => false,
                    'failure_code' => 'mfa_recovery_codes_unavailable',
                    'message' => 'Recovery code tidak tersedia. Hubungi administrator untuk reset MFA.',
                    'remaining_recovery_code_count' => 0,
                    'verified_at' => null,
                ];
            }

            foreach ($recoveryCodeHashes as $index => $recoveryCodeHash) {
                if (! Hash::check($normalizedRecoveryCode, $recoveryCodeHash)) {
                    continue;
                }

                unset($recoveryCodeHashes[$index]);

                $remainingRecoveryCodeHashes = array_values($recoveryCodeHashes);
                $verifiedAt = now();

                $lockedUser->forceFill([
                    'mfa_last_used_at' => $verifiedAt,
                    'mfa_recovery_codes' => $remainingRecoveryCodeHashes,
                ])->save();

                $user->forceFill([
                    'mfa_last_used_at' => $verifiedAt,
                    'mfa_recovery_codes' => $remainingRecoveryCodeHashes,
                ]);

                return [
                    'matched' => true,
                    'failure_code' => '',
                    'message' => 'Recovery code berhasil diverifikasi.',
                    'remaining_recovery_code_count' => count($remainingRecoveryCodeHashes),
                    'verified_at' => $verifiedAt,
                ];
            }

            return [
                'matched' => false,
                'failure_code' => 'invalid_recovery_code',
                'message' => 'Recovery code tidak valid atau sudah pernah digunakan.',
                'remaining_recovery_code_count' => count($recoveryCodeHashes),
                'verified_at' => null,
            ];
        }, attempts: 3);
    }

    /**
     * @return list<string>
     */
    private function recoveryCodeHashes(mixed $recoveryCodes): array
    {
        if (! is_array($recoveryCodes)) {
            return [];
        }

        return array_values(array_filter(
            $recoveryCodes,
            static fn (mixed $recoveryCode): bool => is_string($recoveryCode) && $recoveryCode !== ''
        ));
    }

    private function normalizeRecoveryCode(string $recoveryCode): string
    {
        $recoveryCodeCharacters = Str::of($recoveryCode)
            ->upper()
            ->replaceMatches('/[^A-F0-9]+/', '')
            ->toString();

        if (Str::length($recoveryCodeCharacters) === 16) {
            return implode('-', str_split($recoveryCodeCharacters, 4));
        }

        return Str::of($recoveryCode)
            ->upper()
            ->replaceMatches('/\s+/', '')
            ->toString();
    }

    private function throttleKey(Request $request, User $user, UserPosition $realActiveUserPosition): string
    {
        return implode('|', [
            'mfa-recovery-challenge',
            $user->getKey(),
            $realActiveUserPosition->getKey(),
            $request->ip() ?: 'unknown',
        ]);
    }

    private function recordBlockedChallenge(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        string $failureCode,
        string $message,
        int $httpStatus
    ): void {
        $this->recordMfaVerificationEvent($request, $user, $realActiveUserPosition, LoginEvent::RESULT_BLOCKED, $failureCode, $message, $httpStatus, null);
    }

    private function recordFailedChallenge(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        string $failureCode,
        string $message,
        int $remainingRecoveryCodeCount
    ): void {
        $this->recordMfaVerificationEvent($request, $user, $realActiveUserPosition, LoginEvent::RESULT_FAILED, $failureCode, $message, 422, $remainingRecoveryCodeCount);
    }

    private function recordMfaVerificationEvent(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        string $result,
        string $failureCode,
        string $message,
        int $httpStatus,
        ?int $remainingRecoveryCodeCount
    ): void {
        $metadata = [
            'real_user_position_id' => $realActiveUserPosition->getKey(),
            'remaining_attempts' => RateLimiter::remaining($this->throttleKey($request, $user, $realActiveUserPosition), self::MAX_ATTEMPTS),
        ];

        if ($remainingRecoveryCodeCount !== null) {
            $metadata['remaining_recovery_code_count'] = $remainingRecoveryCodeCount;
        }

        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_VERIFIED,
            'result' => $result,
            'failure_code' => $failureCode,
            'message' => $message,
            'auth_method' => 'mfa_challenge',
            'mfa_method' => MfaPolicy::METHOD_RECOVERY_CODE,
            'mfa_result' => LoginEvent::RESULT_FAILED,
            'http_status' => $httpStatus,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => $metadata,
        ]);
    }
}
