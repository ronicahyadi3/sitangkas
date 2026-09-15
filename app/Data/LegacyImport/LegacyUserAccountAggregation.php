<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserAccountAggregation
{
    /**
     * @param  list<LegacyUserAccount>  $accounts
     * @param  list<int>  $invalidNikRowIds
     */
    public function __construct(
        public array $accounts,
        public array $invalidNikRowIds,
    ) {}
}
