<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\UserSessionInvalidator;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResetUserMfa
{
    public function __construct(
        private RecordAuthenticationEvent $recordAuthenticationEvent,
        private MfaPolicy $mfaPolicy,
        private UserSessionInvalidator $sessionInvalidator,
        private UserManagementAuditLogger $managementAuditLogger
    ) {}

    /**
     * @return array{reset_at: Carbon, mfa_was_enrolled: bool, pending_enrollment_was_present: bool, previous_recovery_code_count: int, revoked_database_session_count: int, remember_token_rotated: bool, audit_event: LoginEvent}
     */
    public function handle(User $user, ?User $actor = null, ?string $reason = null, ?Request $request = null): array
    {
        $resetAt = now();

        $state = DB::transaction(function () use ($user, $actor, $resetAt): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $mfaWasEnrolled = filled($lockedUser->mfa_secret)
                || $lockedUser->mfa_enabled_at instanceof Carbon
                || $lockedUser->mfa_confirmed_at instanceof Carbon;
            $pendingEnrollmentWasPresent = filled($lockedUser->mfa_pending_secret)
                || $lockedUser->mfa_pending_secret_created_at instanceof Carbon;
            $previousRecoveryCodeCount = $this->recoveryCodeCount($lockedUser->mfa_recovery_codes);

            if (! $this->mfaPolicy->isEnrolled($lockedUser) && ! $this->mfaPolicy->hasPendingEnrollment($lockedUser)) {
                throw ValidationException::withMessages([
                    'user' => 'Akun ini belum memiliki MFA yang perlu direset.',
                ]);
            }

            $resetFields = [
                'mfa_secret' => null,
                'mfa_enabled_at' => null,
                'mfa_confirmed_at' => null,
                'mfa_last_used_at' => null,
                'mfa_recovery_codes' => null,
                'mfa_recovery_codes_generated_at' => null,
                'mfa_pending_secret' => null,
                'mfa_pending_secret_created_at' => null,
                ...$this->sessionInvalidator->invalidationFields($resetAt),
            ];

            if ($actor instanceof User) {
                $resetFields['updated_by_user_id'] = $actor->getKey();
            }

            $lockedUser->forceFill($resetFields)->save();
            $user->forceFill($resetFields);

            return [
                'mfa_was_enrolled' => $mfaWasEnrolled,
                'pending_enrollment_was_present' => $pendingEnrollmentWasPresent,
                'previous_recovery_code_count' => $previousRecoveryCodeCount,
            ];
        }, attempts: 3);

        $revokedDatabaseSessionCount = $this->sessionInvalidator->revokeDatabaseSessions($user);
        $auditRequest = $request ?? $this->consoleRequest();
        $auditOverrides = [
            'actor_user_id' => $actor?->getKey(),
            'event_type' => LoginEvent::EVENT_MFA_RESET,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => $request instanceof Request
                ? 'MFA user direset melalui management users.'
                : 'MFA user direset melalui command Artisan.',
            'auth_method' => 'mfa_reset',
            'mfa_method' => MfaPolicy::METHOD_TOTP,
            'mfa_result' => LoginEvent::RESULT_REVOKED,
            'remember_me' => false,
            'http_status' => 200,
            'session_id_hash' => $request instanceof Request
                ? $this->recordAuthenticationEvent->sessionIdHash($request)
                : null,
            'metadata' => [
                'target_user_id' => $user->getKey(),
                'target_nik' => $user->nik,
                'target_email' => $user->email,
                'mfa_was_enrolled' => $state['mfa_was_enrolled'],
                'pending_enrollment_was_present' => $state['pending_enrollment_was_present'],
                'previous_recovery_code_count' => $state['previous_recovery_code_count'],
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
                'remember_token_rotated' => true,
                'sessions_invalidated_at' => $resetAt->toISOString(),
                'reason' => $reason,
            ],
        ];

        if (! $request instanceof Request) {
            $auditOverrides = [
                ...$auditOverrides,
                'source_channel' => 'console',
                'route_name' => 'auth:mfa-reset',
                'request_path' => 'artisan auth:mfa-reset',
                'http_method' => 'CONSOLE',
            ];
        }

        $auditEvent = $this->recordAuthenticationEvent->handle($auditRequest, $user, null, $auditOverrides);

        $this->managementAuditLogger->success(UserManagementAuditEvent::EVENT_SECURITY_MFA_RESET, [
            'actor' => $actor,
            'target_user' => $user,
            'reason' => $reason,
            'message' => $request instanceof Request
                ? 'MFA user direset melalui management users.'
                : 'MFA user direset melalui command Artisan.',
            'before_state' => [
                'mfa_was_enrolled' => $state['mfa_was_enrolled'],
                'pending_enrollment_was_present' => $state['pending_enrollment_was_present'],
                'previous_recovery_code_count' => $state['previous_recovery_code_count'],
            ],
            'after_state' => [
                'mfa_enabled_at' => null,
                'mfa_confirmed_at' => null,
                'mfa_recovery_codes' => null,
                'mfa_pending_secret' => null,
                'sessions_invalidated_at' => $resetAt->toISOString(),
            ],
            'metadata' => [
                'login_event_id' => $auditEvent->getKey(),
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
                'remember_token_rotated' => true,
            ],
            'http_status' => 200,
            ...(! $request instanceof Request ? [
                'source_channel' => 'console',
                'route_name' => 'auth:mfa-reset',
                'request_path' => 'artisan auth:mfa-reset',
                'http_method' => 'CONSOLE',
            ] : []),
        ], $auditRequest);

        return [
            'reset_at' => $resetAt,
            'mfa_was_enrolled' => $state['mfa_was_enrolled'],
            'pending_enrollment_was_present' => $state['pending_enrollment_was_present'],
            'previous_recovery_code_count' => $state['previous_recovery_code_count'],
            'revoked_database_session_count' => $revokedDatabaseSessionCount,
            'remember_token_rotated' => true,
            'audit_event' => $auditEvent,
        ];
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

    private function consoleRequest(): Request
    {
        return Request::create(
            '/artisan/auth-mfa-reset',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_USER_AGENT' => 'PHP CLI',
            ]
        );
    }
}
