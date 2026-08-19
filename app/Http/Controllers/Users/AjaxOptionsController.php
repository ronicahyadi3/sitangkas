<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\UserManagementAuditEvent;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionScopeOptionsService;
use App\Services\User\UserManagementAccessService;
use App\Services\User\UserManagementAuditLogger;
use App\Support\EncryptedId;
use Illuminate\Http\Request;

class AjaxOptionsController extends Controller
{
    // GET /ajax/options/instansi?jabatan_id=ENC
    public function instansi(Request $request)
    {
        $isUsersManagementScope = $request->query('scope') === 'users-management';
        $enc = (string) $request->query('jabatan_id', '');
        if ($enc === '') {
            return response()->json(['ok' => true, 'allow_null' => false, 'options' => []]);
        }

        try {
            $jabatanId = EncryptedId::decode($enc);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Jabatan tidak valid.'], 422);
        }

        $jabatan = Jabatan::find($jabatanId);
        if (! $jabatan) {
            return response()->json(['ok' => false, 'message' => 'Jabatan tidak ditemukan.'], 404);
        }

        if ($isUsersManagementScope) {
            $actor = app(ActivePositionService::class)->managementActor();
            $access = app(UserManagementAccessService::class);

            if (! $access->canAccessModule($actor)) {
                $this->logUsersManagementOptionsBlocked($request, $actor, 'instansi_options');

                return response()->json(['ok' => false, 'message' => 'Akses manajemen pengguna tidak tersedia.'], 403);
            }

            if (! $access->isJabatanManageable($jabatanId, $actor)) {
                return response()->json(['ok' => true, 'allow_null' => false, 'options' => []]);
            }

            if (! $access->isFullAdmin($actor)) {
                $instansi = $actor?->instansi;
                $scopeOptions = app(PositionScopeOptionsService::class);

                if (
                    ! $instansi ||
                    ! $scopeOptions->isInstansiAllowedForRole((int) $jabatanId, (int) $instansi->id, $jabatan->nama)
                ) {
                    return response()->json([
                        'ok' => true,
                        'allow_null' => false,
                        'options' => [],
                    ]);
                }

                return response()->json([
                    'ok' => true,
                    'allow_null' => false,
                    'options' => [[
                        'id' => EncryptedId::encode($instansi->id),
                        'text' => $instansi->nama,
                    ]],
                ]);
            }

            $scopeOptions = app(PositionScopeOptionsService::class);
            $allowedNames = $scopeOptions->allowedInstansiNamesForRole((int) $jabatanId, $jabatan->nama);

            $instansis = Instansi::whereIn('nama', $allowedNames)->orderBy('nama')->get();

            $options = $instansis->map(fn ($i) => [
                'id' => EncryptedId::encode($i->id),
                'text' => $i->nama,
            ])->values();

            return response()->json([
                'ok' => true,
                'allow_null' => false,
                'options' => $options,
            ]);
        }

        $scopeOptions = app(PositionScopeOptionsService::class);
        $isAdminSuper = $scopeOptions->isAdminSuperRole($jabatan);
        $allowedNames = app(PositionScopeOptionsService::class)
            ->allowedInstansiNamesForRole((int) $jabatanId, $jabatan->nama);

        $instansis = Instansi::whereIn('nama', $allowedNames)->orderBy('nama')->get();

        $options = $instansis->map(fn ($i) => [
            'id' => EncryptedId::encode($i->id),
            'text' => $i->nama,
        ])->values();

        return response()->json([
            'ok' => true,
            'allow_null' => $isAdminSuper,
            'options' => $options,
        ]);
    }

    // GET /ajax/options/unit-kerja?instansi_id=ENC&jabatan_id=ENC
    public function unitKerja(Request $request)
    {
        $isUsersManagementScope = $request->query('scope') === 'users-management';
        $instansiEnc = (string) $request->query('instansi_id', '');
        $jabatanEnc = (string) $request->query('jabatan_id', '');

        if ($instansiEnc === '') {
            return response()->json(['ok' => true, 'options' => []]);
        }

        try {
            $instansiId = EncryptedId::decode($instansiEnc);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Instansi tidak valid.'], 422);
        }

        $instansi = Instansi::find($instansiId);
        if (! $instansi) {
            return response()->json(['ok' => false, 'message' => 'Instansi tidak ditemukan.'], 404);
        }

        if ($isUsersManagementScope) {
            $actor = app(ActivePositionService::class)->managementActor();
            $access = app(UserManagementAccessService::class);

            if (! $access->canAccessModule($actor)) {
                $this->logUsersManagementOptionsBlocked($request, $actor, 'unit_kerja_options');

                return response()->json(['ok' => false, 'message' => 'Akses manajemen pengguna tidak tersedia.'], 403);
            }

            $jabatanId = null;
            if ($jabatanEnc !== '') {
                try {
                    $jabatanId = EncryptedId::decode($jabatanEnc);
                } catch (\Throwable) {
                    $jabatanId = null;
                }
            }

            if (! $jabatanId || ! $access->isJabatanManageable($jabatanId, $actor)) {
                return response()->json(['ok' => true, 'options' => []]);
            }

            if (! $access->isFullAdmin($actor)) {
                $units = app(PositionScopeOptionsService::class)
                    ->unitQueryForRoleAndInstansi((int) $jabatanId, (int) ($actor?->instansi_id ?? 0))
                    ->whereIn('id', $access->managedUnitIds($actor))
                    ->orderBy('nama')
                    ->get();

                $options = $units->map(fn ($u) => [
                    'id' => EncryptedId::encode($u->id),
                    'text' => $u->nama,
                ])->values();

                return response()->json(['ok' => true, 'options' => $options]);
            }
        }

        $jabatanId = null;

        if ($jabatanEnc !== '') {
            try {
                $jabatanId = EncryptedId::decode($jabatanEnc);
            } catch (\Throwable $e) {
            }
        }

        $uq = $jabatanId
            ? app(PositionScopeOptionsService::class)->unitQueryForRoleAndInstansi((int) $jabatanId, (int) $instansi->id)
            : UnitKerja::where('instansi_id', $instansi->id);

        $units = $uq->orderBy('nama')->get();

        $options = $units->map(fn ($u) => [
            'id' => EncryptedId::encode($u->id),
            'text' => $u->nama,
        ])->values();

        return response()->json(['ok' => true, 'options' => $options]);
    }

    private function logUsersManagementOptionsBlocked(Request $request, mixed $actor, string $requestedOptions): void
    {
        app(UserManagementAuditLogger::class)->blocked(UserManagementAuditEvent::EVENT_MODULE_ACCESS, [
            'actor_user' => $request->user(),
            'actor_position' => $actor,
            'resource_type' => 'management_users_options',
            'resource_id' => $requestedOptions,
            'reason_code' => 'module_access_denied',
            'reason' => 'Akses opsi Management Users ditolak.',
            'message' => 'Akses opsi Management Users ditolak.',
            'metadata' => [
                'requested_options' => $requestedOptions,
                'scope' => $request->query('scope'),
            ],
        ], $request);
    }
}
