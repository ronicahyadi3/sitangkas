<?php

namespace App\Actions\UserManagement;

use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteManagedUser
{
    public function __construct(
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    public function handle(Request $request, User $user, ?UserPosition $actor): User
    {
        try {
            $beforeState = $this->userManagementAuditLogger->userSnapshot($user);

            Log::channel('module_users')->warning('User delete request', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'target_name' => $user->nama,
            ]);

            DB::transaction(function () use ($user): void {
                $user->delete();
            });

            Log::channel('module_users')->warning('User deleted', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
            ]);

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_USER_DELETED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'message' => 'User dihapus melalui management users.',
                'before_state' => $beforeState,
                'after_state' => [
                    ...$beforeState,
                    'deleted_at' => now()->toISOString(),
                    'deleted_by_user_id' => auth()->id(),
                ],
                'http_status' => 200,
            ], $request);

            return $user;
        } catch (Throwable $e) {
            Log::channel('module_users')->error('User delete failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_USER_DELETED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'message' => 'Gagal menghapus user melalui management users.',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
                'http_status' => 500,
            ], $request);

            throw $e;
        }
    }
}
