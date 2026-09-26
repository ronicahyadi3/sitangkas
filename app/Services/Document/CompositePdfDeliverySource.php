<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailSourceState;
use App\Models\Document;

final class CompositePdfDeliverySource implements PdfDeliverySource
{
    private const LEGACY_PRIVATE_DISK = 'legacy_private_documents';

    private const LEGACY_PUBLIC_DISK = 'legacy_public_documents';

    public function __construct(
        private readonly DocumentArtifactPdfDeliverySource $artifactSource,
        private readonly LegacyFilesystemPdfDeliverySource $legacySource,
    ) {}

    public function resolve(
        Document $document,
        string $resourceKey = self::RESOURCE_DOCUMENT,
    ): ?ResolvedPdfDeliverySource {
        return $this->artifactSource->resolve($document, $resourceKey)
            ?? $this->legacySource->resolve(
                document: $document,
                resourceKey: $resourceKey,
                storageDisk: self::LEGACY_PRIVATE_DISK,
                sourceState: DocumentDetailSourceState::LegacyPrivatePending,
            )
            ?? $this->legacySource->resolve(
                document: $document,
                resourceKey: $resourceKey,
                storageDisk: self::LEGACY_PUBLIC_DISK,
                sourceState: DocumentDetailSourceState::LegacyPublicPending,
            );
    }
}
