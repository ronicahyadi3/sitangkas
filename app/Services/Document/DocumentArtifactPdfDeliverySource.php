<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailSourceState;
use App\Enums\Esign\DocumentArtifactType;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\Esign\DocumentArtifact;
use App\Support\Esign\DocumentArtifactStoragePath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

final class DocumentArtifactPdfDeliverySource implements PdfDeliverySource
{
    public function __construct(
        private readonly CurrentDocumentArtifactResolver $currentArtifactResolver,
    ) {}

    public function resolve(
        Document $document,
        string $resourceKey = self::RESOURCE_DOCUMENT,
    ): ?ResolvedPdfDeliverySource {
        $artifact = match ($resourceKey) {
            self::RESOURCE_DOCUMENT => $this->resolveCurrentDocumentArtifact($document),
            self::RESOURCE_BILLING => $this->resolveAttachmentArtifact(
                $document,
                DocumentArtifact::ATTACHMENT_BILLING,
            ),
            self::RESOURCE_SPJ_FUNCTIONAL => $this->resolveAttachmentArtifact(
                $document,
                DocumentArtifact::ATTACHMENT_SPJ_FUNCTIONAL,
            ),
            default => null,
        };

        if (! $artifact instanceof DocumentArtifact) {
            return null;
        }

        $this->assertCanonicalMetadata($document, $artifact, $resourceKey);

        return $this->resolvedSource($document, $artifact, $resourceKey);
    }

    public function resolveHistoricalArtifact(
        Document $document,
        DocumentArtifact $artifact,
    ): ResolvedPdfDeliverySource {
        $this->assertHistoricalCanonicalMetadata($document, $artifact);

        return $this->resolvedSource($document, $artifact, 'history');
    }

    private function resolvedSource(
        Document $document,
        DocumentArtifact $artifact,
        string $resourceKey,
    ): ResolvedPdfDeliverySource {

        return new ResolvedPdfDeliverySource(
            resourceType: in_array($resourceKey, [self::RESOURCE_DOCUMENT, 'history'], true)
                ? 'document_artifact'
                : 'document_attachment_artifact',
            resourceKey: $resourceKey,
            documentId: (int) $document->getKey(),
            authorizationSubject: $artifact,
            sourceState: DocumentDetailSourceState::Canonical,
            documentArtifactId: (int) $artifact->getKey(),
            artifactPublicId: (string) $artifact->public_id,
            storageDisk: (string) $artifact->storage_disk,
            filePath: (string) $artifact->file_path,
            safeFilename: $this->safeFilename($document, $artifact),
            mimeType: (string) $artifact->mime_type,
            sha256: (string) $artifact->file_sha256,
            sizeBytes: (int) $artifact->size_bytes,
            documentYear: (int) $artifact->document_year,
            metadata: [
                'artifact_type' => $artifact->artifact_type->value,
                'artifact_version' => (int) $artifact->version,
                'document_type' => Str::upper(trim((string) $document->src_type)),
                'payment_type' => Str::upper(trim((string) $document->payment_type)),
                'resource_key' => $resourceKey,
                'source_system' => (string) $artifact->source_system,
            ],
        );
    }

