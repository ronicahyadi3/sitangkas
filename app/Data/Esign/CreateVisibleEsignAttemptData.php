<?php

namespace App\Data\Esign;

final readonly class CreateVisibleEsignAttemptData
{
    /** @param array<string, mixed>|null $actorContextSnapshot */
    public function __construct(
        public string $idempotencyKey,
        public string $requestCorrelationId,
        public ?array $actorContextSnapshot = null,
        public string $provider = 'bsre',
    ) {}
}
