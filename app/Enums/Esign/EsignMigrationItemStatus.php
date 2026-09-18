<?php

namespace App\Enums\Esign;

enum EsignMigrationItemStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case RetryableFailed = 'retryable_failed';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';
}
