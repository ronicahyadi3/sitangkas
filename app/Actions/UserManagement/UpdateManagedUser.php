<?php

namespace App\Actions\UserManagement;

use App\Http\Requests\User\UserUpdateRequest;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateManagedUser
{
    public function __construct(
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    public function handle(UserUpdateRequest $request, User $user, ?UserPosition $actor): User
    {
        try {
            $data = $request->validated();
            $passwordWasChanged = ! empty($data['password'] ?? '');
            $beforeState = $this->userManagementAuditLogger->userSnapshot($user);

            if (! $passwordWasChanged) {
                unset($data['password']);
            }

            unset($data['old_password']);

            $data = $this->withStatusAuditAttributes($data, $user);

            Log::channel('module_users')->info('User update request', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'fields' => array_values(array_diff(array_keys($data), ['password', 'old_password'])),
            ]);

            DB::transaction(function () use ($user, $data, $passwordWasChanged): void {
                if ($passwordWasChanged) {
                    $user->forceFill(array_merge($data, [
                        'password_changed_at' => null,
                        'must_change_password' => true,
                    ]))->save();

                    return;
                }

                $user->update($data);
            });

            Log::channel('module_users')->info('User updated', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
            ]);

            $user->refresh();

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_USER_UPDATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'message' => 'User diperbarui melalui management users.',
                'before_state' => $beforeState,
                'after_state' => $this->userManagementAuditLogger->userSnapshot($user),
                'metadata' => [
                    'password_was_changed' => $passwordWasChanged,
                ],
                'http_status' => 200,
            ], $request);

            return $user;
        } catch (Throwable $e) {
            Log::channel('module_users')->error('User update failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_USER_UPDATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'message' => 'Gagal memperbarui user melalui management users.',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
                'http_status' => 500,
            ], $request);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withStatusAuditAttributes(array $data, User $existingUser): array
    {
        $newStatus = (string) ($data['status'] ?? User::STATUS_ACTIVE);
        $oldStatus = $existingUser->status ?? User::STATUS_ACTIVE;
        $statusChanged = $newStatus !== $oldStatus;

        if ($statusChanged) {
            $data['status_changed_at'] = now();
            $data['status_changed_by_user_id'] = auth()->id();
        }

        if ($newStatus === User::STATUS_LOCKED && $statusChanged) {
            $data['locked_at'] = now();
            $data['lock_reason'] = $data['status_reason'] ?? null;
        }

        if ($newStatus !== User::STATUS_LOCKED && $oldStatus === User::STATUS_LOCKED) {
            $data['locked_at'] = null;
            $data['locked_until'] = null;
            $data['lock_reason'] = null;
        }

        return $data;
    }
}
