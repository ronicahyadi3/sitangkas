<?php

namespace App\Actions\Auth;

use App\Http\Requests\Auth\StoreAuthenticatedSessionRequest;
use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\RememberMePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;
use Throwable;

class AuthenticateSession
{
    private const DECAY_SECONDS = 900;

    private const IP_MAX_ATTEMPTS = 30;

    private const LOCK_AFTER_FAILED_ATTEMPTS = 5;

    private const LOCK_MINUTES = 30;

    private const USER_MAX_ATTEMPTS = 5;

    public function __construct(
        private RecordAuthenticationEvent $recordAuthenticationEvent,
        private CurrentUserContext $currentUserContext,
        private RememberMePolicy $rememberMePolicy,
        private EnforceSingleDeviceAuthentication $singleDeviceAuthentication
    ) {}

    public function handle(StoreAuthenticatedSessionRequest $request): User
    {
        $this->ensureRateLimitIsNotExceeded($request);

        $captchaResult = $this->verifyCaptcha($request);

        if ($captchaResult['configured'] && ! $captchaResult['success']) {
            $this->incrementRateLimits($request);
            $this->recordAuthenticationEvent->handle($request, null, null, [
                'event_type' => LoginEvent::EVENT_LOGIN,
                'result' => LoginEvent::RESULT_BLOCKED,
                'failure_code' => 'captcha_failed',
                'message' => 'Verifikasi CAPTCHA gagal.',
                'http_status' => 422,
                'captcha_provider' => 'recaptcha_v3',
                'captcha_score' => $captchaResult['score'],
                'captcha_success' => false,
                'captcha_error_codes' => $captchaResult['error_codes'],
            ]);

            $this->throwLoginValidationException('Verifikasi CAPTCHA tidak berhasil. Silakan coba lagi.');
        }

        $user = User::query()
            ->whereLoginIdentifier($request->identifier())
            ->first();

        if (! $user instanceof User) {
            $this->incrementRateLimits($request);
            $this->recordAuthenticationEvent->handle($request, null, null, [
                'event_type' => LoginEvent::EVENT_LOGIN,
                'result' => LoginEvent::RESULT_FAILED,
                'failure_code' => 'user_not_found',
                'message' => 'Identifier login tidak ditemukan.',
                'http_status' => 422,
                'captcha_score' => $captchaResult['score'],
                'captcha_success' => $captchaResult['configured'] ? true : null,
            ]);

            $this->throwLoginValidationException();
        }

        $this->releaseExpiredLock($user);

        if ($failureCode = $this->blockedFailureCode($user)) {
            $this->incrementRateLimits($request);
            $this->recordAuthenticationEvent->handle($request, $user, null, [
                'event_type' => LoginEvent::EVENT_LOGIN,
                'result' => LoginEvent::RESULT_BLOCKED,
                'failure_code' => $failureCode,
                'message' => 'Login diblokir oleh kebijakan akun.',
                'http_status' => 403,
                'captcha_score' => $captchaResult['score'],
                'captcha_success' => $captchaResult['configured'] ? true : null,
            ]);

            $this->throwLoginValidationException('Proses login tidak dapat dilanjutkan. Silakan hubungi administrator.');
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            $this->incrementRateLimits($request);
            $this->recordFailedPasswordAttempt($request, $user, $captchaResult);

            $this->throwLoginValidationException();
        }

        $userPosition = $this->resolveActivePosition($user);

        if (! $userPosition instanceof UserPosition) {
            return $this->authenticateWithoutSelectablePosition($request, $user, $captchaResult);
        }

        $remember = $this->rememberMePolicy->shouldRemember($request->remember(), $userPosition);
        $singleDeviceState = $this->singleDeviceAuthentication->renew($request, $user, $remember);

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $this->singleDeviceAuthentication->markCurrentSession($request, $singleDeviceState['authenticated_at']);

        if ($remember && $singleDeviceState['remember_token_expires_at'] instanceof Carbon) {
            $this->singleDeviceAuthentication->markRememberedSession($request, $singleDeviceState['remember_token_expires_at']);
        } else {
            $this->singleDeviceAuthentication->clearRememberedSession($request);
            $this->singleDeviceAuthentication->forgetRememberCookie();
        }

        $this->currentUserContext->forgetAdminSuperActingContext($request);
        $request->session()->put(CurrentUserContext::ACTIVE_USER_POSITION_SESSION_KEY, $userPosition->getKey());
        $request->session()->put(CurrentUserContext::ACTIVE_YEAR_SESSION_KEY, $request->selectedYear());

        DB::transaction(function () use ($request, $user, $userPosition, $captchaResult, $remember, $singleDeviceState): void {
            $this->recordSuccessfulLogin($request, $user, $userPosition, $captchaResult, $remember, $singleDeviceState);
        }, attempts: 3);

        RateLimiter::clear($request->throttleKey());
        RateLimiter::clear($request->ipThrottleKey());

        return $user;
    }

