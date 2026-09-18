<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserPositionDocumentAnalysis
{
    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $physicalFiles
     * @param  array<string, mixed>  $documents
     * @param  list<array{name: string, expected: int|string, actual: int|string, passed: bool}>  $checks
     * @param  list<string>  $blockers
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $generatedAt,
        public array $source,
        public array $physicalFiles,
        public array $documents,
        public array $checks,
        public array $blockers,
        public array $warnings,
    ) {}

    public function hasBlockers(): bool
    {
        return $this->blockers !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'mode' => 'read_only',
            'source' => $this->source,
            'physical_files' => $this->physicalFiles,
            'documents' => $this->documents,
            'checks' => $this->checks,
            'blockers' => $this->blockers,
            'warnings' => $this->warnings,
        ];
    }
}
