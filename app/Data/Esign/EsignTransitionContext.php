<?php

namespace App\Data\Esign;

final readonly class EsignTransitionContext
{
    /** @param array<string, mixed>|null $metadata */
    public function __construct(
        public ?int $actorUserId = null,
        public ?int $actorUserPositionId = null,
        public bool $actorIsActing = false,
        public ?string $reasonCode = null,
        public ?string $message = null,
        public ?string $correlationId = null,
        public ?array $metadata = null,
        public ?int $providerResponseId = null,
        public ?string $applicationErrorCode = null,
        public bool $retryable = false,
        public ?int $signatureOperationId = null,
    ) {}
}
