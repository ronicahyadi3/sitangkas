<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementAuditController extends Controller
{
    public function __construct(
        private ActivePositionService $activePositionService,
        private UserManagementAccessService $userManagementAccessService
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->activePositionService->managementActor();
        $filters = $this->filters($request);

        $events = UserManagementAuditEvent::query()
            ->select([
                'id',
                'event_uuid',
                'actor_user_id',
                'actor_user_position_id',
                'target_user_id',
                'target_user_position_id',
                'event_type',
                'result',
                'resource_type',
                'resource_id',
                'reason',
                'message',
                'before_state',
                'after_state',
                'changed_fields',
                'metadata',
                'request_id',
                'route_name',
                'request_path',
                'http_method',
                'http_status',
                'ip_address',
                'user_agent',
                'occurred_at',
                'created_at',
            ])
            ->with([
                'actor:id,nama,nik,email',
                'targetUser:id,nama,nik,email',
                'actorPosition:id,user_id,jabatan_id,instansi_id,unit_kerja_id',
                'actorPosition.jabatan:id,nama,kode',
                'actorPosition.instansi:id,nama,nama_singkat',
                'actorPosition.unitKerja:id,nama,nama_singkat',
                'targetPosition:id,user_id,jabatan_id,instansi_id,unit_kerja_id',
                'targetPosition.jabatan:id,nama,kode',
                'targetPosition.instansi:id,nama,nama_singkat',
                'targetPosition.unitKerja:id,nama,nama_singkat',
            ])
            ->when(
                ! $this->userManagementAccessService->isFullAdmin($actor),
                fn (Builder $query): Builder => $this->applyScopedAuditVisibility($query, $actor)
            )
            ->when($filters['date_from'] !== null, fn (Builder $query): Builder => $query->where('occurred_at', '>=', $filters['date_from'].' 00:00:00'))
            ->when($filters['date_to'] !== null, fn (Builder $query): Builder => $query->where('occurred_at', '<=', $filters['date_to'].' 23:59:59'))
            ->when($filters['event_type'] !== null, fn (Builder $query): Builder => $query->where('event_type', $filters['event_type']))
            ->when($filters['result'] !== null, fn (Builder $query): Builder => $query->where('result', $filters['result']))
            ->when($filters['actor_user_id'] !== null, fn (Builder $query): Builder => $query->where('actor_user_id', $filters['actor_user_id']))
            ->when($filters['target_user_id'] !== null, fn (Builder $query): Builder => $query->where('target_user_id', $filters['target_user_id']))
            ->when($filters['resource_type'] !== null, fn (Builder $query): Builder => $query->where('resource_type', $filters['resource_type']))
            ->when($filters['ip_address'] !== null, fn (Builder $query): Builder => $query->where('ip_address', $filters['ip_address']))
            ->when($filters['search'] !== null, function (Builder $query) use ($filters): Builder {
                return $query->where(function (Builder $query) use ($filters): void {
                    $query->where('event_uuid', 'like', '%'.$filters['search'].'%')
                        ->orWhere('request_id', 'like', '%'.$filters['search'].'%')
                        ->orWhere('message', 'like', '%'.$filters['search'].'%')
                        ->orWhere('reason', 'like', '%'.$filters['search'].'%')
                        ->orWhere('route_name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('request_path', 'like', '%'.$filters['search'].'%');
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('users.audit-trail', [
            'events' => $events,
            'filters' => $filters,
            'eventTypes' => $this->eventTypes(),
            'resultTypes' => $this->resultTypes(),
            'resourceTypes' => $this->resourceTypes(),
            'scopeLabel' => $this->userManagementAccessService->context($actor)['scope_label'] ?? 'jabatan aktif Anda',
            'isFullAdmin' => $this->userManagementAccessService->isFullAdmin($actor),
        ]);
    }

    private function applyScopedAuditVisibility(Builder $query, ?UserPosition $actor): Builder
    {
        if (! $this->userManagementAccessService->isScopedAdmin($actor)) {
            return $query->whereKey([]);
        }

        $visibleUserIds = $this->userManagementAccessService
            ->applyVisibleUsersScope(User::query()->select('id'), $actor);

        $visiblePositionIds = $this->userManagementAccessService
            ->applyVisiblePositionsScope(UserPosition::query()->select('id'), $actor);

        return $query->where(function (Builder $query) use ($actor, $visibleUserIds, $visiblePositionIds): void {
            $query->where('actor_user_id', auth()->id())
                ->orWhere('actor_user_position_id', $actor?->getKey())
                ->orWhereIn('target_user_id', $visibleUserIds)
                ->orWhereIn('target_user_position_id', $visiblePositionIds);
        });
    }

    /**
     * @return array{
     *     date_from: ?string,
     *     date_to: ?string,
     *     event_type: ?string,
     *     result: ?string,
     *     actor_user_id: ?int,
     *     target_user_id: ?int,
     *     resource_type: ?string,
     *     ip_address: ?string,
     *     search: ?string,
     *     per_page: int
     * }
     */
    private function filters(Request $request): array
    {
        return [
            'date_from' => $this->dateFilter($request, 'date_from'),
            'date_to' => $this->dateFilter($request, 'date_to'),
            'event_type' => $this->nullableFilter($request, 'event_type'),
            'result' => $this->nullableFilter($request, 'result'),
            'actor_user_id' => $this->integerFilter($request, 'actor_user_id'),
            'target_user_id' => $this->integerFilter($request, 'target_user_id'),
            'resource_type' => $this->nullableFilter($request, 'resource_type'),
            'ip_address' => $this->ipFilter($request),
            'search' => $this->nullableFilter($request, 'search'),
            'per_page' => $this->perPage($request),
        ];
    }

    private function nullableFilter(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key, ''));

        return $value === '' ? null : $value;
    }

    private function dateFilter(Request $request, string $key): ?string
    {
        $value = $this->nullableFilter($request, $key);

        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function integerFilter(Request $request, string $key): ?int
    {
        $value = $this->nullableFilter($request, $key);

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    private function ipFilter(Request $request): ?string
    {
        $value = $this->nullableFilter($request, 'ip_address');

        return $value !== null && filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 25);

        return in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 25;
    }

    /**
     * @return array<string, string>
     */
    private function eventTypes(): array
    {
        return [
            UserManagementAuditEvent::EVENT_USER_CREATED => 'User dibuat',
            UserManagementAuditEvent::EVENT_USER_UPDATED => 'User diperbarui',
            UserManagementAuditEvent::EVENT_USER_DELETED => 'User dihapus',
            UserManagementAuditEvent::EVENT_MODULE_ACCESS => 'Akses module',
            UserManagementAuditEvent::EVENT_POSITION_VIEWED => 'Posisi dilihat',
            UserManagementAuditEvent::EVENT_POSITION_CREATED => 'Posisi dibuat',
            UserManagementAuditEvent::EVENT_POSITION_UPDATED => 'Posisi diperbarui',
            UserManagementAuditEvent::EVENT_POSITION_ACTIVATED => 'Posisi diaktifkan',
            UserManagementAuditEvent::EVENT_POSITION_DEACTIVATED => 'Posisi dinonaktifkan',
            UserManagementAuditEvent::EVENT_POSITION_DELETED => 'Posisi dihapus',
            UserManagementAuditEvent::EVENT_YEAR_PERMISSION_GRANTED => 'Izin tahun diberikan',
            UserManagementAuditEvent::EVENT_YEAR_PERMISSION_REVOKED => 'Izin tahun dicabut',
            UserManagementAuditEvent::EVENT_SECURITY_FORCE_PASSWORD_CHANGE => 'Force password',
            UserManagementAuditEvent::EVENT_SECURITY_LOCK => 'Lock akun',
            UserManagementAuditEvent::EVENT_SECURITY_UNLOCK => 'Unlock akun',
            UserManagementAuditEvent::EVENT_SECURITY_MFA_RESET => 'Reset MFA',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resultTypes(): array
    {
        return [
            UserManagementAuditEvent::RESULT_SUCCESS => 'Success',
            UserManagementAuditEvent::RESULT_FAILED => 'Failed',
            UserManagementAuditEvent::RESULT_BLOCKED => 'Blocked',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function resourceTypes(): array
    {
        return [
            User::class => 'User',
            UserPosition::class => 'User Position',
            'management_users' => 'Management Users',
            'management_users_options' => 'Opsi Management Users',
        ];
    }
}
