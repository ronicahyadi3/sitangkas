<?php

declare(strict_types=1);

namespace App\Enums\Document;

enum DocumentDetailActionMode: string
{
    case Canonical = 'canonical';
    case LegacyTransition = 'legacy_transition';
    case None = 'none';
}
