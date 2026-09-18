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
    case ReviewRequired = 'review_required';
    case ReviewResolved = 'review_resolved';
    case LegacyMapped = 'legacy_mapped';
}
