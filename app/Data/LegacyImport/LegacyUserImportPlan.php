<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserImportPlan
{
    public function __construct(
        public LegacyUserImportAnalysis $analysis,
        public LegacyUserAccountAggregation $accountAggregation,
        public LegacyUserAccountStatusResolution $accountStatusResolution,
        public LegacyUserPositionClassification $positionClassification,
    ) {}

    /**
     * @return array{
     *     source: array<string, mixed>,
     *     accounts: array<string, mixed>,
     *     identity: array<string, mixed>,
     *     organization: array<string, mixed>,
     *     positions: array<string, mixed>,
     *     status: array<string, mixed>
     * }
     */
    public function validationInput(): array
    {
        return [
            'source' => $this->analysis->source,
            'accounts' => $this->analysis->accounts,
            'identity' => $this->analysis->identity,
            'organization' => $this->analysis->organization,
            'positions' => $this->analysis->positions,
            'status' => $this->analysis->status,
        ];
    }
}
