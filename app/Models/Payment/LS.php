<?php

declare(strict_types=1);

namespace App\Models\Payment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LS
{
    public static function rootQueryAlias(string $alias, ?int $tahun = null)
    {
        $tahun = $tahun ?? (int) (session('tahun_aktif') ?? date('Y'));

        return DB::table("document as {$alias}")
            ->whereNull("{$alias}.deleted_at")
            ->whereBetween("{$alias}.created_at", [
                Carbon::create($tahun)->startOfYear(),
                Carbon::create($tahun)->endOfYear(),
            ]);
    }

    public static function rootQuery(?int $tahun = null)
    {
        return self::rootQueryAlias('document', $tahun);
    }

    public static function joinSpp($query, bool $joinUnitKerja = true)
    {
        $method = $joinUnitKerja ? 'join' : 'leftJoin';

        return $query
            ->where([
                ['document.src_type', '=', 'SPP'],
                ['document.payment_type', '=', 'LS'],
            ])
            ->{$method}(
                'unit_kerjas as unit_kerja_spp',
                'unit_kerja_spp.id',
                '=',
                'document.id_unit_kerja'
            )
            ->addSelect([
                'document.id',
                'document.nomor',
                'document.uraian',
                'document.nominal',
                'document.id_unit_kerja',
                'document.denied_billing_at',
                'document.finished_at',
                'document.src_name as src_name_spp',
                'document.status as status_spp',
                'document.submit as submit_spp',
                'document.verify as verify_spp',
                'document.payment_type as payment_type_spp',
                'document.src_type as src_type_spp',
                'document.assigned_to as assigned_to_spp',
                'document.rejected_by as rejected_by_spp',
                'document.notes as notes_spp',
                'document.updated_at as updated_at_spp',
                'document.created_at as created_at_spp',

                'unit_kerja_spp.nama as unit_kerja_spp',
            ]);
    }

    public static function joinSp($query, bool $join = true)
    {
        $method = $join ? 'join' : 'leftJoin';

        return $query
            ->{$method}('document as sp', function ($q) {
                $q->on('sp.reference_id', '=', 'document.id')
                    ->where('sp.src_type', 'SP')
                    ->whereNull('sp.deleted_at');
            })
            ->addSelect([
                'sp.id as id_sp',
                'sp.submit as submit_sp',
                'sp.status as status_sp',
                'sp.src_name as src_name_sp',
                'sp.notes as notes_sp',
                'sp.rejected_by as rejected_by_sp',
                'sp.verify as verify_sp',
                'sp.assigned_to as assigned_to_sp',
                'sp.reference_id as reference_id_sp',

                DB::raw("CASE WHEN sp.submit LIKE '%5%' THEN 1 ELSE 0 END AS sp_submit_has_5"),
                DB::raw("CASE WHEN sp.submit LIKE '%6%' THEN 1 ELSE 0 END AS sp_submit_has_6"),
                DB::raw("CASE WHEN sp.submit LIKE '%7%' THEN 1 ELSE 0 END AS sp_submit_has_7"),
                DB::raw("CASE WHEN sp.status LIKE '%7%' THEN 1 ELSE 0 END AS sp_status_has_7"),
            ]);
    }

    public static function joinSpm($query, bool $join = true)
    {
        $method = $join ? 'join' : 'leftJoin';

        return $query
            ->{$method}('document as spm', function ($q) {
                $q->on('spm.reference_id', '=', 'document.id')
                    ->where('spm.src_type', 'SPM')
                    ->whereNull('spm.deleted_at');
            })
            ->{$method}(
                'unit_kerjas as unit_kerjas_spm',
                'unit_kerjas_spm.id',
                '=',
                'spm.id_unit_kerja'
            )
            ->addSelect([
                'spm.id as id_spm',
                'spm.nomor as nomor_spm',
                'spm.submit as submit_spm',
                'spm.status as status_spm',
                'spm.verify as verify_spm',
                'spm.src_name as src_name_spm',
                'spm.created_at as created_at_spm',
                'spm.updated_at as updated_at_spm',
                'spm.rejected_by as rejected_by_spm',
                'unit_kerjas_spm.nama as unit_kerja_spm',

                DB::raw("CASE WHEN spm.status LIKE '%5%' THEN 1 ELSE 0 END AS spm_status_has_5"),
                DB::raw("CASE WHEN spm.status LIKE '%6%' THEN 1 ELSE 0 END AS spm_status_has_6"),
            ]);
    }