    private function resolveCurrentDocumentArtifact(Document $document): ?DocumentArtifact
    {
        try {
            return $this->currentArtifactResolver->resolve($document);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function resolveAttachmentArtifact(
        Document $document,
        string $attachmentType,
    ): ?DocumentArtifact {
        if ($document->relationLoaded('pdfDeliveryArtifacts')) {
            return $document->pdfDeliveryArtifacts
                ->filter(static fn (DocumentArtifact $artifact): bool => $artifact->artifact_type === DocumentArtifactType::Attachment
                    && $artifact->source_reference_type === DocumentArtifact::SOURCE_REFERENCE_DOCUMENT_ATTACHMENT
                    && $artifact->source_reference_id === $attachmentType)
                ->sort(static fn (DocumentArtifact $left, DocumentArtifact $right): int => [
                    (int) $right->version,
                    (int) $right->getKey(),
                ] <=> [
                    (int) $left->version,
                    (int) $left->getKey(),
                ])
                ->first();
        }

        return $document->artifacts()
            ->where('artifact_type', DocumentArtifactType::Attachment->value)
            ->where('source_reference_type', DocumentArtifact::SOURCE_REFERENCE_DOCUMENT_ATTACHMENT)
            ->where('source_reference_id', $attachmentType)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
    }

    private function assertCanonicalMetadata(
        Document $document,
        DocumentArtifact $artifact,
        string $resourceKey,
    ): void {
        $this->assertArtifactMetadata($document, $artifact);

        if (! $this->hasExpectedArtifactIdentity($artifact, $resourceKey)) {
            throw new EsignInvariantViolationException('pdf_delivery_canonical_metadata_invalid');
        }
    }

    private function assertHistoricalCanonicalMetadata(
        Document $document,
        DocumentArtifact $artifact,
    ): void {
        $this->assertArtifactMetadata($document, $artifact);

        if (! in_array($artifact->artifact_type, [
            DocumentArtifactType::BeforeSign,
            DocumentArtifactType::AfterSign,
        ], true)) {
            throw new EsignInvariantViolationException('pdf_delivery_history_artifact_invalid');
        }
    }

    private function assertArtifactMetadata(
        Document $document,
        DocumentArtifact $artifact,
    ): void {
        $storageDisk = (string) $artifact->storage_disk;
        $filePath = (string) $artifact->file_path;

        if ((int) $artifact->document_id !== (int) $document->getKey()
            || ! ($artifact->artifact_type instanceof DocumentArtifactType)
            || ! Str::isUuid((string) $artifact->public_id)
            || $storageDisk !== 'private'
            || $filePath === ''
            || (string) $artifact->mime_type !== 'application/pdf'
            || Str::lower((string) $artifact->extension) !== 'pdf'
            || (int) $artifact->version < 1
            || (int) $artifact->size_bytes < 1
            || (int) $artifact->document_year < 2000
            || preg_match('/\A[a-f0-9]{64}\z/i', (string) $artifact->file_sha256) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/i', (string) $artifact->storage_path_sha256) !== 1
            || ! hash_equals(
                (string) $artifact->storage_path_sha256,
                DocumentArtifactStoragePath::checksum($storageDisk, $filePath),
            )) {
            throw new EsignInvariantViolationException('pdf_delivery_canonical_metadata_invalid');
        }
    }

    private function hasExpectedArtifactIdentity(
        DocumentArtifact $artifact,
        string $resourceKey,
    ): bool {
        if ($resourceKey === self::RESOURCE_DOCUMENT) {
            return $artifact->is_current
                && in_array($artifact->artifact_type, [
                    DocumentArtifactType::BeforeSign,
                    DocumentArtifactType::AfterSign,
                ], true);
        }

        return $artifact->artifact_type === DocumentArtifactType::Attachment
            && $artifact->source_reference_type === DocumentArtifact::SOURCE_REFERENCE_DOCUMENT_ATTACHMENT
            && $artifact->source_reference_id === $resourceKey;
    }

    private function safeFilename(Document $document, DocumentArtifact $artifact): string
    {
        $candidate = trim((string) $artifact->original_name);

        if ($candidate === '') {
            $candidate = Str::upper(trim((string) $document->src_type))
                .'-'.(string) $artifact->public_id.'.pdf';
        }

        $basename = basename(str_replace('\\', '/', $candidate));
        $stem = (string) Str::of(pathinfo($basename, PATHINFO_FILENAME))
            ->squish()
            ->replaceMatches('/[^\pL\pN._ -]+/u', '-')
            ->trim(' .-_')
            ->limit(180, '');

        if ($stem === '') {
            $stem = 'dokumen-'.(string) $artifact->public_id;
        }

        return $stem.'.pdf';
    }
}
