<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserImportPasswordPolicy
{
    public function __construct(
        public string $configuredStrategy,
        public string $effectiveStrategy,
        public bool $mustChangePassword,
        public string $resolvedEnvironment,
        private ?string $sharedPasswordHash,
    ) {}

    public function passwordHashFor(LegacyUserAccount $account): string
    {
        return $this->sharedPasswordHash ?? $account->passwordHash();
    }

    public function usesDevelopmentOverride(): bool
    {
        return $this->sharedPasswordHash !== null;
    }
}
