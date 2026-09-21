<?php

namespace App\Data\Esign;

final readonly class DocumentArtifactIntegrityData
{
    public function __construct(
        public int $sizeBytes,
        public string $sha256,
        public string $md5,
    ) {}
}
