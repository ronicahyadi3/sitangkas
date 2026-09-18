<?php

namespace App\Enums\Esign;

enum DocumentSigningWorkflowStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case NeedsReview = 'needs_review';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Active, self::NeedsReview], true),
            self::Active => in_array($target, [self::Completed, self::Rejected, self::NeedsReview], true),
            self::NeedsReview => in_array($target, [self::Draft, self::Active], true),
            self::Completed, self::Rejected => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected], true);
    }
}
