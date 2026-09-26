<?php

declare(strict_types=1);

namespace App\Data\Document;

use App\Enums\Document\DocumentDetailSourceState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class ResolvedPdfDeliverySource
{
    /**
     * @param  array<string, bool|float|int|string|null>  $metadata
     */
    public function __construct(
        public string $resourceType,
        public string $resourceKey,
        public int $documentId,
        public Model $authorizationSubject,
        public DocumentDetailSourceState $sourceState,
        public ?int $documentArtifactId,
        public ?string $artifactPublicId,
        public string $storageDisk,
        public string $filePath,
        public string $safeFilename,
        public string $mimeType,
        public string $sha256,
        public int $sizeBytes,
        public int $documentYear,
        public array $metadata = [],
    ) {
        $normalizedPath = str_replace('\\', '/', $this->filePath);
        $pathSegments = explode('/', $normalizedPath);

        if (preg_match('/\A[a-z0-9_]+\z/', $this->resourceType) !== 1
            || preg_match('/\A[a-z0-9._:-]+\z/', $this->resourceKey) !== 1
            || $this->documentId < 1
            || $this->sourceState === DocumentDetailSourceState::Unavailable
            || ($this->documentArtifactId !== null && $this->documentArtifactId < 1)
            || ($this->artifactPublicId !== null && ! Str::isUuid($this->artifactPublicId))
            || preg_match('/\A[a-z0-9_-]+\z/i', $this->storageDisk) !== 1
            || $normalizedPath === ''
            || Str::startsWith($normalizedPath, '/')
            || preg_match('/\A[A-Za-z]:\//', $normalizedPath) === 1
            || in_array('..', $pathSegments, true)
            || basename(str_replace('\\', '/', $this->safeFilename)) !== $this->safeFilename
            || preg_match('/\.pdf\z/i', $this->safeFilename) !== 1
            || $this->mimeType !== 'application/pdf'
            || preg_match('/\A[a-f0-9]{64}\z/i', $this->sha256) !== 1
            || $this->sizeBytes < 1
            || $this->documentYear < 2000
            || $this->documentYear > 2100) {
            throw new InvalidArgumentException('resolved_pdf_delivery_source_invalid');
        }
    }
}
