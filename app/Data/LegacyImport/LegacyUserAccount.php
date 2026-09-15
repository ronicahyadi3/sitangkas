<?php

namespace App\Data\LegacyImport;

use Carbon\CarbonImmutable;
use SensitiveParameter;

final readonly class LegacyUserAccount
{
    /**
     * @param  list<int>  $sourceRowIds
     * @param  list<string>  $conflictingFields
     */
    public function __construct(
        public int $id,
        public string $nik,
        public ?string $nip,
        public string $name,
        public ?string $email,
        #[SensitiveParameter]
        private string $passwordHash,
        public array $sourceRowIds,
        public array $conflictingFields,
        public string $selectionTier,
        public bool $hasActiveSourceRow,
        public bool $allSourceRowsDeleted,
        public ?CarbonImmutable $sourceCreatedAt,
        public ?CarbonImmutable $sourceUpdatedAt,
    ) {}

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }
}
