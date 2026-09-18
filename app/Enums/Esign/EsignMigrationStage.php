<?php

namespace App\Enums\Esign;

enum EsignMigrationStage: string
{
    case Discovered = 'discovered';
    case MetadataMapped = 'metadata_mapped';
    case FileCopied = 'file_copied';
    case ChecksumVerified = 'checksum_verified';
    case CanonicalActivated = 'canonical_activated';
    case Completed = 'completed';
}
