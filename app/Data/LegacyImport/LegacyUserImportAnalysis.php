<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserImportAnalysis
{
    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $accounts
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $organization
     * @param  array<string, mixed>  $positions
     * @param  array<string, mixed>  $status
     * @param  list<array{name: string, expected: int|string, actual: int|string, passed: bool}>  $checks
     * @param  list<string>  $blockers
     * @param  list<string>  $warnings
     * @param  list<string>  $pendingDecisions
     * @param  array<string, mixed>  $validation
     */
    public function __construct(
        public string $generatedAt,
        public array $source,
        public array $accounts,
        public array $identity,
        public array $organization,
        public array $positions,
        public array $status,
        public array $checks,
        public array $blockers,
        public array $warnings,
        public array $pendingDecisions,
        public array $validation,
    ) {}

    public function hasBlockers(): bool
    {
        return $this->blockers !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'mode' => 'dry-run',
            'writes_target_database' => false,
            'source' => $this->source,
            'accounts' => $this->accounts,
            'identity' => $this->identity,
            'organization' => $this->organization,
            'positions' => $this->positions,
            'status' => $this->status,
            'checks' => $this->checks,
            'blockers' => $this->blockers,
            'warnings' => $this->warnings,
            'pending_decisions' => $this->pendingDecisions,
            'validation' => $this->validation,
        ];
    }
}
