<?php

namespace App\Enums\Esign;

enum DocumentSigningStepStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Signing = 'signing';
    case ReconciliationRequired = 'reconciliation_required';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
    case NeedsReview = 'needs_review';
}