    /**
     * @param  array{configured: bool, success: bool, score: float|null, error_codes: list<string>|null}  $captchaResult
     */
    private function authenticateWithoutSelectablePosition(
        StoreAuthenticatedSessionRequest $request,
        User $user,
        array $captchaResult
    ): User {
        $singleDeviceState = $this->singleDeviceAuthentication->renew($request, $user, remember: false);

        Auth::guard('web')->login($user, remember: false);
        $request->session()->regenerate();
        $this->singleDeviceAuthentication->markCurrentSession($request, $singleDeviceState['authenticated_at']);
        $this->singleDeviceAuthentication->clearRememberedSession($request);
        $this->singleDeviceAuthentication->forgetRememberCookie();

        $this->currentUserContext->forgetActivePosition($request);
        $request->session()->put(CurrentUserContext::ACTIVE_YEAR_SESSION_KEY, $request->selectedYear());

        DB::transaction(function () use ($request, $user, $captchaResult, $singleDeviceState): void {
            $this->recordSuccessfulLoginWithoutPosition($request, $user, $captchaResult, $singleDeviceState);
        }, attempts: 3);

        RateLimiter::clear($request->throttleKey());
        RateLimiter::clear($request->ipThrottleKey());

        return $user;
    }

    private function ensureRateLimitIsNotExceeded(StoreAuthenticatedSessionRequest $request): void
    {
        if (RateLimiter::tooManyAttempts($request->throttleKey(), self::USER_MAX_ATTEMPTS)) {
            $this->recordRateLimitedEvent($request, $request->throttleKey());
        }

        if (RateLimiter::tooManyAttempts($request->ipThrottleKey(), self::IP_MAX_ATTEMPTS)) {
            $this->recordRateLimitedEvent($request, $request->ipThrottleKey());
        }
    }

    /**
     * @return array{configured: bool, success: bool, score: float|null, error_codes: list<string>|null}
     */
    private function verifyCaptcha(StoreAuthenticatedSessionRequest $request): array
    {
        if (! $request->captchaIsConfigured()) {
            return [
                'configured' => false,
                'success' => true,
                'score' => null,
                'error_codes' => null,
            ];
        }

        try {
            $score = RecaptchaV3::verify(
                $request->captchaToken(),
                $request->captchaAction()
            );
        } catch (Throwable) {
            return [
                'configured' => true,
                'success' => false,
                'score' => null,
                'error_codes' => ['verification_exception'],
            ];
        }

        $success = $score !== false && (float) $score >= $request->captchaMinimumScore();

        return [
            'configured' => true,
            'success' => $success,
            'score' => $score === false ? null : (float) $score,
            'error_codes' => $score === false ? ['invalid_token_or_action'] : null,
        ];
    }

    private function recordRateLimitedEvent(StoreAuthenticatedSessionRequest $request, string $limiterKey): never
    {
        $seconds = RateLimiter::availableIn($limiterKey);

        $this->recordAuthenticationEvent->handle($request, null, null, [
            'event_type' => LoginEvent::EVENT_LOCKOUT,
            'result' => LoginEvent::RESULT_BLOCKED,
            'failure_code' => 'rate_limited',
            'message' => 'Percobaan login melewati batas rate limit.',
            'http_status' => 429,
            'metadata' => [
                'available_in_seconds' => $seconds,
            ],
        ]);

        $this->throwLoginValidationException(
            'Terlalu banyak percobaan login. Silakan coba lagi dalam '.$seconds.' detik.'
        );
    }

