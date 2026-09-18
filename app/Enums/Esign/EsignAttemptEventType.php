<?php

namespace App\Enums\Esign;

enum EsignAttemptEventType: string
{
    case AttemptPrepared = 'attempt_prepared';
    case RequestStarted = 'request_started';
    case ProviderResponded = 'provider_responded';
    case OutputReceived = 'output_received';
    case ValidationStarted = 'validation_started';
    case AttemptSucceeded = 'attempt_succeeded';
    case AttemptFailed = 'attempt_failed';
    case OutcomeUnknown = 'outcome_unknown';
    case ReconciliationResolved = 'reconciliation_resolved';
}
