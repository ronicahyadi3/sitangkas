<?php

namespace App\Data\Esign;

final readonly class StagedDocumentArtifact
{
    public function __construct(
        public string $publicId,
        public string $storageDisk,
        public string $stagingPath,
        public int $sizeBytes,
        public string $sha256,
        public bool $hasPdfHeader,
        public int $documentYear,
        public int $documentMonth,
    ) {}
}
