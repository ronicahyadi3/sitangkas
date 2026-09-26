<?php

declare(strict_types=1);

namespace App\Enums\Document;

enum PdfDeliveryPurpose: string
{
    case View = 'view';
    case Download = 'download';
    case SigningPreview = 'signing_preview';
}
