<?php

namespace App\Enums\Esign;

enum EsignAttemptStatus: string
{
    case Prepared = 'prepared';
    case Signing = 'signing';
    case Validating = 'validating';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Prepared => in_array($target, [self::Signing, self::Failed], true),
            self::Signing => in_array($target, [self::Validating, self::Failed, self::Unknown], true),
            self::Validating => in_array($target, [self::Succeeded, self::Failed], true),
            self::Unknown => in_array($target, [self::Succeeded, self::Failed], true),
            self::Succeeded, self::Failed => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed], true);
    }
}
