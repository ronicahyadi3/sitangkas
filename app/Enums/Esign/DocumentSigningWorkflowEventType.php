<?php

namespace App\Enums\Esign;

enum DocumentSigningWorkflowEventType: string
{
    case WorkflowCreated = 'workflow_created';
    case WorkflowActivated = 'workflow_activated';
    case StepActivated = 'step_activated';
    case StepCompleted = 'step_completed';
    case StepRejected = 'step_rejected';
    case WorkflowCompleted = 'workflow_completed';
    case WorkflowRejected = 'workflow_rejected';
    case StepSigningStarted = 'step_signing_started';
    case StepReconciliationRequired = 'step_reconciliation_required';
    case StepSkipped = 'step_skipped';
    case ReviewRequired = 'review_required';
    case ReviewResolved = 'review_resolved';
    case LegacyMapped = 'legacy_mapped';
}