    private function incrementRateLimits(StoreAuthenticatedSessionRequest $request): void
    {
        RateLimiter::increment($request->throttleKey(), self::DECAY_SECONDS);
        RateLimiter::increment($request->ipThrottleKey(), self::DECAY_SECONDS);
    }

    private function releaseExpiredLock(User $user): void
    {
        if ($user->status !== User::STATUS_LOCKED || $user->locked_until === null || $user->locked_until->isFuture()) {
            return;
        }

        $updatedAttributes = [
            'status' => User::STATUS_ACTIVE,
            'status_changed_at' => now(),
            'status_reason' => 'Kunci akun otomatis telah kedaluwarsa.',
            'locked_at' => null,
            'locked_until' => null,
            'lock_reason' => null,
        ];

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update($updatedAttributes);

        $user->forceFill($updatedAttributes);
    }

    private function blockedFailureCode(User $user): ?string
    {
        if ($user->account_type === User::ACCOUNT_TYPE_SERVICE) {
            return 'service_account_web_login_denied';
        }

        if ($user->isLocked()) {
            return 'account_locked';
        }

        if ($user->status === User::STATUS_PENDING) {
            return 'account_pending';
        }

        if ($user->status === User::STATUS_INACTIVE) {
            return 'account_inactive';
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            return 'account_suspended';
        }

        if (! $user->isActive()) {
            return 'account_not_active';
        }

        return null;
    }

