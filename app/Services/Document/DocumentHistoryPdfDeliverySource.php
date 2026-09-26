<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailSourceState;
use App\Models\Document;
use App\Models\DocumentHistory;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\EsignAttemptLegacyLink;

final class DocumentHistoryPdfDeliverySource
{
    private const LEGACY_PRIVATE_DISK = 'legacy_private_documents';

    private const LEGACY_PUBLIC_DISK = 'legacy_public_documents';

    /** @var list<string> */
    private const SIGNED_ACTIONS = [
        DocumentHistory::ACTION_SUBMIT,
        DocumentHistory::ACTION_VERIFY,
        DocumentHistory::ACTION_TTE,
        DocumentHistory::ACTION_REJECT,
        DocumentHistory::ACTION_DELETE,
    ];

    public function __construct(
        private readonly DocumentArtifactPdfDeliverySource $artifactSource,
        private readonly LegacyFilesystemPdfDeliverySource $legacySource,
    ) {}

    public function resolve(DocumentHistory $history): ?ResolvedPdfDeliverySource
    {
        $document = $history->document;
        if (! $document instanceof Document) {
            return null;
        }

        $linkedArtifact = $this->linkedArtifact($history);
        if ($linkedArtifact instanceof DocumentArtifact) {
            return $this->artifactSource->resolveHistoricalArtifact(
                $document,
                $linkedArtifact,
            );
        }

        $snapshot = clone $document;
        $snapshot->setAttribute('src_name', (string) $history->src_name);
        $snapshot->setAttribute(
            'status',
            in_array((string) $history->action, self::SIGNED_ACTIONS, true)
                ? 'history_signed'
                : null,
        );

        $source = $this->legacySource->resolve(
            document: $snapshot,
            resourceKey: 'document',
            storageDisk: self::LEGACY_PRIVATE_DISK,
            sourceState: DocumentDetailSourceState::LegacyPrivatePending,
        ) ?? $this->legacySource->resolve(
            document: $snapshot,
            resourceKey: 'document',
            storageDisk: self::LEGACY_PUBLIC_DISK,
            sourceState: DocumentDetailSourceState::LegacyPublicPending,
        );

        if (! $source instanceof ResolvedPdfDeliverySource) {
            return null;
        }

        return new ResolvedPdfDeliverySource(
            resourceType: 'document_history',
            resourceKey: 'history',
            documentId: $source->documentId,
            authorizationSubject: $document,
            sourceState: $source->sourceState,
            documentArtifactId: null,
            artifactPublicId: null,
            storageDisk: $source->storageDisk,
            filePath: $source->filePath,
            safeFilename: $source->safeFilename,
            mimeType: $source->mimeType,
            sha256: $source->sha256,
            sizeBytes: $source->sizeBytes,
            documentYear: $source->documentYear,
            metadata: [
                ...$source->metadata,
                'document_history_id' => (int) $history->getKey(),
                'history_action' => (string) $history->action,
            ],
        );
    }

    private function linkedArtifact(DocumentHistory $history): ?DocumentArtifact
    {
        $link = EsignAttemptLegacyLink::query()
            ->with('artifact')
            ->where('legacy_table', 'document_process')
            ->where('legacy_id', $history->getKey())
            ->where('mapping_status', 'mapped')
            ->whereNotNull('document_artifact_id')
            ->latest('id')
            ->first();

        return $link?->artifact;
    }
}
