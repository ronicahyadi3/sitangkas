<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\MfaSession;
use App\Services\Auth\SensitiveAuthenticationResponseHeaders;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SecurityController extends Controller
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private MfaPolicy $mfaPolicy,
        private MfaSession $mfaSession,
        private SensitiveAuthenticationResponseHeaders $sensitiveResponseHeaders
    ) {}

    public function __invoke(Request $request): View|Response
    {
        $viewData = [
            ...$this->currentUserContext->viewData($request),
            'mfaStatus' => $this->mfaStatus($request),
        ];

        if ($this->hasRegeneratedRecoveryCodes($request)) {
            return $this->sensitiveResponseHeaders->noStoreView('profile.security', $viewData);
        }

        return view('profile.security', $viewData);
    }

    /**
     * @return array{
     *     label: string,
     *     badge_class: string,
     *     icon: string,
     *     method_label: string,
     *     policy_label: string,
     *     last_used_at_label: string,
     *     enabled_at_label: string,
     *     recovery_codes_generated_at_label: string,
     *     recovery_codes_remaining: int,
     *     session_method_label: string,
     *     session_expires_at_label: string,
     *     is_enrolled: bool,
     *     has_pending_enrollment: bool,
     *     is_required: bool,
     *     is_available: bool,
     *     is_verified_with_totp: bool,
     *     is_admin_super_position: bool,
     *     can_start_enrollment: bool,
     *     can_continue_enrollment: bool,
     *     enrollment_action_label: string,
     *     enrollment_action_icon: string,
     *     enrollment_action_help: string
     * }
     */
    private function mfaStatus(Request $request): array
    {
        $user = $this->currentUserContext->user($request);
        $realActiveUserPosition = $this->currentUserContext->realActivePosition($request);

        abort_unless($user instanceof User && $realActiveUserPosition instanceof UserPosition, 403);

        $isEnrolled = $this->mfaPolicy->isEnrolled($user);
        $hasPendingEnrollment = $this->mfaPolicy->hasPendingEnrollment($user);
        $isAvailable = $this->mfaPolicy->availableFor($realActiveUserPosition);
        $isRequired = $this->mfaPolicy->requiredFor($realActiveUserPosition, $user);
        $isVerifiedWithTotp = $this->mfaSession->isVerifiedFor($request, $user, $realActiveUserPosition, MfaPolicy::METHOD_TOTP);
        $isAdminSuperPosition = $this->currentUserContext->isAdminSuperPosition($realActiveUserPosition);
        $sessionState = $this->mfaSession->state($request);
        $statusPresentation = $this->statusPresentation($isEnrolled, $hasPendingEnrollment, $isRequired, $isAvailable);
        $enrollmentAction = $this->enrollmentActionPresentation($isAvailable, $isEnrolled, $hasPendingEnrollment, $isAdminSuperPosition);

        return [
            ...$statusPresentation,
            ...$enrollmentAction,
            'method_label' => $isEnrolled ? $this->methodLabel($this->mfaPolicy->method()) : 'Belum aktif',
            'policy_label' => $this->policyLabel($isRequired, $isAvailable),
            'last_used_at_label' => $this->dateTimeLabel($user->mfa_last_used_at, 'Belum pernah'),
            'enabled_at_label' => $this->dateTimeLabel($user->mfa_enabled_at, 'Belum aktif'),
            'recovery_codes_generated_at_label' => $this->dateTimeLabel($user->mfa_recovery_codes_generated_at, 'Belum dibuat'),
            'recovery_codes_remaining' => $isEnrolled ? $this->recoveryCodeCount($user->mfa_recovery_codes) : 0,
            'session_method_label' => $sessionState !== null ? $this->methodLabel($sessionState['method']) : 'Tidak aktif',
            'session_expires_at_label' => $this->dateTimeLabel($sessionState['expires_at'] ?? null, 'Tidak aktif'),
            'is_enrolled' => $isEnrolled,
            'has_pending_enrollment' => $hasPendingEnrollment,
            'is_required' => $isRequired,
            'is_available' => $isAvailable,
            'is_verified_with_totp' => $isVerifiedWithTotp,
            'is_admin_super_position' => $isAdminSuperPosition,
        ];
    }

    /**
     * @return array{label: string, badge_class: string, icon: string}
     */
    private function statusPresentation(bool $isEnrolled, bool $hasPendingEnrollment, bool $isRequired, bool $isAvailable): array
    {
        if ($isEnrolled) {
            return [
                'label' => 'Aktif',
                'badge_class' => 'bg-gradient-success',
                'icon' => 'fa-circle-check',
            ];
        }

        if ($hasPendingEnrollment) {
            return [
                'label' => 'Setup Pending',
                'badge_class' => 'bg-gradient-warning',
                'icon' => 'fa-clock',
            ];
        }

        if ($isRequired) {
            return [
                'label' => 'Wajib Diaktifkan',
                'badge_class' => 'bg-gradient-danger',
                'icon' => 'fa-triangle-exclamation',
            ];
        }

        if ($isAvailable) {
            return [
                'label' => 'Tersedia',
                'badge_class' => 'bg-gradient-info',
                'icon' => 'fa-circle-info',
            ];
        }

        return [
            'label' => 'Tidak Tersedia',
            'badge_class' => 'bg-gradient-secondary',
            'icon' => 'fa-ban',
        ];
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            MfaPolicy::METHOD_TOTP => 'Google Authenticator / TOTP',
            MfaPolicy::METHOD_RECOVERY_CODE => 'Recovery Code',
            default => str($method)->replace(['-', '_'], ' ')->title()->toString(),
        };
    }

    private function policyLabel(bool $isRequired, bool $isAvailable): string
    {
        if ($isRequired) {
            return 'Wajib';
        }

        return $isAvailable ? 'Optional' : 'Nonaktif';
    }

    /**
     * @return array{can_start_enrollment: bool, can_continue_enrollment: bool, enrollment_action_label: string, enrollment_action_icon: string, enrollment_action_help: string}
     */
    private function enrollmentActionPresentation(
        bool $isAvailable,
        bool $isEnrolled,
        bool $hasPendingEnrollment,
        bool $isAdminSuperPosition
    ): array {
        $canManageOptionalEnrollment = $isAvailable && ! $isAdminSuperPosition && ! $isEnrolled;
        $canContinueEnrollment = $canManageOptionalEnrollment && $hasPendingEnrollment;
        $canStartEnrollment = $canManageOptionalEnrollment && ! $hasPendingEnrollment;

        if ($canContinueEnrollment) {
            return [
                'can_start_enrollment' => false,
                'can_continue_enrollment' => true,
                'enrollment_action_label' => 'Lanjutkan Setup MFA',
                'enrollment_action_icon' => 'fa-arrow-right',
                'enrollment_action_help' => 'Setup authenticator masih pending.',
            ];
        }

        if ($canStartEnrollment) {
            return [
                'can_start_enrollment' => true,
                'can_continue_enrollment' => false,
                'enrollment_action_label' => 'Aktifkan MFA',
                'enrollment_action_icon' => 'fa-mobile-screen-button',
                'enrollment_action_help' => 'MFA optional tersedia untuk akun ini.',
            ];
        }

        return [
            'can_start_enrollment' => false,
            'can_continue_enrollment' => false,
            'enrollment_action_label' => '',
            'enrollment_action_icon' => '',
            'enrollment_action_help' => '',
        ];
    }

    private function dateTimeLabel(?DateTimeInterface $dateTime, string $emptyLabel): string
    {
        if (! $dateTime instanceof DateTimeInterface) {
            return $emptyLabel;
        }

        return $dateTime->format('d/m/Y H:i');
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

    private function hasRegeneratedRecoveryCodes(Request $request): bool
    {
        $regeneratedRecoveryCodes = $request->session()->get('mfa_recovery_codes_regenerated');

        if (! is_array($regeneratedRecoveryCodes) || ! is_array($regeneratedRecoveryCodes['codes'] ?? null)) {
            return false;
        }

        foreach ($regeneratedRecoveryCodes['codes'] as $recoveryCode) {
            if (is_string($recoveryCode) && $recoveryCode !== '') {
                return true;
            }
        }

        return false;
    }
}
