<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\StartMfaChallenge;
use App\Actions\Auth\VerifyRecoveryCodeChallenge;
use App\Actions\Auth\VerifyTotpChallenge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyMfaChallengeRequest;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\MfaSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class MfaChallengeController extends Controller
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private MfaPolicy $mfaPolicy,
        private MfaSession $mfaSession
    ) {}

    public function create(Request $request, StartMfaChallenge $startMfaChallenge): View|RedirectResponse
    {
        $context = $this->authenticatedContext($request);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$user, $realActiveUserPosition] = $context;

        if ($this->mfaSession->shouldSetup($request, $user, $realActiveUserPosition)) {
            return redirect()->route('login.mfa.setup')
                ->with('status', 'Aktifkan MFA sebelum melanjutkan.');
        }

        if (! $this->mfaSession->shouldChallenge($request, $user, $realActiveUserPosition)) {
            return redirect()->to($this->verifiedDestinationUrl($request, $realActiveUserPosition));
        }

        $startMfaChallenge->handle($request, $user, $realActiveUserPosition);

        return view('auth.mfa-challenge', $this->challengeViewData($request, $user, $realActiveUserPosition));
    }

    public function store(
        VerifyMfaChallengeRequest $request,
        VerifyTotpChallenge $verifyTotpChallenge,
        VerifyRecoveryCodeChallenge $verifyRecoveryCodeChallenge
    ): RedirectResponse {
        $user = $request->user();
        $realActiveUserPosition = $request->realActiveUserPosition();

        abort_unless($user instanceof User, 403);

        if ($request->challengeMethod() === MfaPolicy::METHOD_RECOVERY_CODE) {
            $verifyRecoveryCodeChallenge->handle(
                $request,
                $user,
                $realActiveUserPosition,
                $request->recoveryCode()
            );
        } else {
            $verifyTotpChallenge->handle(
                $request,
                $user,
                $realActiveUserPosition,
                $request->oneTimePassword()
            );
        }

        return redirect()
            ->to($this->verifiedDestinationUrl($request, $realActiveUserPosition))
            ->with('status', 'Verifikasi MFA berhasil.');
    }

    /**
     * @return array{0: User, 1: UserPosition}|RedirectResponse
     */
    private function authenticatedContext(Request $request): array|RedirectResponse
    {
        $user = $this->currentUserContext->user($request);

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $this->currentUserContext->hasSessionContext($request)) {
            return redirect()
                ->route('positions.index')
                ->with('status', 'Silakan pilih konteks kerja sebelum verifikasi MFA.');
        }

        $realActiveUserPosition = $this->currentUserContext->realActivePosition($request);

        if (! $realActiveUserPosition instanceof UserPosition) {
            $this->currentUserContext->forgetActivePosition($request);

            return redirect()
                ->route('positions.index')
                ->with('status', 'Konteks kerja sudah tidak aktif. Silakan pilih kembali sebelum verifikasi MFA.');
        }

        return [$user, $realActiveUserPosition];
    }

    /**
     * @return array<string, mixed>
     */
    private function challengeViewData(Request $request, User $user, UserPosition $realActiveUserPosition): array
    {
        $realActiveUserPosition->loadMissing(['jabatan', 'instansi', 'unitKerja']);

        return [
            'activeYear' => $this->currentUserContext->activeYear($request),
            'isAdminSuperPosition' => $this->currentUserContext->isAdminSuperPosition($realActiveUserPosition),
            'nextUrl' => $this->nextUrlAfterVerified($realActiveUserPosition),
            'positionContext' => $realActiveUserPosition->toAuthenticationContext(),
            'realActiveUserPosition' => $realActiveUserPosition,
            'totpDigits' => $this->mfaPolicy->totpDigits(),
            'totpPeriodSeconds' => $this->mfaPolicy->totpPeriodSeconds(),
            'verifiedExpiresAt' => $this->mfaPolicy->verifiedExpiresAt($realActiveUserPosition),
            'user' => $user,
        ];
    }

    private function nextUrlAfterVerified(UserPosition $realActiveUserPosition): string
    {
        if ($this->currentUserContext->isAdminSuperPosition($realActiveUserPosition) && Route::has('login.post')) {
            return route('login.post');
        }

        return Route::has('dashboard') ? route('dashboard') : route('landing');
    }

    private function verifiedDestinationUrl(Request $request, UserPosition $realActiveUserPosition): string
    {
        $defaultUrl = $this->nextUrlAfterVerified($realActiveUserPosition);
        $intendedUrl = $request->session()->pull('url.intended');

        if (! is_string($intendedUrl) || $intendedUrl === '') {
            return $defaultUrl;
        }

        if (! $this->isAllowedVerifiedDestination($request, $intendedUrl)) {
            return $defaultUrl;
        }

        return $intendedUrl;
    }

    private function isAllowedVerifiedDestination(Request $request, string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && $host !== '' && $host !== $request->getHost()) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        $path = trim($path, '/');

        if ($path === '') {
            return true;
        }

        $authenticationPaths = [
            'login',
            'login/context',
            'login/mfa',
            'login/mfa/setup',
            'login/no-active-position',
            'login/post',
            'positions',
        ];

        if (in_array($path, $authenticationPaths, true)) {
            return false;
        }

        return ! str_starts_with($path, 'login/post/options/');
    }
}
