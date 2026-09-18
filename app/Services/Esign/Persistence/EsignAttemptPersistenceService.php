<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\CreateEsignAttemptData;
use App\Data\Esign\EsignTransitionContext;
use App\Enums\Esign\DocumentArtifactType;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Enums\Esign\EsignAttemptEventType;
use App\Enums\Esign\EsignAttemptStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Exceptions\Esign\EsignStateTransitionException;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignAttemptEvent;
use App\Models\Esign\EsignProviderResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EsignAttemptPersistenceService
{
    public function __construct(
        private DocumentSigningStepTransitionService $stepTransitions,
        private DocumentSigningWorkflowTransitionService $workflowTransitions,
    ) {}

    public function create(CreateEsignAttemptData $data): EsignAttempt
    {
        $this->assertUuid($data->idempotencyKey, 'attempt_idempotency_key_invalid');
        $this->assertUuid($data->requestCorrelationId, 'attempt_correlation_id_invalid');
        $this->assertSha256($data->requestFingerprint, 'attempt_request_fingerprint_invalid');

        if ($data->previewArtifactSha256 !== null) {
            $this->assertSha256($data->previewArtifactSha256, 'attempt_preview_hash_invalid');
        }

        if (trim($data->provider) === '') {
            throw new EsignInvariantViolationException('attempt_provider_required');
        }

        $stepSnapshot = DocumentSigningStep::query()
            ->select(['id', 'document_signing_workflow_id'])
            ->findOrFail($data->documentSigningStepId);
        $workflowId = (int) $stepSnapshot->document_signing_workflow_id;

        return DB::transaction(function () use ($data, $workflowId): EsignAttempt {
            /** @var DocumentSigningWorkflow $workflow */
            $workflow = DocumentSigningWorkflow::query()
                ->lockForUpdate()
                ->findOrFail($workflowId);
            /** @var DocumentSigningStep $step */
            $step = DocumentSigningStep::query()
                ->lockForUpdate()
                ->findOrFail($data->documentSigningStepId);
            /** @var DocumentArtifact $sourceArtifact */
            $sourceArtifact = DocumentArtifact::query()
                ->lockForUpdate()
                ->findOrFail($data->sourceArtifactId);

            if ((int) $step->document_signing_workflow_id !== $workflowId) {
                throw new EsignInvariantViolationException('attempt_step_workflow_changed');
            }

            $existingAttempt = EsignAttempt::query()
                ->where('idempotency_key', $data->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingAttempt instanceof EsignAttempt) {
                $this->assertIdempotentReplayMatches($existingAttempt, $data, $workflow);

                return $existingAttempt;
            }

            $this->assertCreateInvariants($workflow, $step, $sourceArtifact);

            $hasBlockingAttempt = $step->attempts()
                ->whereIn('status', [
                    EsignAttemptStatus::Prepared->value,
                    EsignAttemptStatus::Signing->value,
                    EsignAttemptStatus::Validating->value,
                    EsignAttemptStatus::Unknown->value,
                ])
                ->exists();

            if ($hasBlockingAttempt) {
                throw new EsignInvariantViolationException('step_has_blocking_attempt');
            }

            $attemptNumber = ((int) $step->attempts()->max('attempt_number')) + 1;
            $attempt = EsignAttempt::query()->create([
                'public_id' => (string) Str::uuid(),
                'idempotency_key' => $data->idempotencyKey,
                'request_correlation_id' => $data->requestCorrelationId,
                'document_id' => $workflow->document_id,
                'document_signing_step_id' => $step->getKey(),
                'attempt_number' => $attemptNumber,
                'source_artifact_id' => $sourceArtifact->getKey(),
                'actor_user_id' => $data->actorUserId,
                'actor_user_position_id' => $data->actorUserPositionId,
                'signer_user_id' => $data->signerUserId,
                'signer_user_position_id' => $data->signerUserPositionId,
                'is_acting' => $data->isActing,
                'effective_role_code' => $data->effectiveRoleCode,
                'effective_unit_kerja_id' => $data->effectiveUnitKerjaId,
                'effective_instansi_id' => $data->effectiveInstansiId,
                'actor_context_snapshot' => $data->actorContextSnapshot,
                'provider' => $data->provider,
                'request_fingerprint' => Str::lower($data->requestFingerprint),
                'source_artifact_sha256' => $sourceArtifact->file_sha256,
                'preview_artifact_sha256' => $data->previewArtifactSha256 === null
                    ? null
                    : Str::lower($data->previewArtifactSha256),
                'status' => EsignAttemptStatus::Prepared,
            ]);

            $context = new EsignTransitionContext(
                actorUserId: $data->actorUserId,
                actorUserPositionId: $data->actorUserPositionId,
                actorIsActing: $data->isActing,
                correlationId: $data->requestCorrelationId,
            );

            $this->recordAttemptEvent(
                attempt: $attempt,
                eventType: EsignAttemptEventType::AttemptPrepared,
                from: null,
                to: EsignAttemptStatus::Prepared,
                context: $context,
            );

            $this->stepTransitions->transition(
                $step,
                DocumentSigningStepStatus::Signing,
                $context,
            );

            return $attempt;
        }, attempts: 3);
    }

    public function attachResultArtifact(
        EsignAttempt|int $attempt,
        DocumentArtifact|int $artifact,
        EsignTransitionContext $context = new EsignTransitionContext,
    ): EsignAttempt {
        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;
        $artifactId = $artifact instanceof DocumentArtifact ? (int) $artifact->getKey() : $artifact;
        [$workflowId, $stepId] = $this->resolveParentIds($attemptId);

        return DB::transaction(function () use (
            $attemptId,
            $artifactId,
            $workflowId,
            $stepId,
            $context,
        ): EsignAttempt {
            DocumentSigningWorkflow::query()->lockForUpdate()->findOrFail($workflowId);
            DocumentSigningStep::query()->lockForUpdate()->findOrFail($stepId);
            /** @var EsignAttempt $lockedAttempt */
            $lockedAttempt = EsignAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            /** @var DocumentArtifact $lockedArtifact */
            $lockedArtifact = DocumentArtifact::query()->lockForUpdate()->findOrFail($artifactId);

            $this->assertLockedAttemptParents($lockedAttempt, $workflowId, $stepId);

            if ($lockedAttempt->result_artifact_id !== null) {
                if ((int) $lockedAttempt->result_artifact_id === $artifactId) {
                    return $lockedAttempt;
                }

                throw new EsignInvariantViolationException('attempt_result_artifact_immutable');
            }

            if (! in_array($lockedAttempt->status, [
                EsignAttemptStatus::Signing,
                EsignAttemptStatus::Validating,
            ], true)) {
                throw new EsignInvariantViolationException('attempt_result_artifact_status_invalid');
            }

            if (! in_array($lockedArtifact->artifact_type, [
                DocumentArtifactType::AfterSign,
                DocumentArtifactType::FailedOutput,
            ], true)
                || $lockedArtifact->document_id !== $lockedAttempt->document_id
                || (int) $lockedArtifact->parent_artifact_id !== (int) $lockedAttempt->source_artifact_id) {
                throw new EsignInvariantViolationException('attempt_result_artifact_mismatch');
            }

            $lockedAttempt->result_artifact_id = $lockedArtifact->getKey();
            $lockedAttempt->save();

            $this->recordAttemptEvent(
                attempt: $lockedAttempt,
                eventType: EsignAttemptEventType::OutputReceived,
                from: $lockedAttempt->status,
                to: $lockedAttempt->status,
                context: $context,
            );

            return $lockedAttempt;
        }, attempts: 3);
    }

    public function transition(
        EsignAttempt|int $attempt,
        EsignAttemptStatus $target,
        EsignTransitionContext $context = new EsignTransitionContext,
    ): EsignAttempt {
        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;
        [$workflowId, $stepId] = $this->resolveParentIds($attemptId);

        return DB::transaction(function () use (
            $attemptId,
            $workflowId,
            $stepId,
            $target,
            $context,
        ): EsignAttempt {
            /** @var DocumentSigningWorkflow $workflow */
            $workflow = DocumentSigningWorkflow::query()->lockForUpdate()->findOrFail($workflowId);
            /** @var DocumentSigningStep $step */
            $step = DocumentSigningStep::query()->lockForUpdate()->findOrFail($stepId);
            /** @var EsignAttempt $lockedAttempt */
            $lockedAttempt = EsignAttempt::query()->lockForUpdate()->findOrFail($attemptId);

            $this->assertLockedAttemptParents($lockedAttempt, $workflowId, $stepId);
            $this->assertDocumentInvariant($lockedAttempt, $workflow, $step);
            $from = $lockedAttempt->status;

            if ($from === $target) {
                return $lockedAttempt;
            }

            if (! $from->canTransitionTo($target)) {
                throw new EsignStateTransitionException('esign_attempt', $from->value, $target->value);
            }

            if (in_array($target, [EsignAttemptStatus::Failed, EsignAttemptStatus::Unknown], true)
                && trim((string) $context->applicationErrorCode) === '') {
                throw new EsignInvariantViolationException('attempt_error_code_required');
            }

            if ($context->providerResponseId !== null) {
                $providerResponse = EsignProviderResponse::query()
                    ->whereKey($context->providerResponseId)
                    ->where('esign_attempt_id', $lockedAttempt->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $providerResponse instanceof EsignProviderResponse) {
                    throw new EsignInvariantViolationException('attempt_provider_response_mismatch');
                }

                $providerResponseAlreadyRecorded = $lockedAttempt->events()
                    ->where('esign_provider_response_id', $context->providerResponseId)
                    ->where('event_type', EsignAttemptEventType::ProviderResponded->value)
                    ->exists();

                if (! $providerResponseAlreadyRecorded) {
                    $this->recordAttemptEvent(
                        attempt: $lockedAttempt,
                        eventType: EsignAttemptEventType::ProviderResponded,
                        from: $from,
                        to: $from,
                        context: $context,
                    );
                }
            }

            if ($target === EsignAttemptStatus::Succeeded) {
                $this->assertSucceededInvariant($lockedAttempt);
            }

            $lockedAttempt->status = $target;

            if ($target === EsignAttemptStatus::Signing) {
                $lockedAttempt->started_at ??= now();
                $lockedAttempt->request_sent_at ??= now();
            }

            if ($target === EsignAttemptStatus::Validating) {
                $lockedAttempt->response_received_at ??= now();
            }

            if (in_array($target, [EsignAttemptStatus::Failed, EsignAttemptStatus::Unknown], true)) {
                $lockedAttempt->application_error_code = $context->applicationErrorCode;
                $lockedAttempt->retryable = $target === EsignAttemptStatus::Failed && $context->retryable;
                $lockedAttempt->safe_error_context = $context->metadata;
            }

            if ($target->isTerminal()) {
                $lockedAttempt->completed_at = now();
            }

            $lockedAttempt->save();

            $this->recordAttemptEvent(
                attempt: $lockedAttempt,
                eventType: $this->eventType($from, $target),
                from: $from,
                to: $target,
                context: $context,
            );

            $this->synchronizeStepAndWorkflow($lockedAttempt, $step, $workflow, $target, $context);

            return $lockedAttempt;
        }, attempts: 3);
    }

    /** @return array{int, int} */
    private function resolveParentIds(int $attemptId): array
    {
        $attempt = EsignAttempt::query()
            ->select(['id', 'document_signing_step_id'])
            ->findOrFail($attemptId);
        $step = DocumentSigningStep::query()
            ->select(['id', 'document_signing_workflow_id'])
            ->findOrFail($attempt->document_signing_step_id);

        return [(int) $step->document_signing_workflow_id, (int) $step->getKey()];
    }

    private function assertCreateInvariants(
        DocumentSigningWorkflow $workflow,
        DocumentSigningStep $step,
        DocumentArtifact $sourceArtifact,
    ): void {
        if ($workflow->status !== DocumentSigningWorkflowStatus::Active) {
            throw new EsignInvariantViolationException('attempt_requires_active_workflow');
        }

        if ($step->status !== DocumentSigningStepStatus::Active) {
            throw new EsignInvariantViolationException('attempt_requires_active_step');
        }

        if ((int) $workflow->current_sequence !== (int) $step->sequence) {
            throw new EsignInvariantViolationException('attempt_step_is_not_current_workflow_step');
        }

        if ((int) $step->source_artifact_id !== (int) $sourceArtifact->getKey()) {
            throw new EsignInvariantViolationException('attempt_source_artifact_not_bound_to_step');
        }

        if ((int) $workflow->current_artifact_id !== (int) $sourceArtifact->getKey()) {
            throw new EsignInvariantViolationException('attempt_source_artifact_not_current_workflow_artifact');
        }

        if ($sourceArtifact->document_id === null
            || $sourceArtifact->document_id !== $workflow->document_id) {
            throw new EsignInvariantViolationException('attempt_source_document_mismatch');
        }

        if (! $sourceArtifact->is_current
            || ! in_array($sourceArtifact->artifact_type, [
                DocumentArtifactType::BeforeSign,
                DocumentArtifactType::AfterSign,
            ], true)) {
            throw new EsignInvariantViolationException('attempt_source_artifact_not_current');
        }
    }

    private function assertIdempotentReplayMatches(
        EsignAttempt $attempt,
        CreateEsignAttemptData $data,
        DocumentSigningWorkflow $workflow,
    ): void {
        if ((int) $attempt->document_signing_step_id !== $data->documentSigningStepId
            || (int) $attempt->source_artifact_id !== $data->sourceArtifactId
            || $attempt->document_id !== $workflow->document_id
            || ! hash_equals($attempt->request_fingerprint, Str::lower($data->requestFingerprint))) {
            throw new EsignInvariantViolationException('attempt_idempotency_key_payload_mismatch');
        }
    }

    private function assertLockedAttemptParents(EsignAttempt $attempt, int $workflowId, int $stepId): void
    {
        if ((int) $attempt->document_signing_step_id !== $stepId) {
            throw new EsignInvariantViolationException('attempt_step_changed_during_transition');
        }

        $actualWorkflowId = (int) DocumentSigningStep::query()
            ->whereKey($stepId)
            ->valueOrFail('document_signing_workflow_id');

        if ($actualWorkflowId !== $workflowId) {
            throw new EsignInvariantViolationException('attempt_workflow_changed_during_transition');
        }
    }

    private function assertDocumentInvariant(
        EsignAttempt $attempt,
        DocumentSigningWorkflow $workflow,
        DocumentSigningStep $step,
    ): void {
        $sourceArtifact = DocumentArtifact::query()->findOrFail($attempt->source_artifact_id);

        if ($attempt->document_id === null
            || $attempt->document_id !== $workflow->document_id
            || $sourceArtifact->document_id !== $attempt->document_id
            || (int) $step->source_artifact_id !== (int) $attempt->source_artifact_id) {
            throw new EsignInvariantViolationException('attempt_document_invariant_mismatch');
        }

        if ($attempt->result_artifact_id !== null) {
            $resultArtifact = DocumentArtifact::query()->findOrFail($attempt->result_artifact_id);

            if ($resultArtifact->document_id !== $attempt->document_id
                || (int) $resultArtifact->parent_artifact_id !== (int) $attempt->source_artifact_id) {
                throw new EsignInvariantViolationException('attempt_result_document_mismatch');
            }
        }
    }

    private function assertSucceededInvariant(EsignAttempt $attempt): void
    {
        if ($attempt->result_artifact_id === null) {
            throw new EsignInvariantViolationException('succeeded_attempt_result_artifact_required');
        }

        $resultArtifact = DocumentArtifact::query()->findOrFail($attempt->result_artifact_id);

        if ($resultArtifact->artifact_type !== DocumentArtifactType::AfterSign
            || $resultArtifact->document_id !== $attempt->document_id) {
            throw new EsignInvariantViolationException('succeeded_attempt_result_artifact_invalid');
        }
    }

    private function synchronizeStepAndWorkflow(
        EsignAttempt $attempt,
        DocumentSigningStep $step,
        DocumentSigningWorkflow $workflow,
        EsignAttemptStatus $target,
        EsignTransitionContext $context,
    ): void {
        if ($target === EsignAttemptStatus::Failed) {
            $this->stepTransitions->transition($step, DocumentSigningStepStatus::Active, $context);

            return;
        }

        if ($target === EsignAttemptStatus::Unknown) {
            $this->stepTransitions->transition(
                $step,
                DocumentSigningStepStatus::ReconciliationRequired,
                $context,
            );

            return;
        }

        if ($target !== EsignAttemptStatus::Succeeded) {
            return;
        }

        $this->promoteResultArtifact($attempt, $workflow);

        $step->result_artifact_id = $attempt->result_artifact_id;
        $step->save();
        $completedStep = $this->stepTransitions->transition(
            $step,
            DocumentSigningStepStatus::Completed,
            $context,
        );

        $nextStep = $workflow->steps()
            ->where('sequence', '>', $completedStep->sequence)
            ->where('status', DocumentSigningStepStatus::Pending->value)
            ->orderBy('sequence')
            ->lockForUpdate()
            ->first();

        if ($nextStep instanceof DocumentSigningStep) {
            $nextStep->source_artifact_id = $attempt->result_artifact_id;
            $nextStep->save();
            $this->stepTransitions->transition(
                $nextStep,
                DocumentSigningStepStatus::Active,
                $context,
            );

            return;
        }

        $this->workflowTransitions->transition(
            $workflow,
            DocumentSigningWorkflowStatus::Completed,
            $context,
        );
    }

    private function promoteResultArtifact(
        EsignAttempt $attempt,
        DocumentSigningWorkflow $workflow,
    ): void {
        $artifacts = DocumentArtifact::query()
            ->where('document_id', $attempt->document_id)
            ->where(function (Builder $query) use ($attempt): void {
                $query->where('is_current', true)
                    ->orWhere('id', $attempt->result_artifact_id);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var DocumentArtifact|null $resultArtifact */
        $resultArtifact = $artifacts->firstWhere('id', $attempt->result_artifact_id);
        $currentArtifacts = $artifacts->where('is_current', true);

        if (! $resultArtifact instanceof DocumentArtifact
            || $resultArtifact->artifact_type !== DocumentArtifactType::AfterSign
            || (int) $resultArtifact->parent_artifact_id !== (int) $attempt->source_artifact_id) {
            throw new EsignInvariantViolationException('result_artifact_promotion_invalid');
        }

        if ($currentArtifacts->count() !== 1
            || (int) $currentArtifacts->first()->getKey() !== (int) $attempt->source_artifact_id) {
            throw new EsignInvariantViolationException('source_artifact_is_not_unique_current');
        }

        /** @var DocumentArtifact $sourceArtifact */
        $sourceArtifact = $currentArtifacts->first();
        $sourceArtifact->is_current = false;
        $sourceArtifact->save();

        $resultArtifact->is_current = true;
        $resultArtifact->save();

        $workflow->current_artifact_id = $resultArtifact->getKey();
        $workflow->lock_version = ((int) $workflow->lock_version) + 1;
        $workflow->save();
    }

    private function eventType(
        EsignAttemptStatus $from,
        EsignAttemptStatus $target,
    ): EsignAttemptEventType {
        if ($from === EsignAttemptStatus::Unknown) {
            return EsignAttemptEventType::ReconciliationResolved;
        }

        return match ($target) {
            EsignAttemptStatus::Signing => EsignAttemptEventType::RequestStarted,
            EsignAttemptStatus::Validating => EsignAttemptEventType::ValidationStarted,
            EsignAttemptStatus::Succeeded => EsignAttemptEventType::AttemptSucceeded,
            EsignAttemptStatus::Failed => EsignAttemptEventType::AttemptFailed,
            EsignAttemptStatus::Unknown => EsignAttemptEventType::OutcomeUnknown,
            EsignAttemptStatus::Prepared => EsignAttemptEventType::AttemptPrepared,
        };
    }

    private function recordAttemptEvent(
        EsignAttempt $attempt,
        EsignAttemptEventType $eventType,
        ?EsignAttemptStatus $from,
        EsignAttemptStatus $to,
        EsignTransitionContext $context,
    ): void {
        $correlationId = $context->correlationId ?? $attempt->request_correlation_id;
        $this->assertUuid($correlationId, 'attempt_event_correlation_id_invalid');

        EsignAttemptEvent::query()->create([
            'event_uuid' => (string) Str::uuid(),
            'esign_attempt_id' => $attempt->getKey(),
            'esign_provider_response_id' => $context->providerResponseId,
            'event_type' => $eventType,
            'from_status' => $from,
            'to_status' => $to,
            'result' => match ($to) {
                EsignAttemptStatus::Failed => 'failed',
                EsignAttemptStatus::Unknown => 'unknown',
                default => 'success',
            },
            'actor_user_id' => $context->actorUserId,
            'actor_user_position_id' => $context->actorUserPositionId,
            'reason_code' => $context->reasonCode,
            'message' => $context->message,
            'correlation_id' => $correlationId,
            'safe_metadata' => $context->metadata,
            'occurred_at' => now(),
        ]);
    }

    private function assertUuid(string $value, string $invariantCode): void
    {
        if (! Str::isUuid($value)) {
            throw new EsignInvariantViolationException($invariantCode);
        }
    }

    private function assertSha256(string $value, string $invariantCode): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/i', $value) !== 1) {
            throw new EsignInvariantViolationException($invariantCode);
        }
    }
}
