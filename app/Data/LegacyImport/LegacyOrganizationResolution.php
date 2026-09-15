<?php

namespace App\Data\LegacyImport;

final readonly class LegacyOrganizationResolution
{
    /**
     * @param  list<array{type: string, reference_id: int|null}>  $missingReferences
     * @param  list<array{type: string, reference_id: int}>  $inactiveReferences
     * @param  array{row_id: int, unit_kerja_id: int, source_instansi_id: int|null, target_instansi_id: int}|null  $allowedCorrection
     * @param  array{row_id: int, unit_kerja_id: int, source_instansi_id: int|null, target_instansi_id: int}|null  $unexpectedMismatch
     */
    public function __construct(
        public ?int $canonicalInstansiId,
        public array $missingReferences,
        public array $inactiveReferences,
        public ?array $allowedCorrection,
        public ?array $unexpectedMismatch,
    ) {}

    public function isImportable(): bool
    {
        return $this->missingReferences === [] && $this->unexpectedMismatch === null;
    }
}