    /**
     * @param  array{configured: bool, success: bool, score: float|null, error_codes: list<string>|null}  $captchaResult
     */
    private function recordFailedPasswordAttempt(StoreAuthenticatedSessionRequest $request, User $user, array $captchaResult): void
    {
        DB::transaction(function () use ($request, $user, $captchaResult): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $failedCount = ((int) $lockedUser->consecutive_failed_login_count) + 1;
            $lockedUntil = $failedCount >= self::LOCK_AFTER_FAILED_ATTEMPTS
                ? now()->addMinutes(self::LOCK_MINUTES)
                : null;

            $updatedAttributes = [
                'consecutive_failed_login_count' => $failedCount,
                'last_failed_login_at' => now(),
            ];

            if ($lockedUntil !== null) {
                $updatedAttributes = [
                    ...$updatedAttributes,
                    'status' => User::STATUS_LOCKED,
                    'status_changed_at' => now(),
                    'status_reason' => 'Akun terkunci otomatis karena terlalu banyak percobaan login gagal.',
                    'locked_at' => now(),
                    'locked_until' => $lockedUntil,
                    'lock_reason' => 'too_many_failed_attempts',
                ];
            }

            DB::table($user->getTable())
                ->where($user->getKeyName(), $user->getKey())
                ->update($updatedAttributes);

            $user->forceFill($updatedAttributes);

            $this->recordAuthenticationEvent->handle($request, $user, null, [
                'event_type' => LoginEvent::EVENT_LOGIN,
                'result' => LoginEvent::RESULT_FAILED,
                'failure_code' => 'invalid_credentials',
                'message' => 'Password tidak sesuai.',
                'http_status' => 422,
                'captcha_score' => $captchaResult['score'],
                'captcha_success' => $captchaResult['configured'] ? true : null,
                'metadata' => [
                    'account_locked' => $lockedUntil !== null,
                    'consecutive_failed_login_count' => $failedCount,
                ],
            ]);

            if ($lockedUntil !== null) {
                $this->recordAuthenticationEvent->handle($request, $user, null, [
                    'event_type' => LoginEvent::EVENT_LOCKOUT,
                    'result' => LoginEvent::RESULT_BLOCKED,
                    'failure_code' => 'too_many_failed_attempts',
                    'message' => 'Akun terkunci otomatis karena terlalu banyak percobaan login gagal.',
                    'http_status' => 423,
                    'captcha_score' => $captchaResult['score'],
                    'captcha_success' => $captchaResult['configured'] ? true : null,
                    'metadata' => [
                        'locked_until' => $lockedUntil->toISOString(),
                        'lock_minutes' => self::LOCK_MINUTES,
                    ],
                ]);
            }
        }, attempts: 3);
    }

    private function resolveActivePosition(User $user): ?UserPosition
    {
        return UserPosition::query()
            ->with(['jabatan', 'instansi', 'unitKerja'])
            ->forUser($user)
            ->availableForSelection()
            ->withActiveReferences()
            ->preferredFirst()
            ->first();
    }

    /**
     * @param  array{configured: bool, success: bool, score: float|null, error_codes: list<string>|null}  $captchaResult
     * @param  array{authenticated_at: mixed, revoked_session_count: int, remember_token_rotated: bool, remember_token_expires_at: mixed}  $singleDeviceState
     */
    private function recordSuccessfulLogin(
        StoreAuthenticatedSessionRequest $request,
        User $user,
        UserPosition $userPosition,
        array $captchaResult,
        bool $remember,
        array $singleDeviceState
    ): void {
        $updatedAttributes = [
            'last_login_at' => now(),
            'last_failed_login_at' => null,
            'consecutive_failed_login_count' => 0,
            'tahun_aktif' => $request->selectedYear(),
        ];

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update($updatedAttributes);

        $user->forceFill($updatedAttributes);
        $userPosition->markAsUsed();

        $this->recordAuthenticationEvent->handle($request, $user, $userPosition, [
            'event_type' => LoginEvent::EVENT_LOGIN,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Login berhasil.',
            'http_status' => 302,
            'remember_me' => $remember,
            'captcha_score' => $captchaResult['score'],
            'captcha_success' => $captchaResult['configured'] ? true : null,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'remember_me_requested' => $request->remember(),
                'remember_me_allowed' => $this->rememberMePolicy->allows($userPosition),
                'single_device_enforced' => $this->rememberMePolicy->singleDeviceEnabled(),
                'remember_me_duration_minutes' => $remember ? $this->rememberMePolicy->durationMinutes() : null,
                'remember_token_expires_at' => $singleDeviceState['remember_token_expires_at'] instanceof \DateTimeInterface
                    ? $singleDeviceState['remember_token_expires_at']->format(DATE_ATOM)
                    : null,
                'revoked_session_count' => $singleDeviceState['revoked_session_count'],
                'remember_token_rotated' => $singleDeviceState['remember_token_rotated'],
            ],
        ]);
    }

    /**
     * @param  array{configured: bool, success: bool, score: float|null, error_codes: list<string>|null}  $captchaResult
     * @param  array{authenticated_at: mixed, revoked_session_count: int, remember_token_rotated: bool, remember_token_expires_at: mixed}  $singleDeviceState
     */
    private function recordSuccessfulLoginWithoutPosition(
        StoreAuthenticatedSessionRequest $request,
        User $user,
        array $captchaResult,
        array $singleDeviceState
    ): void {
        $updatedAttributes = [
            'last_login_at' => now(),
            'last_failed_login_at' => null,
            'consecutive_failed_login_count' => 0,
            'tahun_aktif' => $request->selectedYear(),
        ];

        DB::table($user->getTable())
            ->where($user->getKeyName(), $user->getKey())
            ->update($updatedAttributes);

        $user->forceFill($updatedAttributes);

        $this->recordAuthenticationEvent->handle($request, $user, null, [
            'event_type' => LoginEvent::EVENT_LOGIN,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Login berhasil, tetapi akun belum memiliki posisi aktif.',
            'http_status' => 302,
            'remember_me' => false,
            'captcha_score' => $captchaResult['score'],
            'captcha_success' => $captchaResult['configured'] ? true : null,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'requires_position_setup' => true,
                'remember_me_requested' => $request->remember(),
                'remember_me_allowed' => false,
                'single_device_enforced' => $this->rememberMePolicy->singleDeviceEnabled(),
                'remember_me_duration_minutes' => null,
                'remember_token_expires_at' => $singleDeviceState['remember_token_expires_at'] instanceof \DateTimeInterface
                    ? $singleDeviceState['remember_token_expires_at']->format(DATE_ATOM)
                    : null,
                'revoked_session_count' => $singleDeviceState['revoked_session_count'],
                'remember_token_rotated' => $singleDeviceState['remember_token_rotated'],
            ],
        ]);
    }

    private function throwLoginValidationException(string $message = 'NIK/email atau password tidak sesuai.'): never
    {
        throw ValidationException::withMessages([
            'nik' => $message,
        ]);
    }
}
