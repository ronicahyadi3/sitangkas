<?php

namespace App\Services\Auth;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class LoginEventRetention
{
    public function until(DateTimeInterface $occurredAt): ?CarbonImmutable
    {
        $retentionDays = $this->retentionDays();

        if ($retentionDays === null) {
            return null;
        }

        return CarbonImmutable::instance($occurredAt)->addDays($retentionDays);
    }

    private function retentionDays(): ?int
    {
        $retentionDays = config('auth.audit.login_events.enrichment.integrity.retention_days');

        if (! is_string($retentionDays) && ! is_numeric($retentionDays)) {
            return null;
        }

        $retentionDays = filter_var($retentionDays, FILTER_VALIDATE_INT);

        return is_int($retentionDays) && $retentionDays > 0 ? $retentionDays : null;
    }
}
