<?php

namespace App\Actions\UserManagement;

use App\Http\Requests\User\UserPositionDeactivateRequest;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Services\User\PositionSwitcher;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeactivateManagedUserPosition
{
    public function __construct(
        private PositionSwitcher $positionSwitcher,
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    public function handle(
        UserPositionDeactivateRequest $request,
        User $user,
        UserPosition $position,
        ?UserPosition $actor
    ): UserPosition {
        $data = $request->validated();
        $endedAt = Carbon::parse($data['ended_at'] ?? today())->toDateString();
        $reason = (string) $data['deactivation_reason'];
        $shouldRefreshCurrentSession = (int) auth()->id() === (int) $user->id
            && (int) session('active_position_id') === (int) $position->id;

        try {
            $beforeState = $this->userManagementAuditLogger->positionSnapshot($position);

            Log::channel('module_users')->warning('User position deactivate request', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'ended_at' => $endedAt,
                'reason' => $reason,
            ]);

            DB::transaction(function () use ($position, $endedAt, $reason, $shouldRefreshCurrentSession, $user): void {
                $position->forceFill([
                    'is_active' => false,
                    'ended_at' => $endedAt,
                    'deactivated_at' => now(),
                    'deactivated_by_user_id' => auth()->id(),
                    'deactivation_reason' => $reason,
                    'updated_by_user_id' => auth()->id(),
                ])->save();

                $position->primaryDocument()->update([
                    'effective_until' => $endedAt,
                    'updated_by_user_id' => auth()->id(),
                ]);

                if (! $shouldRefreshCurrentSession) {
                    return;
                }

                $nextPosition = $user->positions()
                    ->availableForSelection()
                    ->whereKeyNot($position->id)
                    ->preferredFirst()
                    ->first();

                if ($nextPosition instanceof UserPosition) {
                    $this->positionSwitcher->setActive($user, $nextPosition->id);

                    return;
                }

                session()->forget(['active_position_id', 'active_role', 'active_instansi', 'active_unit_kerja']);
            });

            Log::channel('module_users')->warning('User position deactivated', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'ended_at' => $endedAt,
            ]);

            $position->refresh();
            $position->loadMissing('primaryDocument');

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_POSITION_DEACTIVATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'reason' => $reason,
                'message' => 'Posisi user dinonaktifkan melalui management users.',
                'before_state' => $beforeState,
                'after_state' => $this->userManagementAuditLogger->positionSnapshot($position),
                'metadata' => [
                    'should_refresh_current_session' => $shouldRefreshCurrentSession,
                ],
                'http_status' => 200,
            ], $request);

            return $position;
        } catch (Throwable $e) {
            Log::channel('module_users')->error('User position deactivate failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_POSITION_DEACTIVATED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'reason' => $reason,
                'message' => 'Gagal menonaktifkan posisi user melalui management users.',
                'metadata' => [
                    'error' => $e->getMessage(),
                ],
                'http_status' => 500,
            ], $request);

            throw $e;
        }
    }
}