    public static function joinSptjm($query, bool $join = true)
    {
        $method = $join ? 'join' : 'leftJoin';

        return $query
            ->{$method}('document as sptjm', function ($q) {
                $q->on('sptjm.reference_id', '=', 'document.id')
                    ->where('sptjm.src_type', 'SPTJM')
                    ->whereNull('sptjm.deleted_at');
            })
            ->addSelect([
                'sptjm.id as id_sptjm',
                'sptjm.nomor as nomor_sptjm',
                'sptjm.submit as submit_sptjm',
                'sptjm.status as status_sptjm',
                'sptjm.verify as verify_sptjm',
                'sptjm.src_name as src_name_sptjm',

                DB::raw("CASE WHEN sptjm.status LIKE '%5%' THEN 1 ELSE 0 END AS sptjm_status_has_5"),
                DB::raw("CASE WHEN sptjm.status LIKE '%6%' THEN 1 ELSE 0 END AS sptjm_status_has_6"),
            ]);
    }

    public static function joinSpPengajuan($query, bool $join = true)
    {
        $method = $join ? 'join' : 'leftJoin';

        return $query
            ->{$method}('document as sp_pengajuan', function ($q) {
                $q->on('sp_pengajuan.reference_id', '=', 'document.id')
                    ->where('sp_pengajuan.src_type', 'SP_PENGAJUAN')
                    ->whereNull('sp_pengajuan.deleted_at');
            })
            ->addSelect([
                'sp_pengajuan.id as id_sp_pengajuan',
                'sp_pengajuan.nomor as nomor_sp_pengajuan',
                'sp_pengajuan.submit as submit_sp_pengajuan',
                'sp_pengajuan.status as status_sp_pengajuan',
                'sp_pengajuan.verify as verify_sp_pengajuan',
                'sp_pengajuan.src_name as src_name_sp_pengajuan',
                'sp_pengajuan.created_at as created_at_sp_pengajuan',
                'sp_pengajuan.updated_at as updated_at_sp_pengajuan',
            ]);
    }

    public static function withSpm($query, bool $join = true)
    {
        $method = $join ? 'join' : 'leftJoin';

        return $query
            ->{$method}(
                'unit_kerjas',
                'unit_kerjas.id',
                '=',
                'document.id_unit_kerja'
            )
            ->where('document.src_type', 'SPM')
            ->where('document.payment_type', 'LS')
            ->select('document.*', 'unit_kerjas.nama as unit_kerja');
    }

    public static function withSp2d($query, bool $join = true)
    {
        $method = $join ? 'join' : 'leftJoin';

        return $query
            ->{$method}(
                'unit_kerjas as unit_kerjas_sp2d',
                'unit_kerjas_sp2d.id',
                '=',
                'document.id_unit_kerja'
            )
            ->where('document.src_type', 'SP2D')
            ->where('document.payment_type', 'LS')
            ->whereNotNull('document.users_to')
            ->select('document.*', 'unit_kerjas_sp2d.nama as unit_kerja');
    }

    public static function joinSp2d($query, bool $join = true)
    {
        $method = $join ? 'join' : 'leftJoin';

        return $query
            ->{$method}(
                'unit_kerjas as unit_kerjas_sp2d',
                'unit_kerjas_sp2d.id',
                '=',
                'document.id_unit_kerja'
            )
            ->{$method}('document as sp2d', function ($q) {
                $q->on('sp2d.reference_id', '=', 'document.id')
                    ->where('sp2d.src_type', 'SP2D')
                    ->whereNull('sp2d.deleted_at');
            })
            ->addSelect([
                'sp2d.id as id_sp2d',
                'sp2d.nomor as nomor_sp2d',
                'sp2d.submit as submit_sp2d',
                'sp2d.status as status_sp2d',
                'sp2d.verify as verify_sp2d',
                'sp2d.src_name as src_name_sp2d',
                'sp2d.created_at as created_at_sp2d',
                'sp2d.updated_at as updated_at_sp2d',
                'unit_kerjas_sp2d.nama as unit_kerja_sp2d',
            ]);
    }

