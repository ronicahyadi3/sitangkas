<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Data\Document\LegacyPdfSourceDefinition;
use Illuminate\Support\Str;

final class LegacyDocumentSourceRegistry
{
    /** @var array<string, string> */
    private const DOCUMENT_DIRECTORIES = [
        'BMD' => 'File_BMD',
        'DPR' => 'File_DPR',
        'DPT' => 'File_DPT',
        'LPJ' => 'File_LPJ',
        'LPJ_BPP' => 'File_LPJ_BPP',
        'NPD' => 'File_NPD',
        'PENGAJUAN' => 'File_PENGAJUAN',
        'SP' => 'File_SP',
        'SP2D' => 'File_SP2D',
        'SPJ' => 'File_SPJ',
        'SPM' => 'File_SPM',
        'SPP' => 'File_SPP',
        'SPTJM' => 'File_SPTJM',
        'SP_PENGAJUAN' => 'File_SP_PENGAJUAN',
        'STS' => 'File_STS',
        'TBP' => 'File_TBP',
    ];

    /** @var array<string, array{filename_attribute: string, directory: string}> */
    private const ATTACHMENT_DIRECTORIES = [
        PdfDeliverySource::RESOURCE_BILLING => [
            'filename_attribute' => 'billing',
            'directory' => 'File_Billing',
        ],
        PdfDeliverySource::RESOURCE_SPJ_FUNCTIONAL => [
            'filename_attribute' => 'spj_fungsional',
            'directory' => 'File_spj_fungsional',
        ],
    ];

    /** @var list<string> */
    private const DOCUMENT_TYPES_REQUIRING_REVIEW = ['SPJ_BPP'];

    public function forDocumentType(string $documentType): ?LegacyPdfSourceDefinition
    {
        $normalizedType = Str::upper(trim($documentType));
        $relativeDirectory = self::DOCUMENT_DIRECTORIES[$normalizedType] ?? null;

        if (! is_string($relativeDirectory)) {
            return null;
        }

        return new LegacyPdfSourceDefinition(
            resourceKey: PdfDeliverySource::RESOURCE_DOCUMENT,
            documentType: $normalizedType,
            filenameAttribute: 'src_name',
            relativeDirectory: $relativeDirectory,
            signedDirectory: 'signs',
        );
    }

    public function forAttachment(string $resourceKey): ?LegacyPdfSourceDefinition
    {
        $normalizedKey = Str::lower(trim($resourceKey));
        $definition = self::ATTACHMENT_DIRECTORIES[$normalizedKey] ?? null;

        if (! is_array($definition)) {
            return null;
        }

        return new LegacyPdfSourceDefinition(
            resourceKey: $normalizedKey,
            documentType: null,
            filenameAttribute: $definition['filename_attribute'],
            relativeDirectory: $definition['directory'],
            signedDirectory: null,
        );
    }

    public function requiresReview(string $documentType): bool
    {
        return in_array(
            Str::upper(trim($documentType)),
            self::DOCUMENT_TYPES_REQUIRING_REVIEW,
            true,
        );
    }

    /** @return list<string> */
    public function supportedDocumentTypes(): array
    {
        return array_keys(self::DOCUMENT_DIRECTORIES);
    }
}
