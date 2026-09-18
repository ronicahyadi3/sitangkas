<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\EsignTransitionContext;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowEventType;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Exceptions\Esign\EsignStateTransitionException;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\DocumentSigningWorkflowEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentSigningWorkflowTransitionService
{
    public function transition(
        DocumentSigningWorkflow|int $workflow,
        DocumentSigningWorkflowStatus $target,
        EsignTransitionContext $context = new EsignTransitionContext,
    ): DocumentSigningWorkflow {
        $workflowId = $workflow instanceof DocumentSigningWorkflow
            ? (int) $workflow->getKey()
            : $workflow;

        return DB::transaction(function () use ($workflowId, $target, $context): DocumentSigningWorkflow {
            /** @var DocumentSigningWorkflow $lockedWorkflow */
            $lockedWorkflow = DocumentSigningWorkflow::query()
                ->lockForUpdate()
                ->findOrFail($workflowId);
            $from = $lockedWorkflow->status;

            if ($from === $target) {
                return $lockedWorkflow;
            }

            if (! $from->canTransitionTo($target)) {
                throw new EsignStateTransitionException('document_signing_workflow', $from->value, $target->value);
            }

            $this->assertReasonWhenRequired($from, $target, $context);

            if (in_array($target, [
                DocumentSigningWorkflowStatus::Active,
                DocumentSigningWorkflowStatus::Completed,
            ], true)) {
                $this->assertCurrentArtifactInvariant($lockedWorkflow);
            }

            if ($target === DocumentSigningWorkflowStatus::Active
                && ! $lockedWorkflow->steps()->exists()) {
                throw new EsignInvariantViolationException('active_workflow_requires_steps');
            }

            if ($target === DocumentSigningWorkflowStatus::Completed) {
                $hasIncompleteStep = $lockedWorkflow->steps()
                    ->whereNotIn('status', [
                        DocumentSigningStepStatus::Completed->value,
                        DocumentSigningStepStatus::Skipped->value,
                    ])
                    ->exists();

                if ($hasIncompleteStep) {
                    throw new EsignInvariantViolationException('workflow_completed_with_incomplete_steps');
                }
            }

            $lockedWorkflow->status = $target;
            $lockedWorkflow->lock_version = ((int) $lockedWorkflow->lock_version) + 1;

            if ($target === DocumentSigningWorkflowStatus::Active) {
                $lockedWorkflow->started_at ??= now();
            }

            if ($target === DocumentSigningWorkflowStatus::Completed) {
                $lockedWorkflow->completed_at = now();
            }

            if ($target === DocumentSigningWorkflowStatus::Rejected) {
                $lockedWorkflow->rejected_at = now();
            }

            $lockedWorkflow->save();

            DocumentSigningWorkflowEvent::query()->create([
                'public_id' => (string) Str::uuid(),
                'document_signing_workflow_id' => $lockedWorkflow->getKey(),
                'event_type' => $this->eventType($from, $target),
                'from_workflow_status' => $from,
                'to_workflow_status' => $target,
                'actor_user_id' => $context->actorUserId,
                'actor_user_position_id' => $context->actorUserPositionId,
                'actor_is_acting' => $context->actorIsActing,
                'reason_code' => $context->reasonCode,
                'message' => $context->message,
                'correlation_id' => $context->correlationId,
                'metadata' => $context->metadata,
                'occurred_at' => now(),
            ]);

            return $lockedWorkflow;
        }, attempts: 3);
    }

    private function assertCurrentArtifactInvariant(DocumentSigningWorkflow $workflow): void
    {
        if ($workflow->current_artifact_id === null) {
            throw new EsignInvariantViolationException('workflow_current_artifact_required');
        }

        $currentArtifact = DocumentArtifact::query()
            ->lockForUpdate()
            ->findOrFail($workflow->current_artifact_id);

        if ($currentArtifact->document_id !== $workflow->document_id || ! $currentArtifact->is_current) {
            throw new EsignInvariantViolationException('workflow_current_artifact_mismatch');
        }
    }

    private function assertReasonWhenRequired(
        DocumentSigningWorkflowStatus $from,
        DocumentSigningWorkflowStatus $target,
        EsignTransitionContext $context,
    ): void {
        $requiresReason = in_array($target, [
            DocumentSigningWorkflowStatus::Rejected,
            DocumentSigningWorkflowStatus::NeedsReview,
        ], true) || $from === DocumentSigningWorkflowStatus::NeedsReview;

        if ($requiresReason && trim((string) $context->reasonCode) === '') {
            throw new EsignInvariantViolationException('workflow_transition_reason_required');
        }
    }

    private function eventType(
        DocumentSigningWorkflowStatus $from,
        DocumentSigningWorkflowStatus $target,
    ): DocumentSigningWorkflowEventType {
        if ($from === DocumentSigningWorkflowStatus::NeedsReview) {
            return DocumentSigningWorkflowEventType::ReviewResolved;
        }

        return match ($target) {
            DocumentSigningWorkflowStatus::Active => DocumentSigningWorkflowEventType::WorkflowActivated,
            DocumentSigningWorkflowStatus::Completed => DocumentSigningWorkflowEventType::WorkflowCompleted,
            DocumentSigningWorkflowStatus::Rejected => DocumentSigningWorkflowEventType::WorkflowRejected,
            DocumentSigningWorkflowStatus::NeedsReview => DocumentSigningWorkflowEventType::ReviewRequired,
            DocumentSigningWorkflowStatus::Draft => DocumentSigningWorkflowEventType::ReviewResolved,
        };
    }
}
