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

class ActivateManagedUserPosition
{
    public function __construct(
        private PositionSwitcher $positionSwitcher,
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    public function handle(Request $request, User $user, UserPosition $position, ?UserPosition $actor): UserPosition
    {
        try {
            $beforeState = $this->userManagementAuditLogger->positionSnapshot($position);

            Log::channel('module_users')->info('User position activate request', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
            ]);

            $activatedPosition = DB::transaction(function () use ($user, $position): UserPosition {
                return $this->positionSwitcher->setActive($user, $position->id);
            });

            Log::channel('module_users')->info('User position activated', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
            ]);

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_POSITION_ACTIVATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $activatedPosition,
                'message' => 'Posisi user diaktifkan melalui management users.',
                'before_state' => $beforeState,
                'after_state' => $this->userManagementAuditLogger->positionSnapshot($activatedPosition),
                'http_status' => 200,
            ], $request);

            return $activatedPosition;
        } catch (Throwable $e) {
            Log::channel('module_users')->error('User position activate failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_POSITION_ACTIVATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'message' => 'Gagal mengaktifkan posisi user melalui management users.',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
                'http_status' => 500,
            ], $request);

            throw $e;
        }
    }
}
