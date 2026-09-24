<?php

namespace App\Enums\Esign;

enum EsignSignatureOperationStatus: string
{
    case Pending = 'pending';
    case Signing = 'signing';
    case OutputReceived = 'output_received';
    case Completed = 'completed';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => $target === self::Signing,
            self::Signing => in_array($target, [self::OutputReceived, self::Failed, self::Unknown], true),
            self::OutputReceived => in_array($target, [self::Completed, self::Failed, self::Unknown], true),
            self::Failed => $target === self::Signing,
            self::Unknown => in_array($target, [self::OutputReceived, self::Completed, self::Failed], true),
            self::Completed => false,
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }
}
