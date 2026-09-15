<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\AnggaranKegiatan;
use App\Models\AnggaranKegiatanTemp;
use App\Models\UnitKerja;
use App\Services\User\ActivePositionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Rekening extends Controller
{
    public function subKegiatan(Request $request, ActivePositionService $activePosition)
    {
        $unitKerjaId = $this->resolveActiveUnitId($activePosition);
        $categoryGu = $request->boolean('categoryGu', true);
        Log::channel('module_document_data')->info('Rekening subKegiatan request', [
            'category_gu' => $categoryGu,
            'user_unit' => $unitKerjaId,
        ]);

        if (! $unitKerjaId) {
            Log::channel('module_document_data')->warning('Rekening subKegiatan failed: unit not found');

            return response()->json([
                'message' => 'Unit kerja tidak ditemukan',
            ], 403);
        }

        $targetUnit = $this->resolveTargetUnitId($unitKerjaId);

        $query = AnggaranKegiatanTemp::tahunAktif()
            ->join('unit_kerjas as uk', 'uk.id', '=', 'anggaran_kegiatan_temp.id_unit_kerja');

        if ($categoryGu) {
            $query->where(function ($q) use ($unitKerjaId) {
                $q->where('anggaran_kegiatan_temp.id_unit_kerja', $unitKerjaId)
                    ->orWhere('uk.skpd_id', $unitKerjaId);
            });
        } else {
            $query->where('anggaran_kegiatan_temp.id_unit_kerja', $targetUnit);
        }

        $data = $query
            ->select([
                'anggaran_kegiatan_temp.kode_sub_kegiatan as kode',
                'anggaran_kegiatan_temp.nama_sub_kegiatan as nama',
                'uk.nama as unit_kerja',
                'uk.id as unit_kerja_id',
            ])
            ->distinct()
            ->orderBy('anggaran_kegiatan_temp.kode_sub_kegiatan')
            ->get();
        Log::channel('module_document_data')->info('Rekening subKegiatan success', [
            'count' => $data->count(),
            'category_gu' => $categoryGu,
            'target_unit' => $categoryGu ? $unitKerjaId : $targetUnit,
        ]);

        return response()->json($data);
    }

    public function rekening(Request $request, ActivePositionService $activePosition)
    {
        Log::channel('module_document_data')->info('Rekening list request', [
            'sub_kegiatan_id' => $request->input('id'),
            'category_gu' => $request->boolean('categoryGu', false),
            'requested_unit' => $request->input('unit'),
        ]);

        if (! $request->filled('id')) {
            Log::channel('module_document_data')->warning('Rekening list failed: invalid sub kegiatan id');

            return response()->json([
                'message' => 'Sub kegiatan tidak valid',
            ], 422);
        }

        $userUnitId = $this->resolveActiveUnitId($activePosition);
        if (! $userUnitId) {
            Log::channel('module_document_data')->warning('Rekening list failed: user unit not found');

            return response()->json([
                'message' => 'Unit kerja tidak ditemukan',
            ], 403);
        }

        $isGU = $request->boolean('categoryGu', false);

        if ($isGU) {
            $unitId = (int) $request->input('unit');

            if ($unitId <= 0) {
                Log::channel('module_document_data')->warning('Rekening list failed: invalid requested unit', [
                    'requested_unit' => $request->input('unit'),
                ]);

                return response()->json([
                    'message' => 'Unit kerja tidak valid',
                ], 422);
            }

            if (! $this->isAllowedGuUnit($unitId, $userUnitId)) {
                Log::channel('module_document_data')->warning('Rekening forbidden unit access', [
                    'requested_unit' => $unitId,
                    'user_unit' => $userUnitId,
                ]);

                return response()->json([
                    'message' => 'Anda tidak berwenang mengakses unit kerja ini',
                ], 403);
            }
        } else {
            $unitId = $this->resolveTargetUnitId($userUnitId);
        }

        $data = AnggaranKegiatanTemp::tahunAktif()
            ->where('kode_sub_kegiatan', $request->id)
            ->where('id_unit_kerja', $unitId)
            ->whereNull('deleted_at')
            ->select(
                'id_rekening as kode',
                'nama_rekening as uraian',
                'kode_rekening as rekening',
                'kode_sub_kegiatan as sub_kegiatan_id',
                'nama_sub_unit',
                'id_unit_kerja as unit_kerja_id'
            )
            ->orderBy('kode_rekening')
            ->get();
        Log::channel('module_document_data')->info('Rekening list success', [
            'sub_kegiatan_id' => $request->id,
            'unit_id' => $unitId,
            'count' => $data->count(),
        ]);

        return response()->json($data);
    }

    public function rekeningDetail(
        Request $request,
        ActivePositionService $activePosition,
    ) {
        Log::channel('module_document_data')->info('Rekening detail request', [
            'rekening_id' => $request->input('id'),
            'category_gu' => $request->boolean('categoryGu', false),
            'requested_unit' => $request->input('unit'),
        ]);

        if (! $request->filled('id')) {
            Log::channel('module_document_data')->warning('Rekening detail failed: invalid rekening id');

            return response()->json([
                'message' => 'Rekening tidak valid',
            ], 422);
        }

        $userUnitId = $this->resolveActiveUnitId($activePosition);
        $isGU = $request->boolean('categoryGu', false);

        if (! $userUnitId) {
            Log::channel('module_document_data')->warning('Rekening detail failed: user unit not found');

            return response()->json([
                'message' => 'Unit kerja tidak ditemukan',
            ], 404);
        }

        if ($isGU) {
            $unitId = (int) $request->input('unit');

            if ($unitId <= 0) {
                Log::channel('module_document_data')->warning('Rekening detail failed: invalid requested unit', [
                    'requested_unit' => $request->input('unit'),
                ]);

                return response()->json([
                    'message' => 'Unit kerja tidak valid',
                ], 422);
            }

            if (! $this->isAllowedGuUnit($unitId, $userUnitId)) {
                Log::channel('module_document_data')->warning('RekeningDetail forbidden unit access', [
                    'requested_unit' => $unitId,
                    'user_unit' => $userUnitId,
                    'rekening_id' => $request->id,
                ]);

                return response()->json([
                    'message' => 'Anda tidak berwenang mengakses unit kerja ini',
                ], 403);
            }
        } else {
            $unitId = $this->resolveTargetUnitId($userUnitId);
        }

        $temp = AnggaranKegiatanTemp::tahunAktif()
            ->where('id_unit_kerja', $unitId)
            ->where('id_rekening', $request->id)
            ->whereNull('deleted_at')
            ->select([
                'nama_kegiatan as kegiatan',
                'nama_sub_unit',
                'nama_sub_kegiatan as sub_kegiatan',
                'nama_sumber_dana as sumber_dana',
                'nama_rekening as rekening',
                'pagu',
            ])
            ->first();

        if (! $temp) {
            Log::channel('module_document_data')->warning('Rekening detail not found', [
                'rekening_id' => $request->id,
                'unit_id' => $unitId,
            ]);

            return response()->json([
                'message' => 'Data Rekening Tidak ditemukan',
            ], 404);
        }

        $totalNominal = AnggaranKegiatan::tahunAktif()
            ->where('id_unit_kerja', $unitId)
            ->where('id_rekening', $request->id)
            ->whereNull('deleted_at')
            ->sum('nominal');

        $sisaPagu = $temp->pagu - $totalNominal;
        Log::channel('module_document_data')->info('Rekening detail success', [
            'rekening_id' => $request->id,
            'unit_id' => $unitId,
            'pagu' => (float) $temp->pagu,
            'total_nominal' => (float) $totalNominal,
            'sisa_pagu' => (float) $sisaPagu,
        ]);

        return response()->json([
            'nama_sub_unit' => $temp->nama_sub_unit,
            'kegiatan' => $temp->kegiatan,
            'sub_kegiatan' => $temp->sub_kegiatan,
            'sumber_dana' => $temp->sumber_dana,
            'rekening' => $temp->rekening,
            'pagu' => (float) $temp->pagu,
            'total_nominal' => (float) $totalNominal,
            'sisa_pagu' => (float) $sisaPagu,
        ]);
    }

    private function resolveActiveUnitId(ActivePositionService $activePosition): ?int
    {
        $user = $activePosition->get();

        return $user?->unitKerja?->id;
    }

    private function resolveTargetUnitId(int $unitKerjaId): int
    {
        return $unitKerjaId === 16 ? 84 : $unitKerjaId;
    }

    private function isAllowedGuUnit(int $requestedUnitId, int $userUnitId): bool
    {
        return UnitKerja::query()
            ->where('id', $requestedUnitId)
            ->where(function ($q) use ($userUnitId) {
                $q->where('id', $userUnitId)
                    ->orWhere('skpd_id', $userUnitId);
            })
            ->exists();
    }
}
