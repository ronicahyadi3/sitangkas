<?php

namespace App\Actions\UserManagement;

use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Models\UserPositionYearPermission;
use App\Services\User\UserManagementAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GrantHistoricalYearAccess
{
    public function __construct(
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    public function handle(Request $request, User $user, UserPosition $position, ?UserPosition $actor, int $year): UserPositionYearPermission
    {
        $grantData = $this->validatedGrantData($request);

        try {
            $beforePermission = $this->activeHistoricalPermission($position, $year);

            $permission = DB::transaction(function () use ($position, $year, $actor, $grantData): UserPositionYearPermission {
                return UserPositionYearPermission::query()->updateOrCreate(
                    [
                        'user_position_id' => $position->id,
                        'tahun' => $year,
                        'permission_type' => UserPositionYearPermission::TYPE_HISTORICAL_WRITE,
                        'status' => UserPositionYearPermission::STATUS_ACTIVE,
                    ],
                    [
                        'valid_from' => now(),
                        'valid_until' => $grantData['valid_until'],
                        'reason' => $grantData['reason'],
                        'reference_number' => $grantData['reference_number'],
                        'reference_date' => $grantData['reference_date'],
                        'granted_by_user_id' => auth()->id(),
                        'granted_by_position_id' => $actor?->id,
                        'granted_at' => now(),
                        'grant_notes' => $grantData['grant_notes'],
                        'updated_by_user_id' => auth()->id(),
                        'revoked_by_user_id' => null,
                        'revoked_by_position_id' => null,
                        'revoked_at' => null,
                        'revocation_reason' => null,
                        'last_used_by_user_id' => null,
                        'last_used_by_position_id' => null,
                        'last_used_at' => null,
                        'usage_count' => 0,
                    ]
                );
            });

            Log::channel('module_users')->info('Historical write access granted', [
                'actor_id' => auth()->id(),
                'actor_position_id' => $actor?->id,
                'target_user_id' => $user->id,
                'target_position_id' => $position->id,
                'tahun' => $year,
                'reason' => $grantData['reason'],
                'reference_number' => $grantData['reference_number'],
                'reference_date' => $grantData['reference_date'],
                'valid_until' => $grantData['valid_until']?->toDateTimeString(),
            ]);

            $this->userManagementAuditLogger->success(UserManagementAuditEvent::EVENT_YEAR_PERMISSION_GRANTED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'reason' => $grantData['reason'],
                'message' => "Izin tulis tahun {$year} diberikan melalui management users.",
                'before_state' => $beforePermission instanceof UserPositionYearPermission
                    ? $this->userManagementAuditLogger->yearPermissionSnapshot($beforePermission)
                    : null,
                'after_state' => $this->userManagementAuditLogger->yearPermissionSnapshot($permission),
                'metadata' => [
                    'tahun' => $year,
                    'reference_number' => $grantData['reference_number'],
                    'reference_date' => $grantData['reference_date'],
                    'valid_until' => $grantData['valid_until']?->toISOString(),
                    'grant_notes' => $grantData['grant_notes'],
                ],
                'http_status' => 200,
            ], $request);

            return $permission;
        } catch (Throwable $e) {
            Log::channel('module_users')->error('Historical write access grant failed', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'target_position_id' => $position->id,
                'tahun' => $year,
                'error' => $e->getMessage(),
            ]);

            $this->userManagementAuditLogger->failed(UserManagementAuditEvent::EVENT_YEAR_PERMISSION_GRANTED, [
                'actor_position' => $actor,
                'target_user' => $user,
                'target_position' => $position,
                'reason' => $grantData['reason'],
                'message' => "Gagal memberikan izin tulis tahun {$year} melalui management users.",
                'metadata' => [
                    'tahun' => $year,
                    'error' => $e->getMessage(),
                ],
                'http_status' => 500,
            ], $request);

            throw $e;
        }
    }

    /**
     * @return array{
     *     reason: string,
     *     reference_number: ?string,
     *     reference_date: ?string,
     *     valid_until: ?Carbon,
     *     grant_notes: ?string
     * }
     */
    private function validatedGrantData(Request $request): array
    {
        $request->merge([
            'reason' => $this->nullableTrimmedString($request, 'reason'),
            'reference_number' => $this->nullableTrimmedString($request, 'reference_number'),
            'reference_date' => $this->nullableTrimmedString($request, 'reference_date'),
            'valid_until' => $this->nullableTrimmedString($request, 'valid_until'),
            'grant_notes' => $this->nullableTrimmedString($request, 'grant_notes'),
        ]);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'reference_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'grant_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'reason' => (string) $validated['reason'],
            'reference_number' => $validated['reference_number'] ?? null,
            'reference_date' => $validated['reference_date'] ?? null,
            'valid_until' => filled($validated['valid_until'] ?? null)
                ? Carbon::parse((string) $validated['valid_until'])->endOfDay()
                : null,
            'grant_notes' => $validated['grant_notes'] ?? null,
        ];
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

    private function nullableTrimmedString(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key));

        return $value === '' ? null : $value;
    }
}
