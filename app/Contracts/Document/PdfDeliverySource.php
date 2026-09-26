<?php

declare(strict_types=1);

namespace App\Contracts\Document;

use App\Data\Document\ResolvedPdfDeliverySource;
use App\Models\Document;

interface PdfDeliverySource
{
    public const RESOURCE_DOCUMENT = 'document';

    public const RESOURCE_BILLING = 'billing';

    public const RESOURCE_SPJ_FUNCTIONAL = 'spj_fungsional';

    public function resolve(
        Document $document,
        string $resourceKey = self::RESOURCE_DOCUMENT,
    ): ?ResolvedPdfDeliverySource;
}
