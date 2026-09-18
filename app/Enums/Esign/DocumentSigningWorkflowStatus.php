<?php

namespace App\Enums\Esign;

enum DocumentSigningWorkflowStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case NeedsReview = 'needs_review';
}
