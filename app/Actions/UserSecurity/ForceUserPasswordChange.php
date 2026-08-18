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

class ForceUserPasswordChange
{
    public function __construct(
        private UserSessionInvalidator $sessionInvalidator,
        private RecordAuthenticationEvent $recordAuthenticationEvent,
        private UserManagementAuditLogger $managementAuditLogger
    ) {}

    /**
     * @return array{forced_at: Carbon, revoked_database_session_count: int, audit_event: LoginEvent}
     */
    public function handle(Request $request, User $user, User $actor, string $reason): array
    {
        $this->ensureDifferentUser($user, $actor);

        $forcedAt = now();

        $state = DB::transaction(function () use ($user, $actor, $forcedAt): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $previousMustChangePassword = (bool) $lockedUser->must_change_password;

            $fields = [
                'must_change_password' => true,
                'password_reset_at' => $forcedAt,
                'password_reset_by_user_id' => $actor->getKey(),
                'updated_by_user_id' => $actor->getKey(),
                ...$this->sessionInvalidator->invalidationFields($forcedAt),
            ];

            $lockedUser->forceFill($fields)->save();
            $user->forceFill($fields);

            return [
                'previous_must_change_password' => $previousMustChangePassword,
            ];
        }, attempts: 3);

        $revokedDatabaseSessionCount = $this->sessionInvalidator->revokeDatabaseSessions($user);

        $auditEvent = $this->recordAuthenticationEvent->handle($request, $user, null, [
            'actor_user_id' => $actor->getKey(),
            'event_type' => LoginEvent::EVENT_PASSWORD_CHANGE_FORCED,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'User diwajibkan mengganti password pada login berikutnya.',
            'auth_method' => 'user_security',
            'http_status' => 200,
            'remember_me' => false,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'target_user_id' => $user->getKey(),
                'previous_must_change_password' => $state['previous_must_change_password'],
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
                'sessions_invalidated_at' => $forcedAt->toISOString(),
                'reason' => $reason,
            ],
        ]);

        Log::channel('module_users')->warning('User force password change', [
            'actor_id' => $actor->getKey(),
            'target_user_id' => $user->getKey(),
            'reason' => $reason,
            'revoked_database_session_count' => $revokedDatabaseSessionCount,
        ]);

        $this->managementAuditLogger->success(UserManagementAuditEvent::EVENT_SECURITY_FORCE_PASSWORD_CHANGE, [
            'actor' => $actor,
            'target_user' => $user,
            'reason' => $reason,
            'message' => 'User diwajibkan mengganti password pada login berikutnya.',
            'before_state' => [
                'must_change_password' => $state['previous_must_change_password'],
            ],
            'after_state' => [
                'must_change_password' => true,
                'password_reset_at' => $forcedAt->toISOString(),
                'sessions_invalidated_at' => $forcedAt->toISOString(),
            ],
            'metadata' => [
                'login_event_id' => $auditEvent->getKey(),
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
            ],
            'http_status' => 200,
        ], $request);

        return [
            'forced_at' => $forcedAt,
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