    public static function spmJsonQuery(int $tahun)
    {
        return DB::table('document as spm')
            ->whereNull('spm.deleted_at')
            ->where('spm.src_type', 'SPM')
            ->where('spm.payment_type', 'LS')
            ->whereBetween('spm.created_at', [
                Carbon::create($tahun)->startOfYear(),
                Carbon::create($tahun)->endOfYear(),
            ])
            ->join('unit_kerjas as unit_kerjas_spm', 'unit_kerjas_spm.id', '=', 'spm.id_unit_kerja')
            ->leftJoin('document', function ($join) {
                $join->on('document.id', '=', 'spm.reference_id')
                    ->where('document.src_type', 'SPP')
                    ->where('document.payment_type', 'LS')
                    ->whereNull('document.deleted_at');
            })
            ->leftJoin('unit_kerjas as unit_kerja_spp', 'unit_kerja_spp.id', '=', 'document.id_unit_kerja')
            ->leftJoin('document as sp', function ($join) {
                $join->on('sp.reference_id', '=', 'spm.reference_id')
                    ->where('sp.src_type', 'SP')
                    ->where('sp.payment_type', 'LS')
                    ->whereNull('sp.deleted_at');
            })
            ->leftJoin('document as sptjm', function ($join) {
                $join->on('sptjm.reference_id', '=', 'spm.reference_id')
                    ->where('sptjm.src_type', 'SPTJM')
                    ->where('sptjm.payment_type', 'LS')
                    ->whereNull('sptjm.deleted_at');
            })
            ->leftJoin('document as sp_pengajuan', function ($join) {
                $join->on('sp_pengajuan.reference_id', '=', 'spm.reference_id')
                    ->where('sp_pengajuan.src_type', 'SP_PENGAJUAN')
                    ->where('sp_pengajuan.payment_type', 'LS')
                    ->whereNull('sp_pengajuan.deleted_at');
            })
            ->select(self::spmJsonSelectColumns())
            ->orderByDesc('spm.created_at');
    }

    public static function applySpmJsonScope($query, int $jabatanId, ?int $unitKerja)
    {
        $assignedExpr = "REPLACE(COALESCE(sp.assigned_to,''), ' ', '')";
        $submitExpr = "REPLACE(COALESCE(sp.submit,''), ' ', '')";

        return $query
            ->when($jabatanId === 4, function ($q) use ($assignedExpr) {
                $q->whereRaw("FIND_IN_SET('4', {$assignedExpr})");
            })
            ->when(in_array($jabatanId, [5, 6], true), function ($q) use ($jabatanId, $unitKerja, $assignedExpr, $submitExpr) {
                $q->where('document.id_unit_kerja', $unitKerja)
                    ->where(function ($scope) use ($jabatanId, $assignedExpr, $submitExpr) {
                        $scope->whereRaw("FIND_IN_SET(?, {$assignedExpr})", [(string) $jabatanId])
                            ->orWhereRaw("FIND_IN_SET(?, {$submitExpr})", [(string) $jabatanId]);
                    });
            })
            ->when($jabatanId === 7, function ($q) use ($unitKerja) {
                $q->where(function ($scope) use ($unitKerja) {
                    $scope->where('spm.id_unit_kerja', $unitKerja)
                        ->orWhere('unit_kerjas_spm.skpd_id', $unitKerja);
                })->where('document.verify', 1);
            })
            ->when(! in_array($jabatanId, [1, 4, 5, 6, 7, 8, 9, 10, 13], true), fn ($q) => $q->whereRaw('1 = 0'));
    }

