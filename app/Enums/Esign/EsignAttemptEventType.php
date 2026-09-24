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
    case AttemptPartiallySigned = 'attempt_partially_signed';
    case AttemptResumed = 'attempt_resumed';
    case OutcomeUnknown = 'outcome_unknown';
    case ReconciliationResolved = 'reconciliation_resolved';
    case SignatureOperationStarted = 'signature_operation_started';
    case SignatureOperationOutputReceived = 'signature_operation_output_received';
    case SignatureOperationCompleted = 'signature_operation_completed';
    case SignatureOperationFailed = 'signature_operation_failed';
    case SignatureOperationOutcomeUnknown = 'signature_operation_outcome_unknown';
}
