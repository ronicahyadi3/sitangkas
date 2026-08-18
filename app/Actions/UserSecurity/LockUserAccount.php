<?php

namespace App\Actions\UserSecurity;

use App\Actions\Auth\RecordAuthenticationEvent;
use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Services\Auth\UserSessionInvalidator;
use App\Services\User\UserManagementAuditLogger;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LockUserAccount
{
    public function __construct(
        private UserSessionInvalidator $sessionInvalidator,
        private RecordAuthenticationEvent $recordAuthenticationEvent,
        private UserManagementAuditLogger $managementAuditLogger
    ) {}

    /**
     * @return array{locked_at: Carbon, revoked_database_session_count: int, audit_event: LoginEvent}
     */
    public function handle(Request $request, User $user, User $actor, string $reason, ?DateTimeInterface $lockedUntil = null): array
    {
        $this->ensureDifferentUser($user, $actor);

        $lockedAt = now();

        $state = DB::transaction(function () use ($user, $actor, $reason, $lockedAt, $lockedUntil): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $lockedUser->status;
            $previousLockedUntil = $lockedUser->locked_until;

            if ($lockedUser->isLocked()) {
                throw ValidationException::withMessages([
                    'user' => 'Akun ini sudah terkunci.',
                ]);
            }

            if ($lockedUser->status !== User::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'user' => 'Akun hanya dapat dikunci jika statusnya aktif.',
                ]);
            }

            $fields = [
                'status' => User::STATUS_LOCKED,
                'status_changed_at' => $lockedAt,
                'status_changed_by_user_id' => $actor->getKey(),
                'status_reason' => $reason,
                'locked_at' => $lockedAt,
                'locked_until' => $lockedUntil,
                'lock_reason' => $reason,
                'updated_by_user_id' => $actor->getKey(),
                ...$this->sessionInvalidator->invalidationFields($lockedAt),
            ];

            $lockedUser->forceFill($fields)->save();
            $user->forceFill($fields);

            return [
                'previous_status' => $previousStatus,
                'previous_locked_until' => $previousLockedUntil?->toISOString(),
            ];
        }, attempts: 3);

        $revokedDatabaseSessionCount = $this->sessionInvalidator->revokeDatabaseSessions($user);

        $auditEvent = $this->recordAuthenticationEvent->handle($request, $user, null, [
            'actor_user_id' => $actor->getKey(),
            'event_type' => LoginEvent::EVENT_ACCOUNT_LOCKED,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Akun user dikunci melalui management users.',
            'auth_method' => 'user_security',
            'http_status' => 200,
            'remember_me' => false,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'target_user_id' => $user->getKey(),
                'previous_status' => $state['previous_status'],
                'previous_locked_until' => $state['previous_locked_until'],
                'locked_until' => $lockedUntil instanceof DateTimeInterface ? $lockedUntil->format(DATE_ATOM) : null,
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
                'sessions_invalidated_at' => $lockedAt->toISOString(),
                'reason' => $reason,
            ],
        ]);

        Log::channel('module_users')->warning('User account locked', [
            'actor_id' => $actor->getKey(),
            'target_user_id' => $user->getKey(),
            'locked_until' => $lockedUntil instanceof DateTimeInterface ? $lockedUntil->format(DATE_ATOM) : null,
            'reason' => $reason,
            'revoked_database_session_count' => $revokedDatabaseSessionCount,
        ]);

        $this->managementAuditLogger->success(UserManagementAuditEvent::EVENT_SECURITY_LOCK, [
            'actor' => $actor,
            'target_user' => $user,
            'reason' => $reason,
            'message' => 'Akun user dikunci melalui management users.',
            'before_state' => [
                'status' => $state['previous_status'],
                'locked_until' => $state['previous_locked_until'],
            ],
            'after_state' => [
                'status' => User::STATUS_LOCKED,
                'locked_at' => $lockedAt->toISOString(),
                'locked_until' => $lockedUntil instanceof DateTimeInterface ? $lockedUntil->format(DATE_ATOM) : null,
                'sessions_invalidated_at' => $lockedAt->toISOString(),
            ],
            'metadata' => [
                'login_event_id' => $auditEvent->getKey(),
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
            ],
            'http_status' => 200,
        ], $request);

        return [
            'locked_at' => $lockedAt,
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