    private static function spmJsonSelectColumns(): array
    {
        return [
            'document.id',
            'document.nomor',
            'document.uraian',
            'document.nominal',
            'document.id_unit_kerja',
            'document.denied_billing_at',
            'document.finished_at',
            'document.src_name as src_name_spp',
            'document.status as status_spp',
            'document.submit as submit_spp',
            'document.verify as verify_spp',
            'document.payment_type as payment_type_spp',
            'document.src_type as src_type_spp',
            'document.assigned_to as assigned_to_spp',
            'document.rejected_by as rejected_by_spp',
            'document.notes as notes_spp',
            'document.updated_at as updated_at_spp',
            'document.created_at as created_at_spp',
            'unit_kerja_spp.nama as unit_kerja_spp',
            'unit_kerja_spp.skpd_id as skpd_id_spp',
            'sp.id as id_sp',
            'sp.submit as submit_sp',
            'sp.status as status_sp',
            'sp.src_name as src_name_sp',
            'sp.notes as notes_sp',
            'sp.rejected_by as rejected_by_sp',
            'sp.verify as verify_sp',
            'sp.assigned_to as assigned_to_sp',
            'sp.reference_id as reference_id_sp',
            'spm.id as id_spm',
            'spm.nomor as nomor_spm',
            'spm.submit as submit_spm',
            'spm.status as status_spm',
            'spm.verify as verify_spm',
            'spm.src_name as src_name_spm',
            'spm.created_at as created_at_spm',
            'spm.updated_at as updated_at_spm',
            'spm.rejected_by as rejected_by_spm',
            'spm.id_unit_kerja as id_unit_kerja_spm',
            'unit_kerjas_spm.nama as unit_kerja_spm',
            'unit_kerjas_spm.skpd_id as skpd_id_spm',
            'sptjm.id as id_sptjm',
            'sptjm.nomor as nomor_sptjm',
            'sptjm.submit as submit_sptjm',
            'sptjm.status as status_sptjm',
            'sptjm.verify as verify_sptjm',
            'sptjm.src_name as src_name_sptjm',
            'sp_pengajuan.id as id_sp_pengajuan',
            'sp_pengajuan.nomor as nomor_sp_pengajuan',
            'sp_pengajuan.submit as submit_sp_pengajuan',
            'sp_pengajuan.status as status_sp_pengajuan',
            'sp_pengajuan.verify as verify_sp_pengajuan',
            'sp_pengajuan.src_name as src_name_sp_pengajuan',
            DB::raw("CASE WHEN sp.submit LIKE '%5%' THEN 1 ELSE 0 END AS sp_submit_has_5"),
            DB::raw("CASE WHEN sp.submit LIKE '%6%' THEN 1 ELSE 0 END AS sp_submit_has_6"),
            DB::raw("CASE WHEN sp.submit LIKE '%7%' THEN 1 ELSE 0 END AS sp_submit_has_7"),
            DB::raw("CASE WHEN sp.status LIKE '%7%' THEN 1 ELSE 0 END AS sp_status_has_7"),
            DB::raw("CASE WHEN spm.status LIKE '%5%' THEN 1 ELSE 0 END AS spm_status_has_5"),
            DB::raw("CASE WHEN spm.status LIKE '%6%' THEN 1 ELSE 0 END AS spm_status_has_6"),
            DB::raw("CASE WHEN sptjm.status LIKE '%5%' THEN 1 ELSE 0 END AS sptjm_status_has_5"),
            DB::raw("CASE WHEN sptjm.status LIKE '%6%' THEN 1 ELSE 0 END AS sptjm_status_has_6"),
            DB::raw("CASE WHEN sp_pengajuan.status LIKE '%5%' THEN 1 ELSE 0 END AS sp_pengajuan_status_5"),
            DB::raw("CASE WHEN sp_pengajuan.status LIKE '%6%' THEN 1 ELSE 0 END AS sp_pengajuan_status_6"),
        ];
    }
}
