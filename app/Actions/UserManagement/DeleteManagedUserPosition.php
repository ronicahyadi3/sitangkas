<?php

namespace App\Actions\UserManagement;

use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Services\User\PositionSwitcher;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteManagedUserPosition
{
    public function __construct(
        private PositionSwitcher $positionSwitcher,
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    public function handle(Request $request, User $user, UserPosition $position, ?UserPosition $actor): UserPosition
    {
        try {
            $beforeState = $this->userManagementAuditLogger->positionSnapshot($position);
            $wasActive = (bool) $position->is_active;

            Log::channel('module_users')->warning('User position delete request', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'was_active' => $wasActive,
            ]);

            DB::transaction(function () use ($user, $position, $wasActive): void {
                $position->delete();

                if (! $wasActive) {
                    return;
                }

                $nextPosition = $user->positions()->first();

                if ($nextPosition instanceof UserPosition) {
                    $this->positionSwitcher->setActive($user, $nextPosition->id);

                    return;
                }

                session()->forget(['active_position_id', 'active_role', 'active_instansi', 'active_unit_kerja']);
            });

            Log::channel('module_users')->warning('User position deleted', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'was_active' => $wasActive,
            ]);

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_POSITION_DELETED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'message' => 'Posisi user dihapus melalui management users.',
                'before_state' => $beforeState,
                'after_state' => [
                    ...$beforeState,
                    'deleted_at' => now()->toISOString(),
                    'deleted_by_user_id' => auth()->id(),
                ],
                'metadata' => [
                    'was_active' => $wasActive,
                ],
                'http_status' => 200,
            ], $request);

            return $position;
        } catch (Throwable $e) {
            Log::channel('module_users')->error('User position delete failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_POSITION_DELETED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'message' => 'Gagal menghapus posisi user melalui management users.',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
                'http_status' => 500,
            ], $request);

            throw $e;
        }
    }
}
