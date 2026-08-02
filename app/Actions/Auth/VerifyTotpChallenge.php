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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class VerifyTotpChallenge
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
     * @return array{verified_at: string, mfa_session: array{user_id: string, real_user_position_id: string, method: string, verified_at: string, expires_at: string}}
     */
    public function handle(Request $request, User $user, UserPosition $realActiveUserPosition, string $oneTimePassword): array
    {
        $this->ensureTotpChallengeCanRun($request, $user, $realActiveUserPosition);
        $this->ensureRateLimitIsNotExceeded($request, $user, $realActiveUserPosition);

        $secret = $user->mfa_secret;

        if (! is_string($secret) || $secret === '') {
            $this->recordFailedChallenge($request, $user, $realActiveUserPosition, 'mfa_not_enrolled', 'MFA belum aktif untuk user ini.');

            throw ValidationException::withMessages([
                'one_time_password' => 'MFA belum aktif untuk user ini.',
            ]);
        }

        if (! $this->totpAuthenticator->verify($secret, $oneTimePassword)) {
            RateLimiter::hit($this->throttleKey($request, $user, $realActiveUserPosition), self::DECAY_SECONDS);

            $this->recordFailedChallenge($request, $user, $realActiveUserPosition, 'mfa_failed', 'Kode MFA tidak valid.');

            throw ValidationException::withMessages([
                'one_time_password' => 'Kode MFA tidak valid.',
            ]);
        }

        $verifiedAt = now();

        DB::transaction(function () use ($user, $verifiedAt): void {
            $user->forceFill([
                'mfa_last_used_at' => $verifiedAt,
            ])->save();
        }, attempts: 3);

        RateLimiter::clear($this->throttleKey($request, $user, $realActiveUserPosition));

        $mfaSession = $this->mfaSession->markVerified(
            $request,
            $user,
            $realActiveUserPosition,
            MfaPolicy::METHOD_TOTP,
            $verifiedAt
        );

        $this->recordAuthenticationEvent->handle($request, $user, $realActiveUserPosition, [
            'event_type' => LoginEvent::EVENT_MFA_VERIFIED,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Verifikasi MFA TOTP berhasil.',
            'auth_method' => 'mfa_challenge',
            'mfa_method' => MfaPolicy::METHOD_TOTP,
            'mfa_result' => LoginEvent::RESULT_SUCCESS,
            'http_status' => 302,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'real_user_position_id' => $realActiveUserPosition->getKey(),
                'mfa_session' => $mfaSession,
            ],
        ]);

        return [
            'verified_at' => $verifiedAt->toISOString(),
            'mfa_session' => $mfaSession,
        ];
    }

    private function ensureTotpChallengeCanRun(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        if (! $this->mfaPolicy->supportsConfiguredMethod()) {
            $this->recordBlockedChallenge($request, $user, $realActiveUserPosition, 'mfa_method_unsupported', 'Metode MFA tidak didukung.', 422);

            throw ValidationException::withMessages([
                'one_time_password' => 'Metode MFA tidak didukung. Hubungi administrator.',
            ]);
        }

        if (! $this->mfaPolicy->requiredFor($realActiveUserPosition, $user)) {
            $this->recordBlockedChallenge($request, $user, $realActiveUserPosition, 'mfa_not_required', 'MFA tidak wajib untuk konteks user ini.', 422);

            throw ValidationException::withMessages([
                'one_time_password' => 'MFA tidak wajib untuk konteks user ini.',
            ]);
        }

        if (! $this->mfaPolicy->isEnrolled($user)) {
            $this->recordBlockedChallenge($request, $user, $realActiveUserPosition, 'mfa_not_enrolled', 'MFA belum aktif untuk user ini.', 422);

            throw ValidationException::withMessages([
                'one_time_password' => 'MFA belum aktif untuk user ini.',
            ]);
        }
    }

    private function ensureRateLimitIsNotExceeded(Request $request, User $user, UserPosition $realActiveUserPosition): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $user, $realActiveUserPosition), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $user, $realActiveUserPosition));

        $this->recordBlockedChallenge($request, $user, $realActiveUserPosition, 'rate_limited', 'Terlalu banyak percobaan MFA.', 429);

        throw ValidationException::withMessages([
            'one_time_password' => "Terlalu banyak percobaan MFA. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    private function throttleKey(Request $request, User $user, UserPosition $realActiveUserPosition): string
    {
        return implode('|', [
            'mfa-challenge',
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
        $this->recordMfaVerificationEvent($request, $user, $realActiveUserPosition, LoginEvent::RESULT_BLOCKED, $failureCode, $message, $httpStatus);
    }

    private function recordFailedChallenge(
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
            'auth_method' => 'mfa_challenge',
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
