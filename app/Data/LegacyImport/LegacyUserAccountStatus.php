<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserAccountStatus
{
    public function __construct(
        public int $userId,
        public string $status,
        public string $reason,
        public bool $hasActiveCanonicalPosition,
        public bool $allSourceRowsDeleted,
    ) {}
}
