<?php

namespace App\Data\Esign;

final readonly class CreateEsignAttemptData
{
    /** @param array<string, mixed>|null $actorContextSnapshot */
    public function __construct(
        public int $documentSigningStepId,
        public int $sourceArtifactId,
        public string $idempotencyKey,
        public string $requestCorrelationId,
        public string $requestFingerprint,
        public ?string $previewArtifactSha256 = null,
        public ?int $actorUserId = null,
        public ?int $actorUserPositionId = null,
        public ?int $signerUserId = null,
        public ?int $signerUserPositionId = null,
        public bool $isActing = false,
        public ?string $effectiveRoleCode = null,
        public ?int $effectiveUnitKerjaId = null,
        public ?int $effectiveInstansiId = null,
        public ?array $actorContextSnapshot = null,
        public string $provider = 'bsre',
    ) {}
}
