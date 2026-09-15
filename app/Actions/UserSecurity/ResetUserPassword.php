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

class ResetUserPassword
{
    private const LOWERCASE = 'abcdefghijkmnopqrstuvwxyz';

    private const UPPERCASE = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const DIGITS = '23456789';

    private const SYMBOLS = '!@#$%^&*()-_=+';

    public function __construct(
        private UserSessionInvalidator $sessionInvalidator,
        private RecordAuthenticationEvent $recordAuthenticationEvent,
        private UserManagementAuditLogger $managementAuditLogger
    ) {}

    /**
     * @return array{temporary_password: string, reset_at: Carbon, revoked_database_session_count: int, audit_event: LoginEvent}
     */
    public function handle(Request $request, User $user, User $actor, string $reason): array
    {
        $this->ensureDifferentUser($user, $actor);

        $temporaryPassword = $this->generateTemporaryPassword();
        $resetAt = now();

        $state = DB::transaction(function () use ($user, $actor, $temporaryPassword, $resetAt): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $beforeState = $this->managementAuditLogger->userSnapshot($lockedUser);

            $fields = [
                'password' => $temporaryPassword,
                'password_changed_at' => null,
                'password_expires_at' => null,
                'must_change_password' => true,
                'password_reset_at' => $resetAt,
                'password_reset_by_user_id' => $actor->getKey(),
                'consecutive_failed_login_count' => 0,
                'last_failed_login_at' => null,
                'updated_by_user_id' => $actor->getKey(),
                ...$this->sessionInvalidator->invalidationFields($resetAt),
            ];

            $lockedUser->forceFill($fields)->save();
            $user->forceFill($fields);

            return [
                'before_state' => $beforeState,
                'after_state' => $this->managementAuditLogger->userSnapshot($lockedUser),
            ];
        }, attempts: 3);

        $revokedDatabaseSessionCount = $this->sessionInvalidator->revokeDatabaseSessions($user);

        $auditEvent = $this->recordAuthenticationEvent->handle($request, $user, null, [
            'actor_user_id' => $actor->getKey(),
            'event_type' => LoginEvent::EVENT_PASSWORD_RESET_BY_ADMIN,
            'result' => LoginEvent::RESULT_SUCCESS,
            'message' => 'Password user direset oleh Admin Super melalui management users.',
            'auth_method' => 'user_security',
            'http_status' => 200,
            'remember_me' => false,
            'session_id_hash' => $this->recordAuthenticationEvent->sessionIdHash($request),
            'metadata' => [
                'target_user_id' => $user->getKey(),
                'temporary_password_length' => mb_strlen($temporaryPassword),
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
                'sessions_invalidated_at' => $resetAt->toISOString(),
                'reason' => $reason,
            ],
        ]);

        Log::channel('module_users')->warning('User password reset by Admin Super', [
            'actor_id' => $actor->getKey(),
            'target_user_id' => $user->getKey(),
            'reason' => $reason,
            'temporary_password_length' => mb_strlen($temporaryPassword),
            'revoked_database_session_count' => $revokedDatabaseSessionCount,
        ]);

        $this->managementAuditLogger->success(UserManagementAuditEvent::EVENT_SECURITY_PASSWORD_RESET, [
            'actor' => $actor,
            'target_user' => $user,
            'reason' => $reason,
            'message' => 'Password user direset oleh Admin Super melalui management users.',
            'before_state' => $state['before_state'],
            'after_state' => $state['after_state'],
            'metadata' => [
                'login_event_id' => $auditEvent->getKey(),
                'temporary_password_length' => mb_strlen($temporaryPassword),
                'revoked_database_session_count' => $revokedDatabaseSessionCount,
            ],
            'http_status' => 200,
        ], $request);

        return [
            'temporary_password' => $temporaryPassword,
            'reset_at' => $resetAt,
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

    private function generateTemporaryPassword(int $length = 16): string
    {
        $requiredCharacters = [
            $this->randomCharacter(self::LOWERCASE),
            $this->randomCharacter(self::UPPERCASE),
            $this->randomCharacter(self::DIGITS),
            $this->randomCharacter(self::SYMBOLS),
        ];

        $pool = self::LOWERCASE.self::UPPERCASE.self::DIGITS.self::SYMBOLS;

        while (count($requiredCharacters) < $length) {
            $requiredCharacters[] = $this->randomCharacter($pool);
        }

        return $this->shuffleCharacters($requiredCharacters);
    }

    private function randomCharacter(string $characters): string
    {
        return $characters[random_int(0, strlen($characters) - 1)];
    }

    /**
     * @param  list<string>  $characters
     */
    private function shuffleCharacters(array $characters): string
    {
        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swapIndex = random_int(0, $index);
            [$characters[$index], $characters[$swapIndex]] = [$characters[$swapIndex], $characters[$index]];
        }

        return implode('', $characters);
    }
}
