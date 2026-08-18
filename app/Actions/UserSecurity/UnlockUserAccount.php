<?php

namespace App\Actions\UserSecurity;

use App\Actions\Auth\RecordAuthenticationEvent;
use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Services\Auth\UserSessionInvalidator;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnlockUserAccount
{
    public function __construct(
        private UserSessionInvalidator $sessionInvalidator,
        private RecordAuthenticationEvent $recordAuthenticationEvent,
        private UserManagementAuditLogger $managementAuditLogger
    ) {}

    /**
     * @return array{unlocked_at: Carbon, revoked_database_session_count: int, audit_event: LoginEvent}
     */
    public function handle(Request $request, User $user, User $actor, string $reason): array
    {
        $this->ensureDifferentUser($user, $actor);

        $unlockedAt = now();

        $state = DB::transaction(function () use ($user, $actor, $reason, $unlockedAt): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $lockedUser->status;
            $previousLockedAt = $lockedUser->locked_at;
            $previousLockedUntil = $lockedUser->locked_until;

            if ($lockedUser->status !== User::STATUS_LOCKED && ! $lockedUser->isLocked()) {
                throw ValidationException::withMessages([
                    'user' => 'Akun ini tidak sedang terkunci.',
                ]);
            }

            if ($lockedUser->status !== User::STATUS_LOCKED && $lockedUser->status !== User::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'user' => 'Akun dengan status nonaktif, pending, atau suspended tidak dapat dibuka melalui aksi unlock.',
                ]);
            }

            $fields = [
                'status' => User::STATUS_ACTIVE,
                'status_changed_at' => $unlockedAt,
                'status_changed_by_user_id' => $actor->getKey(),
                'status_reason' => $reason,
                'locked_at' => null,
                'locked_until' => null,
                'lock_reason' => null,
                'consecutive_failed_login_count' => 0,
                'last_failed_login_at' => null,
                'updated_by_user_id' => $actor->getKey(),
                ...$this->sessionInvalidator->invalidationFields($unlockedAt),
            ];

            $lockedUser->forceFill($fields)->save();
            $user->forceFill($fields);

            return [
                'previous_status' => $previousStatus,
                'previous_locked_at' => $previousLockedAt?->toISOString(),
                'previous_locked_until' => $previousLockedUntil?->toISOString(),
            ];
        }, attempts: 3);

        $revokedDatabaseSessionCount = $this->sessionInvalidator->revokeDatabaseSessions($user);

        $auditEvent = $this->recordAuthenticationEvent->handle($request, $user, null, [
            'actor_user_id' => $actor->getKey(),
            'event_type' => LoginEvent::EVENT_ACCOUNT_UNLOCKED,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Akun user dibuka kembali melalui management users.',
            'auth_method' => 'user_security',
            'http_status' => 200,
            'remember_me' => false,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'target_user_id' => $user->getKey(),
                'previous_status' => $state['previous_status'],
                'previous_locked_at' => $state['previous_locked_at'],
                'previous_locked_until' => $state['previous_locked_until'],
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
                'sessions_invalidated_at' => $unlockedAt->toISOString(),
                'reason' => $reason,
            ],
        ]);

        Log::channel('module_users')->warning('User account unlocked', [
            'actor_id' => $actor->getKey(),
            'target_user_id' => $user->getKey(),
            'reason' => $reason,
            'revoked_database_session_count' => $revokedDatabaseSessionCount,
        ]);

        $this->managementAuditLogger->success(UserManagementAuditEvent::EVENT_SECURITY_UNLOCK, [
            'actor' => $actor,
            'target_user' => $user,
            'reason' => $reason,
            'message' => 'Akun user dibuka kembali melalui management users.',
            'before_state' => [
                'status' => $state['previous_status'],
                'locked_at' => $state['previous_locked_at'],
                'locked_until' => $state['previous_locked_until'],
            ],
            'after_state' => [
                'status' => User::STATUS_ACTIVE,
                'locked_at' => null,
                'locked_until' => null,
                'consecutive_failed_login_count' => 0,
                'sessions_invalidated_at' => $unlockedAt->toISOString(),
            ],
            'metadata' => [
                'login_event_id' => $auditEvent->getKey(),
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
            ],
            'http_status' => 200,
        ], $request);

        return [
            'unlocked_at' => $unlockedAt,
            'revoked_database_session_count' => $revokedDatabaseSessionCount,
            'audit_event' => $auditEvent,
        ];
    }

    private function ensureDifferentUser(User $user, User $actor): void
    {
        if ((int) $user->getKey() !== (int) $actor->getKey()) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => 'Anda tidak dapat menjalankan aksi keamanan ini untuk akun sendiri.',
        ]);
    }
}
