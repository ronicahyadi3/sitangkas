<?php

namespace App\Http\Controllers\Users;

use App\Actions\UserManagement\ActivateManagedUserPosition;
use App\Actions\UserManagement\CreateManagedUserPosition;
use App\Actions\UserManagement\DeactivateManagedUserPosition;
use App\Actions\UserManagement\DeleteManagedUserPosition;
use App\Actions\UserManagement\GrantHistoricalYearAccess;
use App\Actions\UserManagement\RevokeHistoricalYearAccess;
use App\Actions\UserManagement\UpdateManagedUserPosition;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserPositionDeactivateRequest;
use App\Http\Requests\User\UserPositionStoreRequest;
use App\Models\Jabatan;
use App\Models\User;
use App\Models\UserManagementAuditEvent;
use App\Models\UserPosition;
use App\Models\UserPositionDocument;
use App\Models\UserPositionYearPermission;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use App\Services\User\UserManagementAuditLogger;
use App\Services\User\YearAccessService;
use App\Support\EncryptedId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserPositionController extends Controller
{
    public function __construct(
        protected ActivePositionService $activePositionService,
        protected UserManagementAccessService $userManagementAccessService,
        protected UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    // JSON daftar posisi user (untuk di-load di modal)
    public function index(
        Request $request,
        User $user,
        ActivePositionService $activePositionService,
        YearAccessService $yearAccessService
    ) {
        $viewer = $activePositionService->managementActor();

        if (! $this->userManagementAccessService->canViewUser($user, $viewer)) {
            return $this->positionDeniedResponse(
                $request,
                $user,
                null,
                UserManagementAuditEvent::EVENT_POSITION_VIEWED,
                'User ini tidak termasuk scope pengelolaan jabatan aktif Anda.',
                'unauthorized_scope'
            );
        }

        $selectedYear = $this->resolveManagedYear($request, $viewer, $yearAccessService);
        $isHistoricalYear = $yearAccessService->isHistoricalYear($selectedYear);
        $canManageHistoricalAccess = $this->canManageHistoricalYearAccess($viewer) && $isHistoricalYear;

        Log::channel('module_users')->debug('User positions list request', [
            'actor_id' => auth()->id(),
            'target_user_id' => $user->id,
            'selected_year' => $selectedYear,
        ]);

        $userPositions = $user->positions()
            ->with(['deactivatedBy:id,nama', 'jabatan', 'instansi', 'unitKerja', 'primaryDocument'])
            ->when(
                ! $this->userManagementAccessService->isFullAdmin($viewer),
                fn ($query) => $this->userManagementAccessService->applyVisiblePositionsScope($query, $viewer)
            )
            ->get();

        $permissions = UserPositionYearPermission::query()
            ->with(['grantedByUser:id,nama', 'lastUsedByUser:id,nama'])
            ->whereIn('user_position_id', $userPositions->pluck('id'))
            ->where('tahun', $selectedYear)
            ->historicalWrite()
            ->active()
            ->currentlyEffective()
            ->get()
            ->keyBy('user_position_id');

        $positions = $userPositions->map(function ($p) use (
            $permissions,
            $selectedYear,
            $isHistoricalYear,
            $canManageHistoricalAccess,
        ) {
            $permission = $permissions->get($p->id);

            return $this->serializePosition(
                $p,
                $selectedYear,
                $isHistoricalYear,
                $canManageHistoricalAccess,
                $permission
            );
        });

        return response()->json(['ok' => true, 'data' => $positions]);
    }

    public function show(
        Request $request,
        User $user,
        UserPosition $position,
        ActivePositionService $activePositionService,
        YearAccessService $yearAccessService
    ) {
        $viewer = $activePositionService->managementActor();

        if ($position->user_id !== $user->id) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_POSITION_VIEWED,
                $request,
                $user,
                $position,
                'ownership_mismatch',
                'Posisi tidak dimiliki oleh user pada URL.',
                404
            );

            abort(404);
        }

        if (
            ! $this->userManagementAccessService->canViewUser($user, $viewer) ||
            ! $this->userManagementAccessService->canManagePosition($position, $viewer)
        ) {
            return $this->positionDeniedResponse(
                $request,
                $user,
                $position,
                UserManagementAuditEvent::EVENT_POSITION_VIEWED,
                'Posisi ini tidak termasuk scope pengelolaan jabatan aktif Anda.',
                'unauthorized_scope'
            );
        }

        $position->loadMissing(['deactivatedBy:id,nama', 'jabatan', 'instansi', 'unitKerja', 'user', 'primaryDocument']);

        $selectedYear = $this->resolveManagedYear($request, $viewer, $yearAccessService);
        $isHistoricalYear = $yearAccessService->isHistoricalYear($selectedYear);
        $canManageHistoricalAccess = $this->canManageHistoricalYearAccess($viewer) && $isHistoricalYear;

        $permission = UserPositionYearPermission::query()
            ->with(['grantedByUser:id,nama', 'lastUsedByUser:id,nama'])
            ->where('user_position_id', $position->id)
            ->where('tahun', $selectedYear)
            ->historicalWrite()
            ->active()
            ->currentlyEffective()
            ->first();

        return response()->json([
            'ok' => true,
            'data' => $this->serializePosition(
                $position,
                $selectedYear,
                $isHistoricalYear,
                $canManageHistoricalAccess,
                $permission
            ),
        ]);
    }

    public function store(UserPositionStoreRequest $request, User $user, CreateManagedUserPosition $createManagedUserPosition)
    {
        $actor = $this->activePositionService->managementActor();
        $data = $request->validated();

        if (! $this->userManagementAccessService->canAttachPositionToUser(
            $user,
            $actor,
            (int) $data['jabatan_id'],
            isset($data['instansi_id']) ? (int) $data['instansi_id'] : null,
            isset($data['unit_kerja_id']) ? (int) $data['unit_kerja_id'] : null
        )) {
            return $this->positionDeniedResponse(
                $request,
                $user,
                null,
                UserManagementAuditEvent::EVENT_POSITION_CREATED,
                'Posisi yang dipilih tidak termasuk kewenangan jabatan aktif Anda.',
                'unauthorized_scope',
                [
                    'requested_jabatan_id' => isset($data['jabatan_id']) ? (int) $data['jabatan_id'] : null,
                    'requested_instansi_id' => isset($data['instansi_id']) ? (int) $data['instansi_id'] : null,
                    'requested_unit_kerja_id' => isset($data['unit_kerja_id']) ? (int) $data['unit_kerja_id'] : null,
                ]
            );
        }

        try {
            $position = $createManagedUserPosition->handle($request, $user, $actor);

            if ($request->ajax()) {
                return response()->json(['ok' => true, 'message' => 'Posisi ditambahkan.', 'id' => $position->getRouteKey()]);
            }

            return back()->with('status', 'Posisi ditambahkan.');
        } catch (AuthorizationException $e) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_POSITION_CREATED,
                $request,
                $user,
                null,
                'unauthorized_scope',
                $e->getMessage()
            );

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Gagal menambahkan posisi.'], 500);
            }

            return back()->withErrors(['error' => 'Gagal menambahkan posisi.'])->withInput();
        }
    }

    public function update(
        UserPositionStoreRequest $request,
        User $user,
        UserPosition $position,
        UpdateManagedUserPosition $updateManagedUserPosition
    ) {
        $actor = $this->activePositionService->managementActor();

        if ($position->user_id !== $user->id) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_POSITION_UPDATED,
                $request,
                $user,
                $position,
                'ownership_mismatch',
                'Posisi tidak dimiliki oleh user pada URL.',
                404
            );

            abort(404);
        }

        if (
            ! $this->userManagementAccessService->canViewUser($user, $actor) ||
            ! $this->userManagementAccessService->canManagePosition($position, $actor)
        ) {
            return $this->positionDeniedResponse(
                $request,
                $user,
                $position,
                UserManagementAuditEvent::EVENT_POSITION_UPDATED,
                'Posisi ini tidak termasuk scope pengelolaan jabatan aktif Anda.',
                'unauthorized_scope'
            );
        }

        try {
            $updateManagedUserPosition->handle($request, $user, $position, $actor);

            return response()->json([
                'ok' => true,
                'message' => 'Posisi berhasil diperbarui.',
            ]);
        } catch (AuthorizationException $e) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_POSITION_UPDATED,
                $request,
                $user,
                $position,
                'unauthorized_scope',
                $e->getMessage()
            );

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal memperbarui posisi.',
            ], 500);
        }
    }

    public function activate(
        Request $request,
        User $user,
        UserPosition $position,
        ActivateManagedUserPosition $activateManagedUserPosition
    ) {
        $actor = $this->activePositionService->managementActor();

        if ($position->user_id !== $user->id) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_POSITION_ACTIVATED,
                $request,
                $user,
                $position,
                'ownership_mismatch',
                'Posisi tidak dimiliki oleh user pada URL.',
                404
            );

            Log::channel('module_users')->warning('User position activate blocked: ownership mismatch', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
            ]);
            abort(404);
        }

        if (
            ! $this->userManagementAccessService->canViewUser($user, $actor) ||
            ! $this->userManagementAccessService->canManagePosition($position, $actor)
        ) {
            return $this->positionDeniedResponse(
                $request,
                $user,
                $position,
                UserManagementAuditEvent::EVENT_POSITION_ACTIVATED,
                'Posisi ini tidak termasuk scope pengelolaan jabatan aktif Anda.',
                'unauthorized_scope'
            );
        }

        try {
            $activateManagedUserPosition->handle($request, $user, $position, $actor);

            if ($request->ajax()) {
                return response()->json(['ok' => true, 'message' => 'Posisi aktif diubah.']);
            }

            return back()->with('status', 'Posisi aktif diubah.');
        } catch (Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Gagal mengubah posisi aktif.'], 500);
            }

            return back()->withErrors(['error' => 'Gagal mengubah posisi aktif.']);
        }
    }

    public function deactivate(
        UserPositionDeactivateRequest $request,
        User $user,
        UserPosition $position,
        DeactivateManagedUserPosition $deactivateManagedUserPosition
    ) {
        $actor = $this->activePositionService->managementActor();

        if ($position->user_id !== $user->id) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_POSITION_DEACTIVATED,
                $request,
                $user,
                $position,
                'ownership_mismatch',
                'Posisi tidak dimiliki oleh user pada URL.',
                404
            );

            Log::channel('module_users')->warning('User position deactivate blocked: ownership mismatch', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
            ]);
            abort(404);
        }

        if (
            ! $this->userManagementAccessService->canViewUser($user, $actor) ||
            ! $this->userManagementAccessService->canManagePosition($position, $actor)
        ) {
            return $this->positionDeniedResponse(
                $request,
                $user,
                $position,
                UserManagementAuditEvent::EVENT_POSITION_DEACTIVATED,
                'Posisi ini tidak termasuk scope pengelolaan jabatan aktif Anda.',
                'unauthorized_scope'
            );
        }

        if (! $position->is_active) {
            return response()->json([
                'ok' => false,
                'message' => 'Posisi ini sudah nonaktif.',
            ], 422);
        }

        try {
            $deactivateManagedUserPosition->handle($request, $user, $position, $actor);

            return response()->json([
                'ok' => true,
                'message' => 'Posisi berhasil dinonaktifkan.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal menonaktifkan posisi.',
            ], 500);
        }
    }

    public function destroy(
        Request $request,
        User $user,
        UserPosition $position,
        DeleteManagedUserPosition $deleteManagedUserPosition
    ) {
        $actor = $this->activePositionService->managementActor();

        if ($position->user_id !== $user->id) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_POSITION_DELETED,
                $request,
                $user,
                $position,
                'ownership_mismatch',
                'Posisi tidak dimiliki oleh user pada URL.',
                404
            );

            Log::channel('module_users')->warning('User position delete blocked: ownership mismatch', [
                'actor_id' => auth()->id(),
                'target_user_id' => $user->id,
                'position_id' => $position->id,
            ]);
            abort(404);
        }

        if (
            ! $this->userManagementAccessService->canViewUser($user, $actor) ||
            ! $this->userManagementAccessService->canManagePosition($position, $actor)
        ) {
            return $this->positionDeniedResponse(
                $request,
                $user,
                $position,
                UserManagementAuditEvent::EVENT_POSITION_DELETED,
                'Posisi ini tidak termasuk scope pengelolaan jabatan aktif Anda.',
                'unauthorized_scope'
            );
        }

        try {
            $deleteManagedUserPosition->handle($request, $user, $position, $actor);

            if ($request->ajax()) {
                return response()->json(['ok' => true, 'message' => 'Posisi dihapus.']);
            }

            return back()->with('status', 'Posisi dihapus.');
        } catch (Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Gagal menghapus posisi.'], 500);
            }

            return back()->withErrors(['error' => 'Gagal menghapus posisi.']);
        }
    }

    public function grantHistoricalWriteAccess(
        Request $request,
        User $user,
        UserPosition $position,
        ActivePositionService $activePositionService,
        YearAccessService $yearAccessService,
        GrantHistoricalYearAccess $grantHistoricalYearAccess
    ) {
        $actor = $activePositionService->managementActor();

        if ($position->user_id !== $user->id) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_YEAR_PERMISSION_GRANTED,
                $request,
                $user,
                $position,
                'ownership_mismatch',
                'Posisi tidak dimiliki oleh user pada URL.',
                404
            );

            abort(404);
        }

        $year = (int) $request->integer('tahun', $yearAccessService->selectedYear());

        if (! $this->canManageHistoricalYearAccess($actor)) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_YEAR_PERMISSION_GRANTED,
                $request,
                $user,
                $position,
                'admin_super_required',
                'Grant izin tulis histori ditolak karena membutuhkan Admin Super.',
                403,
                ['requested_year' => $year]
            );

            abort(403, 'Hanya Admin Super yang dapat mengatur izin tulis histori.');
        }

        if (! $yearAccessService->isHistoricalYear($year)) {
            return response()->json([
                'ok' => false,
                'message' => 'Izin khusus hanya diperlukan untuk tahun historis.',
            ], 422);
        }

        try {
            $grantHistoricalYearAccess->handle($request, $user, $position, $actor, $year);

            return response()->json([
                'ok' => true,
                'message' => "Izin tulis tahun {$year} diberikan untuk posisi ini.",
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal memberikan izin tulis histori.',
            ], 500);
        }
    }

    public function revokeHistoricalWriteAccess(
        Request $request,
        User $user,
        UserPosition $position,
        ActivePositionService $activePositionService,
        YearAccessService $yearAccessService,
        RevokeHistoricalYearAccess $revokeHistoricalYearAccess
    ) {
        $actor = $activePositionService->managementActor();

        if ($position->user_id !== $user->id) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_YEAR_PERMISSION_REVOKED,
                $request,
                $user,
                $position,
                'ownership_mismatch',
                'Posisi tidak dimiliki oleh user pada URL.',
                404
            );

            abort(404);
        }

        $year = (int) $request->integer('tahun', $yearAccessService->selectedYear());

        if (! $this->canManageHistoricalYearAccess($actor)) {
            $this->logBlockedPosition(
                UserManagementAuditEvent::EVENT_YEAR_PERMISSION_REVOKED,
                $request,
                $user,
                $position,
                'admin_super_required',
                'Revoke izin tulis histori ditolak karena membutuhkan Admin Super.',
                403,
                ['requested_year' => $year]
            );

            abort(403, 'Hanya Admin Super yang dapat mengatur izin tulis histori.');
        }

        if (! $yearAccessService->isHistoricalYear($year)) {
            return response()->json([
                'ok' => false,
                'message' => 'Izin khusus hanya berlaku untuk tahun historis.',
            ], 422);
        }

        try {
            $revokeHistoricalYearAccess->handle($request, $user, $position, $actor, $year);

            return response()->json([
                'ok' => true,
                'message' => "Izin tulis tahun {$year} dicabut dari posisi ini.",
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal mencabut izin tulis histori.',
            ], 500);
        }
    }

    private function canManageHistoricalYearAccess(?UserPosition $actor): bool
    {
        if (! $actor) {
            return false;
        }

        $originalJabatanId = (int) ($actor->getOriginal('jabatan_id') ?: $actor->jabatan_id);
        $jabatanCode = Jabatan::query()->whereKey($originalJabatanId)->value('kode')
            ?: config("position_rules.legacy.jabatan_id_to_code.{$originalJabatanId}");

        return is_string($jabatanCode)
            && in_array($jabatanCode, config('position_rules.admin_super_jabatan_codes', []), true);
    }

    private function resolveManagedYear(
        Request $request,
        ?UserPosition $actor,
        YearAccessService $yearAccessService
    ): int {
        $fallbackYear = $yearAccessService->selectedYear();

        if (! $this->canManageHistoricalYearAccess($actor)) {
            return $fallbackYear;
        }

        $requestedYear = (int) $request->integer('tahun', $fallbackYear);

        if ($requestedYear < 2000 || $requestedYear > 2100) {
            return $fallbackYear;
        }

        return $requestedYear;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function positionDeniedResponse(
        Request $request,
        User $user,
        ?UserPosition $position,
        string $eventType,
        string $message,
        string $reasonCode,
        array $metadata = []
    ) {
        $this->logBlockedPosition($eventType, $request, $user, $position, $reasonCode, $message, 403, $metadata);

        return response()->json([
            'ok' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function logBlockedPosition(
        string $eventType,
        Request $request,
        User $user,
        ?UserPosition $position,
        string $reasonCode,
        string $message,
        int $httpStatus = 403,
        array $metadata = []
    ): void {
        $actor = $this->activePositionService->managementActor();

        $this->userManagementAuditLogger->blocked($eventType, [
            'actor_user' => $request->user(),
            'actor_position' => $actor,
            'target_user' => $user,
            'target_position' => $position,
            'resource_type' => $position instanceof UserPosition ? UserPosition::class : User::class,
            'resource_id' => $position instanceof UserPosition ? $position->getKey() : $user->getKey(),
            'before_state' => $position instanceof UserPosition
                ? $this->userManagementAuditLogger->positionSnapshot($position)
                : $this->userManagementAuditLogger->userSnapshot($user),
            'reason_code' => $reasonCode,
            'reason' => $message,
            'message' => $message,
            'http_status' => $httpStatus,
            'metadata' => [
                'requested_action' => $eventType,
                'actor_is_full_admin' => $this->userManagementAccessService->isFullAdmin($actor),
                'actor_can_view_target_user' => $this->userManagementAccessService->canViewUser($user, $actor),
                'actor_can_manage_target_position' => $position instanceof UserPosition
                    && $this->userManagementAccessService->canManagePosition($position, $actor),
                ...$metadata,
            ],
        ], $request);
    }

    private function serializePosition(
        UserPosition $position,
        int $selectedYear,
        bool $isHistoricalYear,
        bool $canManageHistoricalAccess,
        ?UserPositionYearPermission $permission
    ): array {
        $hasHistoricalAccess = (bool) $permission;
        $primaryDocument = $position->primaryDocument;

        return [
            'id_enc' => $position->getRouteKey(),
            'position_id' => $position->id,
            'jabatan' => $position->jabatan?->nama ?? '-',
            'jabatan_id' => EncryptedId::encode($position->jabatan_id),
            'instansi' => $position->instansi?->nama ?? '-',
            'instansi_id' => $position->instansi_id ? EncryptedId::encode($position->instansi_id) : null,
            'unit' => $position->unitKerja?->nama ?? '-',
            'unit_kerja_id' => $position->unit_kerja_id ? EncryptedId::encode($position->unit_kerja_id) : null,
            'is_active' => (bool) $position->is_active,
            'started_at' => $position->started_at ? Carbon::parse($position->started_at)->format('Y-m-d') : null,
            'ended_at' => $position->ended_at ? Carbon::parse($position->ended_at)->format('Y-m-d') : null,
            'deactivated_at' => $position->deactivated_at?->format('Y-m-d H:i'),
            'deactivated_by' => $position->deactivatedBy?->nama,
            'deactivation_reason' => $position->deactivation_reason,
            'notes' => $position->notes,
            'file_url' => $this->positionDocumentUrl($primaryDocument),
            'file_name' => $primaryDocument?->original_name,
            'document_type' => $primaryDocument?->document_type,
            'document_type_label' => $primaryDocument instanceof UserPositionDocument
                ? $this->documentTypeLabel($primaryDocument->document_type)
                : null,
            'document_number' => $primaryDocument?->document_number,
            'document_date' => $primaryDocument?->document_date?->format('Y-m-d'),
            'issued_by' => $primaryDocument?->issued_by,
            'show_url' => route('users.positions.show', [$position->user, $position]),
            'update_url' => route('users.positions.update', [$position->user, $position]),
            'activate_url' => route('users.positions.activate', [$position->user, $position]),
            'deactivate_url' => route('users.positions.deactivate', [$position->user, $position]),
            'destroy_url' => route('users.positions.destroy', [$position->user, $position]),
            'historical_year' => $selectedYear,
            'historical_access_granted' => $hasHistoricalAccess,
            'historical_access_label' => ! $isHistoricalYear
                ? 'Tahun berjalan selalu mode tulis.'
                : ($hasHistoricalAccess ? "Izin tulis {$selectedYear} aktif." : "Masih mode lihat saja untuk {$selectedYear}."),
            'historical_access_audit' => $this->buildHistoricalAccessAuditText($permission),
            'can_manage_historical_access' => $canManageHistoricalAccess,
            'grant_historical_access_url' => route('users.positions.year-access.grant', [$position->user, $position]),
            'revoke_historical_access_url' => route('users.positions.year-access.revoke', [$position->user, $position]),
        ];
    }

    private function positionDocumentUrl(?UserPositionDocument $document): ?string
    {
        if (! $document instanceof UserPositionDocument || $document->storage_disk !== 'public') {
            return null;
        }

        return Storage::disk('public')->url($document->file_path);
    }

    private function documentTypeLabel(?string $documentType): string
    {
        return UserPositionDocument::typeOptions()[$documentType ?? ''] ?? 'Dokumen SK';
    }

    private function buildHistoricalAccessAuditText(?UserPositionYearPermission $permission): ?string
    {
        if (! $permission) {
            return null;
        }

        $parts = [];

        if ($permission->granted_at) {
            $grantedBy = $permission->grantedByUser?->nama ?: 'Admin Super';
            $parts[] = 'Diberikan oleh '.$grantedBy.' pada '.$permission->granted_at->format('d M Y H:i');
        }

        if (filled($permission->reason)) {
            $parts[] = 'Alasan: '.$permission->reason;
        }

        if (filled($permission->reference_number) || $permission->reference_date) {
            $referenceText = filled($permission->reference_number)
                ? 'Referensi: '.$permission->reference_number
                : 'Referensi';

            if ($permission->reference_date) {
                $referenceText .= ' tanggal '.$permission->reference_date->format('d M Y');
            }

            $parts[] = $referenceText.'.';
        }

        if ($permission->valid_until) {
            $parts[] = 'Berlaku sampai '.$permission->valid_until->format('d M Y H:i').'.';
        }

        if (filled($permission->grant_notes)) {
            $parts[] = 'Catatan grant: '.$permission->grant_notes;
        }

        if ($permission->last_used_at) {
            $lastUsedBy = $permission->lastUsedByUser?->nama ?: 'pengguna aktif';
            $count = max(0, (int) $permission->usage_count);

            $parts[] = 'Terakhir dipakai oleh '.$lastUsedBy.' pada '.$permission->last_used_at->format('d M Y H:i').' untuk aksi tulis historis'.($count > 0 ? " ({$count}x penggunaan)." : '.');
        }

        return empty($parts) ? null : implode(' ', $parts);
    }
}
