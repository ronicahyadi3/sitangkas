<?php

declare(strict_types=1);

namespace App\Data\Esign;

final readonly class DocumentSigningWorkflowDefinition
{
    /** @param non-empty-list<string> $roleCodes */
    public function __construct(
        public string $variant,
        public array $roleCodes,
        public int $version = 1,
    ) {}
}
