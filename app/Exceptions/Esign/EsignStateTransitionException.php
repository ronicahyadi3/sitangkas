<?php

namespace App\Exceptions\Esign;

use RuntimeException;

final class EsignStateTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $entity,
        public readonly string $fromStatus,
        public readonly string $toStatus,
    ) {
        parent::__construct(
            "Transisi status {$entity} dari {$fromStatus} ke {$toStatus} tidak diizinkan."
        );
    }

    /** @return array{esign_entity: string, esign_from_status: string, esign_to_status: string} */
    public function context(): array
    {
        return [
            'esign_entity' => $this->entity,
            'esign_from_status' => $this->fromStatus,
            'esign_to_status' => $this->toStatus,
        ];
    }
}
