<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\EsignTransitionContext;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowEventType;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Exceptions\Esign\EsignStateTransitionException;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\DocumentSigningWorkflowEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentSigningStepTransitionService
{
    public function __construct(
        private DocumentSigningWorkflowTransitionService $workflowTransitions,
    ) {}

    public function transition(
        DocumentSigningStep|int $step,
        DocumentSigningStepStatus $target,
        EsignTransitionContext $context = new EsignTransitionContext,
    ): DocumentSigningStep {
        $stepId = $step instanceof DocumentSigningStep ? (int) $step->getKey() : $step;
        $workflowId = (int) DocumentSigningStep::query()
            ->whereKey($stepId)
            ->valueOrFail('document_signing_workflow_id');

        return DB::transaction(function () use ($stepId, $workflowId, $target, $context): DocumentSigningStep {
            /** @var DocumentSigningWorkflow $workflow */
            $workflow = DocumentSigningWorkflow::query()
                ->lockForUpdate()
                ->findOrFail($workflowId);
            /** @var DocumentSigningStep $lockedStep */
            $lockedStep = DocumentSigningStep::query()
                ->lockForUpdate()
                ->findOrFail($stepId);

            if ((int) $lockedStep->document_signing_workflow_id !== $workflowId) {
                throw new EsignInvariantViolationException('step_workflow_changed_during_transition');
            }

            $from = $lockedStep->status;

            if ($from === $target) {
                return $lockedStep;
            }

            if (! $from->canTransitionTo($target)) {
                throw new EsignStateTransitionException('document_signing_step', $from->value, $target->value);
            }

            $this->assertReasonWhenRequired($from, $target, $context);

            if ($from === DocumentSigningStepStatus::NeedsReview) {
                $workflowRecoveryTarget = $target === DocumentSigningStepStatus::Pending
                    ? DocumentSigningWorkflowStatus::Draft
                    : DocumentSigningWorkflowStatus::Active;

                if ($workflow->status === DocumentSigningWorkflowStatus::NeedsReview) {
                    $workflow = $this->workflowTransitions->transition(
                        $workflow,
                        $workflowRecoveryTarget,
                        $context,
                    );
                }
            }

            $this->assertTransitionInvariants($lockedStep, $workflow, $target);

            $lockedStep->status = $target;

            if ($target === DocumentSigningStepStatus::Active) {
                $lockedStep->activated_at ??= now();
            }

            if ($target === DocumentSigningStepStatus::Completed) {
                $lockedStep->completed_at = now();
            }

            if ($target === DocumentSigningStepStatus::Rejected) {
                $lockedStep->rejected_at = now();
            }

            $lockedStep->save();

            if (in_array($target, [
                DocumentSigningStepStatus::Active,
                DocumentSigningStepStatus::Signing,
                DocumentSigningStepStatus::ReconciliationRequired,
            ], true)) {
                $workflow->current_sequence = $lockedStep->sequence;
            }

            $workflow->lock_version = ((int) $workflow->lock_version) + 1;
            $workflow->save();

            DocumentSigningWorkflowEvent::query()->create([
                'public_id' => (string) Str::uuid(),
                'document_signing_workflow_id' => $workflow->getKey(),
                'document_signing_step_id' => $lockedStep->getKey(),
                'event_type' => $this->eventType($from, $target),
                'from_step_status' => $from,
                'to_step_status' => $target,
                'actor_user_id' => $context->actorUserId,
                'actor_user_position_id' => $context->actorUserPositionId,
                'actor_is_acting' => $context->actorIsActing,
                'reason_code' => $context->reasonCode,
                'message' => $context->message,
                'correlation_id' => $context->correlationId,
                'metadata' => $context->metadata,
                'occurred_at' => now(),
            ]);

            if ($target === DocumentSigningStepStatus::Rejected) {
                $this->workflowTransitions->transition(
                    $workflow,
                    DocumentSigningWorkflowStatus::Rejected,
                    $context,
                );
            }

            if ($target === DocumentSigningStepStatus::NeedsReview
                && $workflow->status !== DocumentSigningWorkflowStatus::NeedsReview) {
                $this->workflowTransitions->transition(
                    $workflow,
                    DocumentSigningWorkflowStatus::NeedsReview,
                    $context,
                );
            }

            return $lockedStep;
        }, attempts: 3);
    }

    private function assertTransitionInvariants(
        DocumentSigningStep $step,
        DocumentSigningWorkflow $workflow,
        DocumentSigningStepStatus $target,
    ): void {
        if (in_array($target, [
            DocumentSigningStepStatus::Active,
            DocumentSigningStepStatus::Signing,
            DocumentSigningStepStatus::ReconciliationRequired,
            DocumentSigningStepStatus::Completed,
            DocumentSigningStepStatus::Rejected,
        ], true)
            && $workflow->status !== DocumentSigningWorkflowStatus::Active) {
            throw new EsignInvariantViolationException('step_requires_active_workflow');
        }

        if ($target === DocumentSigningStepStatus::Active) {
            $hasIncompleteEarlierStep = $workflow->steps()
                ->where('sequence', '<', $step->sequence)
                ->whereNotIn('status', [
                    DocumentSigningStepStatus::Completed->value,
                    DocumentSigningStepStatus::Skipped->value,
                ])
                ->exists();

            if ($hasIncompleteEarlierStep) {
                throw new EsignInvariantViolationException('step_sequence_has_incomplete_predecessor');
            }

        }

        if (in_array($target, [
            DocumentSigningStepStatus::Active,
            DocumentSigningStepStatus::Signing,
            DocumentSigningStepStatus::ReconciliationRequired,
        ], true)) {
            $hasOtherActiveStep = $workflow->steps()
                ->whereKeyNot($step->getKey())
                ->whereIn('status', [
                    DocumentSigningStepStatus::Active->value,
                    DocumentSigningStepStatus::Signing->value,
                    DocumentSigningStepStatus::ReconciliationRequired->value,
                ])
                ->exists();

            if ($hasOtherActiveStep) {
                throw new EsignInvariantViolationException('workflow_has_another_active_step');
            }
        }

        if ($target === DocumentSigningStepStatus::Signing && $step->source_artifact_id === null) {
            throw new EsignInvariantViolationException('signing_step_source_artifact_required');
        }

        if ($target === DocumentSigningStepStatus::Completed
            && $step->step_type === 'sign'
            && $step->result_artifact_id === null) {
            throw new EsignInvariantViolationException('completed_signing_step_result_artifact_required');
        }

        if ($target === DocumentSigningStepStatus::Skipped && $step->is_required) {
            throw new EsignInvariantViolationException('required_step_cannot_be_skipped');
        }
    }

    private function assertReasonWhenRequired(
        DocumentSigningStepStatus $from,
        DocumentSigningStepStatus $target,
        EsignTransitionContext $context,
    ): void {
        $requiresReason = in_array($target, [
            DocumentSigningStepStatus::Rejected,
            DocumentSigningStepStatus::NeedsReview,
        ], true) || $from === DocumentSigningStepStatus::NeedsReview;

        if ($requiresReason && trim((string) $context->reasonCode) === '') {
            throw new EsignInvariantViolationException('step_transition_reason_required');
        }
    }

    private function eventType(
        DocumentSigningStepStatus $from,
        DocumentSigningStepStatus $target,
    ): DocumentSigningWorkflowEventType {
        if ($from === DocumentSigningStepStatus::NeedsReview) {
            return DocumentSigningWorkflowEventType::ReviewResolved;
        }

        return match ($target) {
            DocumentSigningStepStatus::Active => DocumentSigningWorkflowEventType::StepActivated,
            DocumentSigningStepStatus::Signing => DocumentSigningWorkflowEventType::StepSigningStarted,
            DocumentSigningStepStatus::ReconciliationRequired => DocumentSigningWorkflowEventType::StepReconciliationRequired,
            DocumentSigningStepStatus::Completed => DocumentSigningWorkflowEventType::StepCompleted,
            DocumentSigningStepStatus::Rejected => DocumentSigningWorkflowEventType::StepRejected,
            DocumentSigningStepStatus::Skipped => DocumentSigningWorkflowEventType::StepSkipped,
            DocumentSigningStepStatus::NeedsReview => DocumentSigningWorkflowEventType::ReviewRequired,
            DocumentSigningStepStatus::Pending => DocumentSigningWorkflowEventType::ReviewResolved,
        };
    }
}
