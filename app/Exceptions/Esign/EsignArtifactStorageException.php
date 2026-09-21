<?php

namespace App\Exceptions\Esign;

use RuntimeException;

final class EsignArtifactStorageException extends RuntimeException
{
    public function __construct(
        public readonly string $storageErrorCode,
        public readonly ?string $artifactPublicId = null,
    ) {
        parent::__construct("Penyimpanan artifact eSign gagal: {$storageErrorCode}.");
    }

    /** @return array{esign_storage_error_code: string, artifact_public_id: string|null} */
    public function context(): array
    {
        return [
            'esign_storage_error_code' => $this->storageErrorCode,
            'artifact_public_id' => $this->artifactPublicId,
        ];
    }
}
