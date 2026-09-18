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

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Active, self::Skipped, self::NeedsReview], true),
            self::Active => in_array($target, [self::Signing, self::Rejected, self::NeedsReview], true),
            self::Signing => in_array($target, [
                self::Active,
                self::Completed,
                self::ReconciliationRequired,
                self::NeedsReview,
            ], true),
            self::ReconciliationRequired => in_array($target, [self::Active, self::Completed, self::NeedsReview], true),
            self::NeedsReview => in_array($target, [self::Pending, self::Active], true),
            self::Completed, self::Rejected, self::Skipped => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Skipped], true);
    }
}
