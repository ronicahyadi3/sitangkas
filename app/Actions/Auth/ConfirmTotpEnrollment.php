<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\MfaSession;
use App\Services\Auth\TotpAuthenticator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ConfirmTotpEnrollment
{
    private const DECAY_SECONDS = 900;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private MfaPolicy $mfaPolicy,
        private MfaSession $mfaSession,
        private TotpAuthenticator $totpAuthenticator,
        private RecordAuthenticationEvent $recordAuthenticationEvent
    ) {}

    /**
     * @return array{confirmed_at: string, recovery_codes: list<string>, mfa_session: array{user_id: string, real_user_position_id: string, method: string, verified_at: string, expires_at: string}}
     */
    public function handle(Request $request, User $user, UserPosition $realActiveUserPosition, string $oneTimePassword): array
    {
        $this->ensureTotpConfirmationCanRun($request, $user, $realActiveUserPosition);
        $this->ensureRateLimitIsNotExceeded($request, $user, $realActiveUserPosition);

        $pendingSecret = $user->mfa_pending_secret;

        if (! is_string($pendingSecret) || $pendingSecret === '') {
            $this->recordFailedConfirmation($request, $user, $realActiveUserPosition, 'mfa_setup_not_started', 'Setup MFA belum dimulai.');

            throw ValidationException::withMessages([
                'one_time_password' => 'Setup MFA belum dimulai. Muat ulang halaman setup MFA.',
            ]);
        }

        if (! $this->totpAuthenticator->verify($pendingSecret, $oneTimePassword)) {
            RateLimiter::hit($this->throttleKey($request, $user, $realActiveUserPosition), self::DECAY_SECONDS);

            $this->recordFailedConfirmation($request, $user, $realActiveUserPosition, 'mfa_failed', 'Kode MFA tidak valid.');

            throw ValidationException::withMessages([
                'one_time_password' => 'Kode MFA tidak valid.',
            ]);
        }

        $confirmedAt = now();
        $recoveryCodes = $this->generateRecoveryCodes();

        DB::transaction(function () use ($user, $pendingSecret, $confirmedAt, $recoveryCodes): void {
            $user->forceFill([
                'mfa_secret' => $pendingSecret,
                'mfa_enabled_at' => $confirmedAt,
                'mfa_confirmed_at' => $confirmedAt,
                'mfa_last_used_at' => $confirmedAt,
                'mfa_recovery_codes' => array_map(
                    static fn (string $recoveryCode): string => Hash::make($recoveryCode),
                    $recoveryCodes
                ),
                'mfa_recovery_codes_generated_at' => $confirmedAt,
                'mfa_pending_secret' => null,
                'mfa_pending_secret_created_at' => null,
            ])->save();
        }, attempts: 3);

        RateLimiter::clear($this->throttleKey($request, $user, $realActiveUserPosition));

        $mfaSession = $this->mfaSession->markVerified(
            $request,
            $user,
            $realActiveUserPosition,
            MfaPolicy::METHOD_TOTP,
            $confirmedAt
        );

        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_VERIFIED,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Setup MFA TOTP berhasil dikonfirmasi.',
            'auth_method' => 'mfa_setup',
            'mfa_method' => MfaPolicy::METHOD_TOTP,
            'mfa_result' => LoginEvent::RESULT_SUCCESS,
            'http_status' => 302,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'recovery_code_count' => count($recoveryCodes),
                'mfa_session' => $mfaSession,
            ],
        ]);

        return [
            'confirmed_at' => $confirmedAt->toISOString(),
            'recovery_codes' => $recoveryCodes,
            'mfa_session' => $mfaSession,
        ];
    }

    private function ensureTotpConfirmationCanRun(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        if (! $this->mfaPolicy->supportsConfiguredMethod()) {
            $this->recordBlockedConfirmation($request, $user, $realActiveUserPosition, 'mfa_method_unsupported', 'Metode MFA tidak didukung.', 422);

            throw ValidationException::withMessages([
                'one_time_password' => 'Metode MFA tidak didukung. Hubungi administrator.',
            ]);
        }

        if (! $this->mfaPolicy->availableFor($realActiveUserPosition)) {
            $this->recordBlockedConfirmation($request, $user, $realActiveUserPosition, 'mfa_not_available', 'MFA tidak tersedia untuk konteks user ini.', 422);

            throw ValidationException::withMessages([
                'one_time_password' => 'MFA tidak tersedia untuk konteks user ini.',
            ]);
        }

        if ($this->mfaPolicy->isEnrolled($user)) {
            $this->recordBlockedConfirmation($request, $user, $realActiveUserPosition, 'mfa_already_enabled', 'MFA sudah aktif untuk user ini.', 422);

            throw ValidationException::withMessages([
                'one_time_password' => 'MFA sudah aktif untuk user ini.',
            ]);
        }
    }

    private function ensureRateLimitIsNotExceeded(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $user, $realActiveUserPosition), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $user, $realActiveUserPosition));

        $this->recordBlockedConfirmation($request, $user, $realActiveUserPosition, 'rate_limited', 'Terlalu banyak percobaan MFA.', 429);

        throw ValidationException::withMessages([
            'one_time_password' => "Terlalu banyak percobaan MFA. Coba lagi dalam {$seconds} detik.",
        ]);
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

    private function throttleKey(Request $request, User $user, UserPosition $realActiveUserPosition): string
    {
        return implode('|', [
            'mfa-confirm',
            $user->getKey(),
            $realActiveUserPosition->getKey(),
            $request->ip() ?: 'unknown',
        ]);
    }

    private function recordBlockedConfirmation(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        string $failureCode,
        string $message,
        int $httpStatus
    ): void {
        $this->recordMfaVerificationEvent($request, $user, $realActiveUserPosition, LoginEvent::RESULT_BLOCKED, $failureCode, $message, $httpStatus);
    }

    private function recordFailedConfirmation(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        string $failureCode,
        string $message
    ): void {
        $this->recordMfaVerificationEvent($request, $user, $realActiveUserPosition, LoginEvent::RESULT_FAILED, $failureCode, $message, 422);
    }

    private function recordMfaVerificationEvent(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        string $result,
        string $failureCode,
        string $message,
        int $httpStatus
    ): void {
        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_VERIFIED,
            'result' => $result,
            'failure_code' => $failureCode,
            'message' => $message,
            'auth_method' => 'mfa_setup',
            'mfa_method' => MfaPolicy::METHOD_TOTP,
            'mfa_result' => LoginEvent::RESULT_FAILED,
            'http_status' => $httpStatus,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'remaining_attempts' => RateLimiter::remaining($this->throttleKey($request, $user, $realActiveUserPosition), self::MAX_ATTEMPTS),
            ],
        ]);
    }
}
