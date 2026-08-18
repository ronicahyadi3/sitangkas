<?php

namespace App\Actions\UserManagement;

use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Models\UserPositionYearPermission;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RevokeHistoricalYearAccess
{
    public function __construct(
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    public function handle(Request $request, User $user, UserPosition $position, ?UserPosition $actor, int $year): ?UserPositionYearPermission
    {
        $reason = $this->validatedReason($request);

        try {
            $beforePermission = $this->activeHistoricalPermission($position, $year);

            DB::transaction(function () use ($position, $year, $actor, $reason): void {
                UserPositionYearPermission::query()
                    ->historicalWrite()
                    ->active()
                    ->where('user_position_id', $position->id)
                    ->where('tahun', $year)
                    ->update([
                        'status' => UserPositionYearPermission::STATUS_REVOKED,
                        'revoked_by_user_id' => auth()->id(),
                        'revoked_by_position_id' => $actor?->id,
                        'revoked_at' => now(),
                        'revocation_reason' => $reason,
                        'updated_by_user_id' => auth()->id(),
                    ]);
            });

            Log::channel('module_users')->warning('Historical write access revoked', [
                'actor_id' => auth()->id(),
                'actor_position_id' => $actor?->id,
                'target_user_id' => $user->id,
                'target_position_id' => $position->id,
                'tahun' => $year,
                'reason' => $reason,
            ]);

            $afterPermission = UserPositionYearPermission::query()
                ->historicalWrite()
                ->where('user_position_id', $position->id)
                ->where('tahun', $year)
                ->latest('id')
                ->first();

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_YEAR_PERMISSION_REVOKED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'reason' => $reason,
                'message' => "Izin tulis tahun {$year} dicabut melalui management users.",
                'before_state' => $beforePermission instanceof UserPositionYearPermission
                    ? $this->userManagementAuditLogger->yearPermissionSnapshot($beforePermission)
                    : null,
                'after_state' => $afterPermission instanceof UserPositionYearPermission
                    ? $this->userManagementAuditLogger->yearPermissionSnapshot($afterPermission)
                    : null,
                'metadata' => [
                    'tahun' => $year,
                    'permission_found' => $beforePermission instanceof UserPositionYearPermission,
                ],
                'http_status' => 200,
            ], $request);

            return $afterPermission;
        } catch (Throwable $e) {
            Log::channel('module_users')->error('Historical write access revoke failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'target_position_id' => $position->id,
                'tahun' => $year,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_YEAR_PERMISSION_REVOKED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'reason' => $reason,
                'message' => "Gagal mencabut izin tulis tahun {$year} melalui management users.",
                'metadata' => [
                    'tahun' => $year,
                    'error' => $e->getMessage(),
                ],
                'http_status' => 500,
            ], $request);

            throw $e;
        }
    }

    private function validatedReason(Request $request): string
    {
        $request->merge([
            'reason' => trim((string) $request->input('reason')),
        ]);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return (string) $validated['reason'];
    }

    private function activeHistoricalPermission(UserPosition $position, int $year): ?UserPositionYearPermission
    {
        return UserPositionYearPermission::query()
            ->historicalWrite()
            ->active()
            ->currentlyEffective()
            ->where('user_position_id', $position->id)
            ->where('tahun', $year)
            ->first();
    }
}
