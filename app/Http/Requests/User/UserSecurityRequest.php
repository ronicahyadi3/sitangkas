<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class UserSecurityRequest extends FormRequest
{
    protected function authorizeManagedTargetUser(
        UserManagementAccessService $userManagementAccessService,
        ActivePositionService $activePositionService,
        string $eventType = UserManagementAuditEvent::EVENT_SECURITY_FORCE_PASSWORD_CHANGE
    ): bool {
        $actorPosition = $activePositionService->managementActor();
        $targetUser = $this->targetUser();
        $authorized = $targetUser instanceof User
            && $this->user() instanceof User
            && $userManagementAccessService->canManageUser($targetUser, $actorPosition);

        if (! $authorized) {
            $this->logBlockedSecurityAuthorization(
                $eventType,
                $userManagementAccessService,
                $actorPosition,
                $targetUser,
                'unauthorized_scope',
                'Aksi keamanan akun ditolak karena target tidak berada dalam scope aktor.'
            );
        }

        return $authorized;
    }

    protected function authorizeFullAdminTargetUser(
        UserManagementAccessService $userManagementAccessService,
        ActivePositionService $activePositionService,
        string $eventType = UserManagementAuditEvent::EVENT_SECURITY_MFA_RESET
    ): bool {
        $actor = $activePositionService->managementActor();
        $targetUser = $this->targetUser();
        $authorized = $targetUser instanceof User
            && $this->user() instanceof User
            && $userManagementAccessService->isFullAdmin($actor)
            && $userManagementAccessService->canManageUser($targetUser, $actor);

        if (! $authorized) {
            $reasonCode = $userManagementAccessService->isFullAdmin($actor)
                ? 'unauthorized_scope'
                : 'admin_super_required';

            $this->logBlockedSecurityAuthorization(
                $eventType,
                $userManagementAccessService,
                $actor,
                $targetUser,
                $reasonCode,
                'Aksi keamanan akun ditolak karena membutuhkan Admin Super.'
            );
        }

        return $authorized;
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => $this->nullableString('reason'),
        ]);
    }

    protected function targetUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    protected function nullableString(string $key): ?string
    {
        $value = Str::of((string) $this->input($key))->trim()->toString();

        return $value === '' ? null : $value;
    }

    private function logBlockedSecurityAuthorization(
        string $eventType,
        UserManagementAccessService $userManagementAccessService,
        ?UserPosition $actorPosition,
        ?User $targetUser,
        string $reasonCode,
        string $message
    ): void {
        $userManagementAuditLogger = app(UserManagementAuditLogger::class);

        $userManagementAuditLogger->blocked($eventType, [
            'actor_user' => $this->user(),
            'actor_position' => $actorPosition,
            'target_user' => $targetUser,
            'resource_type' => User::class,
            'resource_id' => $targetUser?->getKey(),
            'before_state' => $targetUser instanceof User
                ? $userManagementAuditLogger->userSnapshot($targetUser)
                : null,
            'reason_code' => $reasonCode,
            'reason' => $message,
            'message' => $message,
            'metadata' => [
                'requested_action' => $eventType,
                'actor_is_full_admin' => $userManagementAccessService->isFullAdmin($actorPosition),
                'actor_can_manage_target' => $targetUser instanceof User
                    && $userManagementAccessService->canManageUser($targetUser, $actorPosition),
            ],
        ], $this);
    }
}
