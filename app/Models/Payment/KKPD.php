<?php

declare(strict_types=1);

namespace App\Models\Payment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KKPD
{
    public const PAYMENT_TYPE = 'KKPD';

    public const ROOT_DOCUMENT_TYPES = ['DPR', 'SPP', 'SPM', 'SP2D'];

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

    public static function actionRules(int $id, ?int $referenceId = null): array
    {
        return [
            'DPR' => ['id' => $id, 'reference_id' => $id, 'src_types' => ['NPD', 'DPT']],
            'SPP' => ['id' => $id, 'reference_id' => $id, 'src_types' => ['BMD']],
            'SPM' => ['id' => $id, 'reference_id' => $referenceId, 'src_types' => ['SP', 'SPTJM', 'SP_PENGAJUAN']],
            'SP2D' => ['id' => $id],
        ];
    }
}
