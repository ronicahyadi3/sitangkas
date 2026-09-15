<?php

namespace App\Data\LegacyImport;

use Carbon\CarbonImmutable;

final readonly class LegacyUserPositionProjection
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $jabatanId,
        public int $instansiId,
        public int $unitKerjaId,
        public bool $isActive,
        public bool $isCanonical,
        public ?int $canonicalUserPositionId,
        public ?string $legacyDuplicateReason,
        public int $sourceStatus,
        public bool $wasSourceActive,
        public ?CarbonImmutable $sourceCreatedAt,
        public ?CarbonImmutable $sourceUpdatedAt,
        public ?CarbonImmutable $originalDeletedAt,
        public ?CarbonImmutable $resultDeletedAt,
        public ?CarbonImmutable $endedAt,
        public ?CarbonImmutable $deactivatedAt,
        public ?string $deactivationReason,
        public bool $softDeleteSynthesized,
    ) {}

    public function contextKey(): string
    {
        return implode(':', [
            $this->userId,
            $this->jabatanId,
            $this->instansiId,
            $this->unitKerjaId,
        ]);
    }
}
