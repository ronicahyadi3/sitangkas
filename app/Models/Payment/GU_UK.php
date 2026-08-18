<?php

declare(strict_types=1);

namespace App\Models\Payment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GU_UK
{
    public const PAYMENT_TYPE = 'GU_UK';

    public const ROOT_DOCUMENT_TYPES = ['NPD', 'TBP', 'LPJ_BPP', 'LPJ', 'SPM', 'SP2D'];

    public static function rootQueryAlias(
        string $alias,
        ?int $tahun = null,
        bool $filterByYear = true
    ) {
        $query = DB::table("document as {$alias}")
            ->whereNull("{$alias}.deleted_at");

        if (! $filterByYear) {
            return $query;
        }

        $tahun = $tahun ?? (int) (session('tahun_aktif') ?? date('Y'));

        return $query->whereBetween("{$alias}.created_at", [
            Carbon::create($tahun)->startOfYear(),
            Carbon::create($tahun)->endOfYear(),
        ]);
    }

    public static function rootQuery(?int $tahun = null, bool $filterByYear = true)
    {
        return self::rootQueryAlias('document', $tahun, $filterByYear);
    }

    public static function isRootDocumentType(?string $srcType): bool
    {
        return in_array((string) $srcType, self::ROOT_DOCUMENT_TYPES, true);
    }

    public static function actionRules(int $id, ?int $referenceId): array
    {
        return [
            'NPD' => ['id' => $id],
            'TBP' => ['id' => $id, 'reference_id' => $referenceId, 'src_types' => ['SPJ']],
            'LPJ_BPP' => ['id' => $id],
            'LPJ' => ['id' => $id, 'reference_id' => $id, 'src_types' => ['SPP', 'BMD']],
            'SPM' => ['id' => $id, 'reference_id' => $referenceId, 'src_types' => ['SP', 'SPTJM', 'SP_PENGAJUAN']],
            'SP2D' => ['id' => $id],
        ];
    }

    public static function withNpd($query, bool $joinUnitKerja = true)
    {
        $method = $joinUnitKerja ? 'join' : 'leftJoin';

        return $query
            ->where([
                ['document.src_type', '=', 'NPD'],
                ['document.payment_type', '=', self::PAYMENT_TYPE],
            ])
            ->{$method}(
                'unit_kerjas as unit_kerja_npd',
                'unit_kerja_npd.id',
                '=',
                'document.id_unit_kerja'
            )
            ->addSelect([
                'document.id as id_npd',
                'document.nomor as nomor_npd',
                'document.src_name as src_name_npd',
                'document.status as status_npd',
                'document.submit as submit_npd',
                'document.rejected_by as rejected_by_npd',
                'document.notes as notes_npd',
                'document.id_unit_kerja as id_unit_kerja_npd',
                'document.created_at as created_at_npd',
                'unit_kerja_npd.nama as unit_kerja_npd',
                'unit_kerja_npd.skpd_id as skpd_id_npd',
            ]);
    }

    public static function withTbp($query, bool $joinUnitKerja = true)
    {
        $method = $joinUnitKerja ? 'join' : 'leftJoin';

        return $query
            ->where([
                ['document.src_type', '=', 'TBP'],
                ['document.payment_type', '=', self::PAYMENT_TYPE],
            ])
            ->{$method}(
                'unit_kerjas as unit_kerja_tbp',
                'unit_kerja_tbp.id',
                '=',
                'document.id_unit_kerja'
            )
            ->addSelect([
                'document.id as id_tbp',
                'document.reference_id as reference_id_tbp',
                'document.nomor as nomor_tbp',
                'document.src_name as src_name_tbp',
                'document.status as status_tbp',
                'document.submit as submit_tbp',
                'document.rejected_by as rejected_by_tbp',
                'document.notes as notes_tbp',
                'document.id_unit_kerja as id_unit_kerja_tbp',
                'document.created_at as created_at_tbp',
                'unit_kerja_tbp.nama as unit_kerja_tbp',
                'unit_kerja_tbp.skpd_id as skpd_id_tbp',
            ]);
    }
}
