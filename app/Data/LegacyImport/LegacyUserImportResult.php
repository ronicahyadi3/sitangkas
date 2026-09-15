<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserImportResult
{
    /**
     * @param  array<string, string>  $decisions
     * @param  array<string, mixed>  $validation
     */
    public function __construct(
        public string $completedAt,
        public string $sourceFingerprint,
        public int $userCount,
        public int $canonicalPositionCount,
        public int $aliasPositionCount,
        public array $decisions,
        public array $validation,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'completed_at' => $this->completedAt,
            'source_fingerprint' => $this->sourceFingerprint,
            'user_count' => $this->userCount,
            'canonical_position_count' => $this->canonicalPositionCount,
            'alias_position_count' => $this->aliasPositionCount,
            'decisions' => $this->decisions,
            'validation' => $this->validation,
        ];
    }
}
