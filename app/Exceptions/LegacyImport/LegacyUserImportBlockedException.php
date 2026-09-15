<?php

namespace App\Exceptions\LegacyImport;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

final class LegacyUserImportBlockedException extends Exception implements ShouldntReport
{
    /** @param list<string> $blockers */
    public function __construct(
        public readonly string $stage,
        public readonly array $blockers,
    ) {
        parent::__construct(
            sprintf(
                'Legacy user import blocked at stage [%s] with %d blocker(s).',
                $stage,
                count($blockers),
            ),
        );
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'legacy_import_stage' => $this->stage,
            'legacy_import_blocker_count' => count($this->blockers),
            'legacy_import_blockers' => $this->blockers,
        ];
    }
}
