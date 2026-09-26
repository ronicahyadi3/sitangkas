<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Document;
use Illuminate\Support\Str;

final class LegacyDocumentSigningRuleRegistry
{
    /** @var array<string, list<int>> */
    private const PAYMENT_DOCUMENT_SIGNERS = [
        'GU_SKPD:NPD' => [8, 5],
        'GU_SKPD:SP' => [7],
        'GU_SKPD:SPM' => [5],
        'GU_SKPD:SPTJM' => [5],
        'GU_SKPD:SP_PENGAJUAN' => [5],
        'GU_SKPD:TBP' => [9, 5],
        'GU_UK:LPJ' => [9],
        'GU_UK:LPJ_BPP' => [10],
        'GU_UK:NPD' => [8, 6],
        'GU_UK:SPP' => [9, 5],
        'GU_UK:TBP' => [10, 6],
        'UP:SP' => [7],
        'UP:SP2D' => [2, 3],
        'UP:SPM' => [5],
        'UP:SPP' => [9, 5],
        'UP:SPTJM' => [5],
        'UP:SP_PENGAJUAN' => [5],
    ];

    /** @var array<string, list<int>> */
    private const DOCUMENT_SIGNERS = [
        'BMD' => [],
        'DPR' => [8],
        'DPT' => [5, 6],
        'LPJ' => [5, 6, 9, 10],
        'NPD' => [8, 5],
        'PENGAJUAN' => [2, 5, 6, 8],
        'SP' => [7],
        'SP2D' => [2, 3],
        'SPJ' => [],
        'SPJ_BPP' => [],
        'SPM' => [5, 6],
        'SPP' => [5, 6, 8, 9, 10],
        'SPTJM' => [5, 6],
        'SP_PENGAJUAN' => [5, 6],
        'STS' => [5, 6, 9, 10],
        'TBP' => [10, 9, 6, 5],
    ];

    /** @return list<int> */
    public function signerJabatanIds(Document $document): array
    {
        $paymentType = Str::upper(trim((string) $document->payment_type));
        $documentType = Str::upper(trim((string) $document->src_type));
        $specificRule = self::PAYMENT_DOCUMENT_SIGNERS["{$paymentType}:{$documentType}"] ?? null;

        if (is_array($specificRule)) {
            return $specificRule;
        }

        if ($documentType === 'LPJ' && in_array($paymentType, ['GU_SKPD', 'GU_UK'], true)) {
            return [9, 10];
        }

        return self::DOCUMENT_SIGNERS[$documentType] ?? [];
    }
}
