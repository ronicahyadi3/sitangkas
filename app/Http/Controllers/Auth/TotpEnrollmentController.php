<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConfirmTotpEnrollment;
use App\Actions\Auth\StartTotpEnrollment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmTotpEnrollmentRequest;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\MfaSession;
use App\Services\Auth\SensitiveAuthenticationResponseHeaders;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class TotpEnrollmentController extends Controller
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private MfaPolicy $mfaPolicy,
        private MfaSession $mfaSession,
        private SensitiveAuthenticationResponseHeaders $sensitiveResponseHeaders
    ) {}

    public function create(Request $request, StartTotpEnrollment $startTotpEnrollment): View|RedirectResponse
    {
        $context = $this->authenticatedContext($request);

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        [$user, $realActiveUserPosition] = $context;

        if ($this->mfaPolicy->isEnrolled($user)) {
            return redirect($this->nextUrlAfterVerified($realActiveUserPosition))
                ->with('status', 'MFA sudah aktif untuk akun ini.');
        }

        if ($this->mfaSession->isVerifiedFor($request, $user, $realActiveUserPosition)) {
            return redirect($this->nextUrlAfterVerified($realActiveUserPosition));
        }

        $enrollment = $startTotpEnrollment->handle($request, $user, $realActiveUserPosition);

        return view('auth.mfa-setup', $this->setupViewData(
            $request,
            $user,
            $realActiveUserPosition,
            $enrollment
        ));
    }

    public function store(
        ConfirmTotpEnrollmentRequest $request,
        ConfirmTotpEnrollment $confirmTotpEnrollment
    ): Response {
        $user = $request->user();
        $realActiveUserPosition = $request->realActiveUserPosition();

        abort_unless($user instanceof User, 403);

        $result = $confirmTotpEnrollment->handle(
            $request,
            $user,
            $realActiveUserPosition,
            $request->oneTimePassword()
        );

        return $this->sensitiveResponseHeaders->noStoreView('auth.mfa-setup', $this->setupViewData(
            $request,
            $user,
            $realActiveUserPosition,
            setupComplete: true,
            recoveryCodes: $result['recovery_codes'],
            confirmedAt: $result['confirmed_at']
        ));
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
                ->route('login.context')
                ->with('status', 'Silakan pilih konteks kerja sebelum setup MFA.');
        }

        $realActiveUserPosition = $this->currentUserContext->realActivePosition($request);

        if (! $realActiveUserPosition instanceof UserPosition) {
            $this->currentUserContext->forgetActivePosition($request);

            return redirect()
                ->route('login.context')
                ->with('status', 'Konteks kerja sudah tidak aktif. Silakan pilih kembali sebelum setup MFA.');
        }

        return [$user, $realActiveUserPosition];
    }

    /**
     * @param  array{manual_entry_key: string, provisioning_uri: string, inline_qr_code: string|null, pending_secret_created_at: string}|null  $enrollment
     * @param  list<string>  $recoveryCodes
     * @return array<string, mixed>
     */
    private function setupViewData(
        Request $request,
        User $user,
        UserPosition $realActiveUserPosition,
        ?array $enrollment = null,
        bool $setupComplete = false,
        array $recoveryCodes = [],
        ?string $confirmedAt = null
    ): array {
        $realActiveUserPosition->loadMissing(['jabatan', 'instansi', 'unitKerja']);

        return [
            'activeYear' => $this->currentUserContext->activeYear($request),
            'confirmedAt' => $confirmedAt,
            'enrollment' => $enrollment,
            'isAdminSuperPosition' => $this->currentUserContext->isAdminSuperPosition($realActiveUserPosition),
            'nextUrl' => $this->nextUrlAfterVerified($realActiveUserPosition),
            'positionContext' => $realActiveUserPosition->toAuthenticationContext(),
            'realActiveUserPosition' => $realActiveUserPosition,
            'recoveryCodes' => $recoveryCodes,
            'setupComplete' => $setupComplete,
            'totpDigits' => $this->mfaPolicy->totpDigits(),
            'totpPeriodSeconds' => $this->mfaPolicy->totpPeriodSeconds(),
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
}
