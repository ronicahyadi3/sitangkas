<?php

declare(strict_types=1);

namespace App\Enums\Document;

enum DocumentDetailSourceState: string
{
    case Canonical = 'canonical';
    case LegacyPrivatePending = 'legacy_private_pending';
    case LegacyPublicPending = 'legacy_public_pending';
    case Unavailable = 'unavailable';
}
