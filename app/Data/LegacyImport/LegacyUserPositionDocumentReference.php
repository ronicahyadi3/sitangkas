<?php

namespace App\Data\LegacyImport;

use stdClass;

final readonly class LegacyUserPositionDocumentReference
{
    public function __construct(
        public int $userPositionId,
        public string $sourcePath,
    ) {}

    public static function fromDatabaseRow(stdClass $row): self
    {
        return new self(
            userPositionId: (int) $row->id,
            sourcePath: (string) $row->file_sk,
        );
    }
}
