<?php

declare(strict_types=1);

namespace App\Enums\Document;

enum PdfDeliveryMode: string
{
    case Original = 'original';

    case IdentifiedWatermarked = 'identified_watermarked';

    case PublicWatermarked = 'public_watermarked';

    case Denied = 'denied';
}
