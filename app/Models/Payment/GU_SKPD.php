<?php

declare(strict_types=1);

namespace App\Models\Payment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GU_SKPD
{
    public const PAYMENT_TYPE = 'GU_SKPD';

    public const ROOT_DOCUMENT_TYPES = ['NPD', 'TBP', 'LPJ', 'SPM', 'SP2D'];

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
            'LPJ' => ['id' => $id, 'reference_id' => $id, 'src_types' => ['SPP', 'BMD']],
            'SPM' => ['id' => $id, 'reference_id' => $referenceId, 'src_types' => ['SP', 'SPTJM', 'SP_PENGAJUAN']],
            'SP2D' => ['id' => $id],
        ];
    }

    public static function withNpd($query, bool $joinUnitKerja = true)
    {
        return self::withSingleDocumentType($query, 'NPD', 'npd', 'unit_kerja_npd', $joinUnitKerja);
    }

    public static function withTbp($query, bool $joinUnitKerja = true)
    {
        return self::withSingleDocumentType($query, 'TBP', 'tbp', 'unit_kerja_tbp', $joinUnitKerja);
    }

    public static function withSpp($query, bool $joinUnitKerja = true)
    {
        return self::withSingleDocumentType($query, 'SPP', 'spp', 'unit_kerja_spp', $joinUnitKerja);
    }

    public static function withLpj($query, bool $joinUnitKerja = true)
    {
        return self::withSingleDocumentType($query, 'LPJ', 'lpj', 'unit_kerja_lpj', $joinUnitKerja);
    }

    public static function withSpj($query, bool $joinUnitKerja = true)
    {
        return self::withSingleDocumentType($query, 'SPJ', 'spj', 'unit_kerja_spj', $joinUnitKerja);
    }

    public static function withSpm($query, bool $joinUnitKerja = true)
    {
        return self::withSingleDocumentType($query, 'SPM', 'spm', 'unit_kerja_spm', $joinUnitKerja);
    }

    public static function withSp2d($query, bool $joinUnitKerja = true)
    {
        return self::withSingleDocumentType($query, 'SP2D', 'sp2d', 'unit_kerja_sp2d', $joinUnitKerja);
    }

    private static function withSingleDocumentType(
        $query,
        string $srcType,
        string $alias,
        string $unitAlias,
        bool $joinUnitKerja = true
    ) {
        $method = $joinUnitKerja ? 'join' : 'leftJoin';

        return $query
            ->where([
                ['document.src_type', '=', $srcType],
                ['document.payment_type', '=', self::PAYMENT_TYPE],
            ])
            ->{$method}(
                "unit_kerjas as {$unitAlias}",
                "{$unitAlias}.id",
                '=',
                'document.id_unit_kerja'
            )
            ->addSelect([
                "document.id as id_{$alias}",
                "document.nomor as nomor_{$alias}",
                "document.src_name as src_name_{$alias}",
                "document.status as status_{$alias}",
                "document.submit as submit_{$alias}",
                "document.rejected_by as rejected_by_{$alias}",
                "document.notes as notes_{$alias}",
                "document.id_unit_kerja as id_unit_kerja_{$alias}",
                "document.created_at as created_at_{$alias}",
                "{$unitAlias}.nama as unit_kerja_{$alias}",
                "{$unitAlias}.skpd_id as skpd_id_{$alias}",
            ]);
    }
}
