<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserPositionClassification
{
    /**
     * @param  list<LegacyUserPositionProjection>  $positions
     * @param  list<int>  $unclassifiableRowIds
     * @param  list<int>  $missingReconciliationTimestampRowIds
     */
    public function __construct(
        public array $positions,
        public array $unclassifiableRowIds,
        public array $missingReconciliationTimestampRowIds,
    ) {}
}
