<?php

namespace App\Actions\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Auth\MfaPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResetUserMfa
{
    public function __construct(private RecordAuthenticationEvent $recordAuthenticationEvent) {}

    /**
     * @return array{reset_at: Carbon, mfa_was_enrolled: bool, pending_enrollment_was_present: bool, previous_recovery_code_count: int, revoked_database_session_count: int, remember_token_rotated: bool, audit_event: LoginEvent}
     */
    public function handle(User $user, ?User $actor = null, ?string $reason = null): array
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

            $resetFields = [
                'mfa_secret' => null,
                'mfa_enabled_at' => null,
                'mfa_confirmed_at' => null,
                'mfa_last_used_at' => null,
                'mfa_recovery_codes' => null,
                'mfa_recovery_codes_generated_at' => null,
                'mfa_pending_secret' => null,
                'mfa_pending_secret_created_at' => null,
                'remember_token' => Str::random(60),
                'remember_token_expires_at' => null,
                'sessions_invalidated_at' => $resetAt,
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

        $revokedDatabaseSessionCount = $this->revokeDatabaseSessions($user);

        $auditEvent = $this->recordAuthenticationEvent->handle($this->consoleRequest(), $user, null, [
            'actor_user_id' => $actor?->getKey(),
            'event_type' => LoginEvent::EVENT_MFA_RESET,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'MFA user direset melalui command Artisan.',
            'auth_method' => 'mfa_reset',
            'mfa_method' => MfaPolicy::METHOD_TOTP,
            'mfa_result' => LoginEvent::RESULT_REVOKED,
            'remember_me' => false,
            'source_channel' => 'console',
            'route_name' => 'auth:mfa-reset',
            'request_path' => 'artisan auth:mfa-reset',
            'http_method' => 'CONSOLE',
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
        ]);

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

    private function revokeDatabaseSessions(User $user): int
    {
        if ((string) config('session.driver', 'database') !== 'database') {
            return 0;
        }

        $sessionTable = $this->sessionTable();

        if (! $this->sessionTableHasUserIdColumn($sessionTable)) {
            return 0;
        }

        $sessionConnection = $this->sessionConnection();
        $query = $sessionConnection === null
            ? DB::table($sessionTable)
            : DB::connection($sessionConnection)->table($sessionTable);

        return $query->where('user_id', $user->getKey())->delete();
    }

    private function sessionTableHasUserIdColumn(string $sessionTable): bool
    {
        $sessionConnection = $this->sessionConnection();

        if ($sessionConnection === null) {
            return Schema::hasTable($sessionTable)
                && Schema::hasColumn($sessionTable, 'user_id');
        }

        return Schema::connection($sessionConnection)->hasTable($sessionTable)
            && Schema::connection($sessionConnection)->hasColumn($sessionTable, 'user_id');
    }

    private function sessionTable(): string
    {
        $sessionTable = config('session.table', 'sessions');

        return is_string($sessionTable) && $sessionTable !== '' ? $sessionTable : 'sessions';
    }

    private function sessionConnection(): ?string
    {
        $sessionConnection = config('session.connection');

        return is_string($sessionConnection) && $sessionConnection !== '' ? $sessionConnection : null;
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
