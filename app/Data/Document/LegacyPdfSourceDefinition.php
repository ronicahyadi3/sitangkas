<?php

declare(strict_types=1);

namespace App\Data\Document;

use InvalidArgumentException;

final readonly class LegacyPdfSourceDefinition
{
    public function __construct(
        public string $resourceKey,
        public ?string $documentType,
        public string $filenameAttribute,
        public string $relativeDirectory,
        public ?string $signedDirectory,
    ) {
        if (preg_match('/\A[a-z0-9._:-]+\z/', $this->resourceKey) !== 1
            || ($this->documentType !== null
                && preg_match('/\A[A-Z0-9_]+\z/', $this->documentType) !== 1)
            || preg_match('/\A[a-z0-9_]+\z/i', $this->filenameAttribute) !== 1
            || preg_match('/\A[A-Za-z0-9_-]+\z/', $this->relativeDirectory) !== 1
            || ($this->signedDirectory !== null
                && preg_match('/\A[A-Za-z0-9_-]+\z/', $this->signedDirectory) !== 1)) {
            throw new InvalidArgumentException('legacy_pdf_source_definition_invalid');
        }
    }
}
