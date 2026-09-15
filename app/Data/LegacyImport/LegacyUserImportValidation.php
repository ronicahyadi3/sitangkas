<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserImportValidation
{
    /**
     * @param  list<array{name: string, expected: int|string, actual: int|string, passed: bool}>  $checks
     * @param  list<string>  $blockers
     * @param  array<string, mixed>  $metrics
     */
    public function __construct(
        public string $stage,
        public array $checks,
        public array $blockers,
        public array $metrics,
    ) {}

    public function passed(): bool
    {
        return $this->blockers === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'read_only' => true,
            'passed' => $this->passed(),
            'metrics' => $this->metrics,
        ];
    }
}
