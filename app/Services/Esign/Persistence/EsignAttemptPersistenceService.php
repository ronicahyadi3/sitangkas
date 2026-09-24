<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\CreateEsignAttemptData;
use App\Data\Esign\EsignTransitionContext;
use App\Data\Esign\PreparedSigningRenditionData;
use App\Enums\Esign\DocumentArtifactDecorationScope;
use App\Enums\Esign\DocumentArtifactDecorationType;
use App\Enums\Esign\DocumentArtifactType;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Enums\Esign\EsignAttemptEventType;
use App\Enums\Esign\EsignAttemptStatus;
use App\Enums\Esign\EsignSignatureOperationStatus;
use App\Enums\Esign\SignatureDisplayMode;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Exceptions\Esign\EsignStateTransitionException;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentArtifactDecoration;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignAttemptEvent;
use App\Models\Esign\EsignAttemptSignatureProperty;
use App\Models\Esign\EsignProviderResponse;
use App\Models\Esign\EsignSignatureOperation;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EsignAttemptPersistenceService
{
    public function __construct(
        private DocumentSigningStepTransitionService $stepTransitions,
        private DocumentSigningWorkflowTransitionService $workflowTransitions,
        private ConfigRepository $config,
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

        if ($data->plannedSignatureCount < 1 || $data->plannedSignatureCount > 100) {
            throw new EsignInvariantViolationException('attempt_planned_signature_count_invalid');
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
                    EsignAttemptStatus::PartiallySigned->value,
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
                'planned_signature_count' => $data->plannedSignatureCount,
                'completed_signature_count' => 0,
                'current_signature_index' => 0,
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

    /**
     * @param  array<int, array{storage_disk: string, file_path: string, size_bytes: int, sha256: string}>  $visualAssets
     */
    public function persistVisiblePlan(
        EsignAttempt|int $attempt,
        PreparedSigningRenditionData $rendition,
        array $visualAssets,
    ): EsignAttempt {
        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;

        return DB::transaction(function () use ($attemptId, $rendition, $visualAssets): EsignAttempt {
            /** @var EsignAttempt $lockedAttempt */
            $lockedAttempt = EsignAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attemptId);
            /** @var DocumentArtifact $sourceArtifact */
            $sourceArtifact = DocumentArtifact::query()
                ->lockForUpdate()
                ->findOrFail($lockedAttempt->source_artifact_id);
            $operations = array_values($rendition->signatureOperations);

            if ($lockedAttempt->status !== EsignAttemptStatus::Prepared
                || (int) $lockedAttempt->source_artifact_id !== (int) $sourceArtifact->getKey()
                || ! hash_equals((string) $lockedAttempt->source_artifact_sha256, $rendition->sha256)
                || ! hash_equals((string) $lockedAttempt->preview_artifact_sha256, $rendition->sha256)
                || (int) $lockedAttempt->planned_signature_count !== count($operations)
                || $sourceArtifact->artifact_type !== DocumentArtifactType::BeforeSign
                || ! $sourceArtifact->is_current
                || ! hash_equals((string) $sourceArtifact->file_sha256, $rendition->sha256)) {
                throw new EsignInvariantViolationException('visible_attempt_source_invariant_mismatch');
            }

            if ($lockedAttempt->signatureOperations()->exists()
                || $lockedAttempt->signatureProperties()->exists()) {
                $this->assertExistingVisiblePlanMatches($lockedAttempt, $rendition, $visualAssets);

                return $lockedAttempt;
            }

            if (count($visualAssets) !== count($operations)) {
                throw new EsignInvariantViolationException('visible_attempt_visual_count_mismatch');
            }

            foreach ($operations as $operationIndex => $operation) {
                $asset = $visualAssets[$operationIndex] ?? null;

                if (! is_array($asset)
                    || $operation['operation_index'] !== $operationIndex
                    || ! hash_equals($operation['qr_sha256'], $asset['sha256'] ?? '')
                    || (int) $operation['qr_size_bytes'] !== ($asset['size_bytes'] ?? null)) {
                    throw new EsignInvariantViolationException('visible_attempt_visual_mismatch');
                }

                $property = EsignAttemptSignatureProperty::query()->create([
                    'esign_attempt_id' => $lockedAttempt->getKey(),
                    'property_index' => $operationIndex,
                    'display_mode' => SignatureDisplayMode::Visible,
                    'page_number' => $operation['page'],
                    'origin_x' => $operation['origin_x'],
                    'origin_y' => $operation['origin_y'],
                    'width' => $operation['width'],
                    'height' => $operation['height'],
                    'coordinate_origin' => 'top_left',
                    'page_rotation' => $operation['page_rotation'],
                    'location' => $this->configuredProviderText('location', 255),
                    'reason' => $this->configuredProviderText('default_reason', 500),
                    'contact_info' => null,
                    'visual_type' => 'qr_code',
                    'visual_storage_disk' => $asset['storage_disk'],
                    'visual_file_path' => $asset['file_path'],
                    'visual_sha256' => $asset['sha256'],
                    'provider_schema_version' => 'bsre-v2.2.0',
                    'safe_provider_properties' => [
                        'client_id' => $operation['client_id'],
                        'prepared_revision' => $rendition->revision,
                        'renderer_version' => $rendition->rendererVersion,
                        'verification_public_id' => $operation['verification_public_id'],
                        'verification_url' => $operation['verification_url'],
                        'page_width' => $operation['page_width'],
                        'page_height' => $operation['page_height'],
                        'visual_size_bytes' => $asset['size_bytes'],
                        'qr_profile_version' => $operation['qr_profile_version'] ?? 'legacy-unbranded-v1',
                        'qr_logo_sha256' => $operation['qr_logo_sha256'] ?? null,
                    ],
                ]);

                EsignSignatureOperation::query()->create([
                    'public_id' => Str::lower($operation['verification_public_id']),
                    'esign_attempt_id' => $lockedAttempt->getKey(),
                    'esign_attempt_signature_property_id' => $property->getKey(),
                    'operation_index' => $operationIndex,
                    'status' => EsignSignatureOperationStatus::Pending,
                    'input_artifact_id' => $operationIndex === 0
                        ? $sourceArtifact->getKey()
                        : null,
                    'input_sha256' => $operationIndex === 0
                        ? $sourceArtifact->file_sha256
                        : null,
                ]);
            }

            $this->persistFooterSnapshot($lockedAttempt, $sourceArtifact, $rendition);

            return $lockedAttempt->load(['signatureOperations', 'signatureProperties']);
        }, attempts: 3);
    }

    public function startSignatureOperation(
        EsignAttempt|int $attempt,
        EsignSignatureOperation|int $operation,
        string $providerCorrelationId,
        EsignTransitionContext $context,
    ): EsignSignatureOperation {
        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;
        $operationId = $operation instanceof EsignSignatureOperation ? (int) $operation->getKey() : $operation;
        $this->assertUuid($providerCorrelationId, 'signature_operation_correlation_id_invalid');
        [$workflowId, $stepId] = $this->resolveParentIds($attemptId);

        return DB::transaction(function () use (
            $attemptId,
            $operationId,
            $providerCorrelationId,
            $context,
            $workflowId,
            $stepId,
        ): EsignSignatureOperation {
            DocumentSigningWorkflow::query()->lockForUpdate()->findOrFail($workflowId);
            DocumentSigningStep::query()->lockForUpdate()->findOrFail($stepId);
            /** @var EsignAttempt $lockedAttempt */
            $lockedAttempt = EsignAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            /** @var EsignSignatureOperation $lockedOperation */
            $lockedOperation = EsignSignatureOperation::query()->lockForUpdate()->findOrFail($operationId);

            $this->assertOperationContext($lockedAttempt, $lockedOperation);

            if ($lockedAttempt->status !== EsignAttemptStatus::Signing
                || ! in_array($lockedOperation->status, [
                    EsignSignatureOperationStatus::Pending,
                    EsignSignatureOperationStatus::Failed,
                ], true)
                || (int) $lockedOperation->operation_index !== (int) $lockedAttempt->completed_signature_count
                || $lockedOperation->input_artifact_id === null
                || $lockedOperation->input_sha256 === null) {
                throw new EsignInvariantViolationException('signature_operation_start_invariant_mismatch');
            }

            $inputArtifact = DocumentArtifact::query()
                ->lockForUpdate()
                ->findOrFail($lockedOperation->input_artifact_id);

            if ($inputArtifact->document_id !== $lockedAttempt->document_id
                || ! hash_equals((string) $lockedOperation->input_sha256, (string) $inputArtifact->file_sha256)) {
                throw new EsignInvariantViolationException('signature_operation_input_artifact_mismatch');
            }

            $from = $lockedOperation->status;
            $lockedOperation->status = EsignSignatureOperationStatus::Signing;
            $lockedOperation->provider_correlation_id = $providerCorrelationId;
            $lockedOperation->application_error_code = null;
            $lockedOperation->retryable = false;
            $lockedOperation->safe_error_context = null;
            $lockedOperation->failed_at = null;
            $lockedOperation->started_at ??= now();
            $lockedOperation->request_sent_at = now();
            $lockedOperation->save();

            $lockedAttempt->current_signature_index = $lockedOperation->operation_index;
            $lockedAttempt->save();

            $this->recordAttemptEvent(
                attempt: $lockedAttempt,
                eventType: EsignAttemptEventType::SignatureOperationStarted,
                from: $lockedAttempt->status,
                to: $lockedAttempt->status,
                context: $this->operationContext($context, $lockedOperation, [
                    'operation_from_status' => $from->value,
                    'operation_to_status' => EsignSignatureOperationStatus::Signing->value,
                ]),
            );

            return $lockedOperation;
        }, attempts: 3);
    }

    public function markSignatureOperationOutputReceived(
        EsignAttempt|int $attempt,
        EsignSignatureOperation|int $operation,
        DocumentArtifact|int $outputArtifact,
        EsignTransitionContext $context,
    ): EsignSignatureOperation {
        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;
        $operationId = $operation instanceof EsignSignatureOperation ? (int) $operation->getKey() : $operation;
        $artifactId = $outputArtifact instanceof DocumentArtifact ? (int) $outputArtifact->getKey() : $outputArtifact;
        [$workflowId, $stepId] = $this->resolveParentIds($attemptId);

        return DB::transaction(function () use (
            $attemptId,
            $operationId,
            $artifactId,
            $context,
            $workflowId,
            $stepId,
        ): EsignSignatureOperation {
            DocumentSigningWorkflow::query()->lockForUpdate()->findOrFail($workflowId);
            DocumentSigningStep::query()->lockForUpdate()->findOrFail($stepId);
            /** @var EsignAttempt $lockedAttempt */
            $lockedAttempt = EsignAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            /** @var EsignSignatureOperation $lockedOperation */
            $lockedOperation = EsignSignatureOperation::query()->lockForUpdate()->findOrFail($operationId);
            /** @var DocumentArtifact $lockedArtifact */
            $lockedArtifact = DocumentArtifact::query()->lockForUpdate()->findOrFail($artifactId);

            $this->assertOperationContext($lockedAttempt, $lockedOperation);

            if ($lockedOperation->status === EsignSignatureOperationStatus::OutputReceived
                && (int) $lockedOperation->output_artifact_id === $artifactId) {
                return $lockedOperation;
            }

            if ($lockedAttempt->status !== EsignAttemptStatus::Signing
                || $lockedOperation->status !== EsignSignatureOperationStatus::Signing
                || $lockedArtifact->artifact_type !== DocumentArtifactType::IntermediateSign
                || $lockedArtifact->document_id !== $lockedAttempt->document_id
                || (int) $lockedArtifact->parent_artifact_id !== (int) $lockedOperation->input_artifact_id) {
                throw new EsignInvariantViolationException('signature_operation_output_invariant_mismatch');
            }

            if ($context->providerResponseId !== null) {
                $providerResponse = EsignProviderResponse::query()
                    ->whereKey($context->providerResponseId)
                    ->where('esign_attempt_id', $lockedAttempt->getKey())
                    ->where('esign_signature_operation_id', $lockedOperation->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $providerResponse instanceof EsignProviderResponse
                    || (int) $providerResponse->output_artifact_id !== $artifactId) {
                    throw new EsignInvariantViolationException('signature_operation_provider_response_mismatch');
                }
            }

            $lockedOperation->status = EsignSignatureOperationStatus::OutputReceived;
            $lockedOperation->output_artifact_id = $lockedArtifact->getKey();
            $lockedOperation->output_sha256 = $lockedArtifact->file_sha256;
            $lockedOperation->output_received_at = now();
            $lockedOperation->save();

            if ($context->providerResponseId !== null
                && ! $lockedAttempt->events()
                    ->where('esign_provider_response_id', $context->providerResponseId)
                    ->where('event_type', EsignAttemptEventType::ProviderResponded->value)
                    ->exists()) {
                $this->recordAttemptEvent(
                    attempt: $lockedAttempt,
                    eventType: EsignAttemptEventType::ProviderResponded,
                    from: $lockedAttempt->status,
                    to: $lockedAttempt->status,
                    context: $this->operationContext($context, $lockedOperation, [
                        'provider_operation' => 'sign',
                    ]),
                );
            }

            $this->recordAttemptEvent(
                attempt: $lockedAttempt,
                eventType: EsignAttemptEventType::SignatureOperationOutputReceived,
                from: $lockedAttempt->status,
                to: $lockedAttempt->status,
                context: $this->operationContext($context, $lockedOperation, [
                    'output_artifact_id' => $lockedArtifact->getKey(),
                    'operation_to_status' => EsignSignatureOperationStatus::OutputReceived->value,
                ]),
            );

            return $lockedOperation;
        }, attempts: 3);
    }

    public function completeSignatureOperation(
        EsignAttempt|int $attempt,
        EsignSignatureOperation|int $operation,
        EsignTransitionContext $context,
    ): EsignSignatureOperation {
        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;
        $operationId = $operation instanceof EsignSignatureOperation ? (int) $operation->getKey() : $operation;
        [$workflowId, $stepId] = $this->resolveParentIds($attemptId);

        return DB::transaction(function () use (
            $attemptId,
            $operationId,
            $context,
            $workflowId,
            $stepId,
        ): EsignSignatureOperation {
            DocumentSigningWorkflow::query()->lockForUpdate()->findOrFail($workflowId);
            DocumentSigningStep::query()->lockForUpdate()->findOrFail($stepId);
            /** @var EsignAttempt $lockedAttempt */
            $lockedAttempt = EsignAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            /** @var EsignSignatureOperation $lockedOperation */
            $lockedOperation = EsignSignatureOperation::query()->lockForUpdate()->findOrFail($operationId);

            $this->assertOperationContext($lockedAttempt, $lockedOperation);

            if ($lockedOperation->status === EsignSignatureOperationStatus::Completed) {
                return $lockedOperation;
            }

            if ($lockedAttempt->status !== EsignAttemptStatus::Signing
                || $lockedOperation->status !== EsignSignatureOperationStatus::OutputReceived
                || $lockedOperation->output_artifact_id === null
                || $lockedOperation->output_sha256 === null
                || (int) $lockedOperation->operation_index !== (int) $lockedAttempt->completed_signature_count) {
                throw new EsignInvariantViolationException('signature_operation_completion_invariant_mismatch');
            }

            $lockedOperation->status = EsignSignatureOperationStatus::Completed;
            $lockedOperation->completed_at = now();
            $lockedOperation->save();

            $completedCount = (int) $lockedOperation->operation_index + 1;
            $lockedAttempt->completed_signature_count = $completedCount;
            $lockedAttempt->current_signature_index = $completedCount;
            $lockedAttempt->save();

            /** @var EsignSignatureOperation|null $nextOperation */
            $nextOperation = EsignSignatureOperation::query()
                ->where('esign_attempt_id', $lockedAttempt->getKey())
                ->where('operation_index', $completedCount)
                ->lockForUpdate()
                ->first();

            if ($nextOperation instanceof EsignSignatureOperation) {
                if ($nextOperation->status !== EsignSignatureOperationStatus::Pending
                    || $nextOperation->input_artifact_id !== null
                    || $nextOperation->input_sha256 !== null) {
                    throw new EsignInvariantViolationException('next_signature_operation_input_already_bound');
                }

                $nextOperation->input_artifact_id = $lockedOperation->output_artifact_id;
                $nextOperation->input_sha256 = $lockedOperation->output_sha256;
                $nextOperation->save();
            }

            $this->recordAttemptEvent(
                attempt: $lockedAttempt,
                eventType: EsignAttemptEventType::SignatureOperationCompleted,
                from: $lockedAttempt->status,
                to: $lockedAttempt->status,
                context: $this->operationContext($context, $lockedOperation, [
                    'completed_signature_count' => $completedCount,
                    'operation_to_status' => EsignSignatureOperationStatus::Completed->value,
                ]),
            );

            return $lockedOperation;
        }, attempts: 3);
    }

    public function closeSignatureOperation(
        EsignAttempt|int $attempt,
        EsignSignatureOperation|int $operation,
        EsignSignatureOperationStatus $target,
        EsignTransitionContext $context,
    ): EsignSignatureOperation {
        if (! in_array($target, [
            EsignSignatureOperationStatus::Failed,
            EsignSignatureOperationStatus::Unknown,
        ], true) || trim((string) $context->applicationErrorCode) === '') {
            throw new EsignInvariantViolationException('signature_operation_close_context_invalid');
        }

        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;
        $operationId = $operation instanceof EsignSignatureOperation ? (int) $operation->getKey() : $operation;
        [$workflowId, $stepId] = $this->resolveParentIds($attemptId);

        return DB::transaction(function () use (
            $attemptId,
            $operationId,
            $target,
            $context,
            $workflowId,
            $stepId,
        ): EsignSignatureOperation {
            DocumentSigningWorkflow::query()->lockForUpdate()->findOrFail($workflowId);
            DocumentSigningStep::query()->lockForUpdate()->findOrFail($stepId);
            /** @var EsignAttempt $lockedAttempt */
            $lockedAttempt = EsignAttempt::query()->lockForUpdate()->findOrFail($attemptId);
            /** @var EsignSignatureOperation $lockedOperation */
            $lockedOperation = EsignSignatureOperation::query()->lockForUpdate()->findOrFail($operationId);

            $this->assertOperationContext($lockedAttempt, $lockedOperation);

            if (! $lockedOperation->status->canTransitionTo($target)) {
                throw new EsignStateTransitionException(
                    'esign_signature_operation',
                    $lockedOperation->status->value,
                    $target->value,
                );
            }

            $lockedOperation->status = $target;
            $lockedOperation->application_error_code = $context->applicationErrorCode;
            $lockedOperation->retryable = $target === EsignSignatureOperationStatus::Failed
                && $context->retryable;
            $lockedOperation->safe_error_context = $context->metadata;
            $lockedOperation->failed_at = now();
            $lockedOperation->save();

            $this->recordAttemptEvent(
                attempt: $lockedAttempt,
                eventType: $target === EsignSignatureOperationStatus::Unknown
                    ? EsignAttemptEventType::SignatureOperationOutcomeUnknown
                    : EsignAttemptEventType::SignatureOperationFailed,
                from: $lockedAttempt->status,
                to: $lockedAttempt->status,
                context: $this->operationContext($context, $lockedOperation, [
                    'operation_to_status' => $target->value,
                ]),
            );

            return $lockedOperation;
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
                || (int) $lockedArtifact->parent_artifact_id !== $this->expectedResultParentId($lockedAttempt)) {
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

            if (in_array($target, [
                EsignAttemptStatus::Failed,
                EsignAttemptStatus::PartiallySigned,
                EsignAttemptStatus::Unknown,
            ], true)
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

                if (! $lockedAttempt->signatureOperations()->exists()) {
                    $lockedAttempt->completed_signature_count = $lockedAttempt->planned_signature_count;
                    $lockedAttempt->current_signature_index = $lockedAttempt->planned_signature_count;
                } else {
                    $lockedAttempt->signatureOperations()
                        ->where('status', EsignSignatureOperationStatus::Completed->value)
                        ->whereNull('public_id_activated_at')
                        ->update(['public_id_activated_at' => now()]);
                }
            }

            $lockedAttempt->status = $target;

            if ($target === EsignAttemptStatus::Signing) {
                $lockedAttempt->started_at ??= now();
                $lockedAttempt->request_sent_at ??= now();

                if ($from === EsignAttemptStatus::PartiallySigned) {
                    $lockedAttempt->application_error_code = null;
                    $lockedAttempt->retryable = false;
                    $lockedAttempt->safe_error_context = null;
                }
            }

            if ($target === EsignAttemptStatus::Validating) {
                $lockedAttempt->response_received_at ??= now();
            }

            if (in_array($target, [
                EsignAttemptStatus::Failed,
                EsignAttemptStatus::PartiallySigned,
                EsignAttemptStatus::Unknown,
            ], true)) {
                $lockedAttempt->application_error_code = $context->applicationErrorCode;
                $lockedAttempt->retryable = in_array($target, [
                    EsignAttemptStatus::Failed,
                    EsignAttemptStatus::PartiallySigned,
                ], true) && $context->retryable;
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

    private function assertOperationContext(
        EsignAttempt $attempt,
        EsignSignatureOperation $operation,
    ): void {
        if ((int) $operation->esign_attempt_id !== (int) $attempt->getKey()
            || (int) $operation->operation_index < 0
            || (int) $operation->operation_index >= (int) $attempt->planned_signature_count) {
            throw new EsignInvariantViolationException('signature_operation_attempt_mismatch');
        }

        $completedPrefixCount = $attempt->signatureOperations()
            ->where('operation_index', '<', $operation->operation_index)
            ->where('status', EsignSignatureOperationStatus::Completed->value)
            ->count();

        if ($completedPrefixCount !== (int) $operation->operation_index) {
            throw new EsignInvariantViolationException('signature_operation_sequence_gap');
        }
    }

    /** @param array<string, mixed> $metadata */
    private function operationContext(
        EsignTransitionContext $context,
        EsignSignatureOperation $operation,
        array $metadata,
    ): EsignTransitionContext {
        return new EsignTransitionContext(
            actorUserId: $context->actorUserId,
            actorUserPositionId: $context->actorUserPositionId,
            actorIsActing: $context->actorIsActing,
            reasonCode: $context->reasonCode,
            message: $context->message,
            correlationId: $context->correlationId,
            metadata: [...($context->metadata ?? []), ...$metadata],
            providerResponseId: $context->providerResponseId,
            applicationErrorCode: $context->applicationErrorCode,
            retryable: $context->retryable,
            signatureOperationId: (int) $operation->getKey(),
        );
    }

    private function expectedResultParentId(EsignAttempt $attempt): int
    {
        /** @var EsignSignatureOperation|null $lastOperation */
        $lastOperation = $attempt->signatureOperations()
            ->where('status', EsignSignatureOperationStatus::Completed->value)
            ->whereNotNull('output_artifact_id')
            ->orderByDesc('operation_index')
            ->first();

        return $lastOperation instanceof EsignSignatureOperation
            ? (int) $lastOperation->output_artifact_id
            : (int) $attempt->source_artifact_id;
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
            || (int) $attempt->planned_signature_count !== $data->plannedSignatureCount
            || ! hash_equals($attempt->request_fingerprint, Str::lower($data->requestFingerprint))) {
            throw new EsignInvariantViolationException('attempt_idempotency_key_payload_mismatch');
        }
    }

    /**
     * @param  array<int, array{storage_disk: string, file_path: string, size_bytes: int, sha256: string}>  $visualAssets
     */
    private function assertExistingVisiblePlanMatches(
        EsignAttempt $attempt,
        PreparedSigningRenditionData $rendition,
        array $visualAssets,
    ): void {
        $properties = $attempt->signatureProperties()->get()->keyBy('property_index');
        $operations = $attempt->signatureOperations()->get()->keyBy('operation_index');

        if ($properties->count() !== count($rendition->signatureOperations)
            || $operations->count() !== count($rendition->signatureOperations)) {
            throw new EsignInvariantViolationException('visible_attempt_plan_incomplete');
        }

        foreach ($rendition->signatureOperations as $operation) {
            $index = $operation['operation_index'];
            /** @var EsignAttemptSignatureProperty|null $property */
            $property = $properties->get($index);
            /** @var EsignSignatureOperation|null $signatureOperation */
            $signatureOperation = $operations->get($index);
            $asset = $visualAssets[$index] ?? null;
            $safeProperties = $property?->safe_provider_properties;

            if (! $property instanceof EsignAttemptSignatureProperty
                || ! $signatureOperation instanceof EsignSignatureOperation
                || ! is_array($asset)
                || ! is_array($safeProperties)
                || $property->display_mode !== SignatureDisplayMode::Visible
                || (int) $property->page_number !== (int) $operation['page']
                || (float) $property->origin_x !== (float) $operation['origin_x']
                || (float) $property->origin_y !== (float) $operation['origin_y']
                || (float) $property->width !== (float) $operation['width']
                || (float) $property->height !== (float) $operation['height']
                || $property->visual_storage_disk !== ($asset['storage_disk'] ?? null)
                || $property->visual_file_path !== ($asset['file_path'] ?? null)
                || ! hash_equals((string) $property->visual_sha256, $asset['sha256'] ?? '')
                || ! hash_equals((string) ($safeProperties['prepared_revision'] ?? ''), $rendition->revision)
                || ! hash_equals((string) $signatureOperation->public_id, Str::lower($operation['verification_public_id']))
                || (int) $signatureOperation->esign_attempt_signature_property_id !== (int) $property->getKey()) {
                throw new EsignInvariantViolationException('visible_attempt_plan_mismatch');
            }
        }

        $footerDecoration = DocumentArtifactDecoration::query()
            ->where('document_artifact_id', $attempt->source_artifact_id)
            ->where('decoration_type', DocumentArtifactDecorationType::Footer->value)
            ->first();

        if (($rendition->footer === null && $footerDecoration instanceof DocumentArtifactDecoration)
            || ($rendition->footer !== null
                && (! $footerDecoration instanceof DocumentArtifactDecoration
                    || ! hash_equals(
                        (string) $footerDecoration->configuration_sha256,
                        (string) ($rendition->footer['configuration_sha256'] ?? ''),
                    )))) {
            throw new EsignInvariantViolationException('visible_attempt_footer_mismatch');
        }
    }

    private function persistFooterSnapshot(
        EsignAttempt $attempt,
        DocumentArtifact $sourceArtifact,
        PreparedSigningRenditionData $rendition,
    ): void {
        $footer = $rendition->footer;

        if ($footer === null) {
            return;
        }

        $configurationSha256 = $footer['configuration_sha256'] ?? null;
        $placements = $footer['placements'] ?? null;

        if (! is_string($configurationSha256)
            || preg_match('/\A[a-f0-9]{64}\z/i', $configurationSha256) !== 1
            || ! is_array($placements)
            || $placements === []) {
            throw new EsignInvariantViolationException('visible_attempt_footer_invalid');
        }

        $decoration = DocumentArtifactDecoration::query()->create([
            'public_id' => (string) Str::uuid(),
            'document_artifact_id' => $sourceArtifact->getKey(),
            'decoration_index' => 0,
            'decoration_type' => DocumentArtifactDecorationType::Footer,
            'text' => $footer['text'],
            'font_key' => $footer['font_key'],
            'font_size_pt' => $footer['font_size_pt'],
            'is_bold' => $footer['is_bold'],
            'is_italic' => $footer['is_italic'],
            'is_underline' => $footer['is_underline'],
            'text_alignment' => 'left',
            'text_color' => '#000000',
            'page_scope' => DocumentArtifactDecorationScope::AllPages,
            'renderer_version' => $rendition->rendererVersion,
            'configuration_sha256' => Str::lower($configurationSha256),
            'created_by_user_id' => $attempt->actor_user_id,
        ]);

        foreach ($placements as $placement) {
            $decoration->placements()->create([
                'page_number' => $placement['page'],
                'page_width' => $placement['page_width'],
                'page_height' => $placement['page_height'],
                'origin_x' => $placement['origin_x'],
                'origin_y' => $placement['origin_y'],
                'width' => $placement['width'],
                'height' => $placement['height'],
                'coordinate_origin' => 'top_left',
                'page_rotation' => $placement['page_rotation'],
            ]);
        }
    }

    private function configuredProviderText(string $key, int $maximumLength): ?string
    {
        $value = $this->config->get("services.bsre_esign.{$key}");

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) || mb_strlen($value) > $maximumLength) {
            throw new EsignInvariantViolationException("attempt_provider_{$key}_invalid");
        }

        return $value;
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
                || (int) $resultArtifact->parent_artifact_id !== $this->expectedResultParentId($attempt)) {
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

        $operations = $attempt->signatureOperations()
            ->orderBy('operation_index')
            ->get();

        if ($operations->isNotEmpty()
            && ($operations->count() !== (int) $attempt->planned_signature_count
                || (int) $attempt->completed_signature_count !== (int) $attempt->planned_signature_count
                || $operations->contains(
                    static fn (EsignSignatureOperation $operation): bool => $operation->status !== EsignSignatureOperationStatus::Completed
                        || $operation->output_artifact_id === null,
                ))) {
            throw new EsignInvariantViolationException('succeeded_attempt_operation_progress_invalid');
        }
    }

    private function synchronizeStepAndWorkflow(
        EsignAttempt $attempt,
        DocumentSigningStep $step,
        DocumentSigningWorkflow $workflow,
        EsignAttemptStatus $target,
        EsignTransitionContext $context,
    ): void {
        if ($target === EsignAttemptStatus::PartiallySigned) {
            if ($step->status === DocumentSigningStepStatus::ReconciliationRequired) {
                $this->stepTransitions->transition($step, DocumentSigningStepStatus::Active, $context);
            }

            return;
        }

        if ($target === EsignAttemptStatus::Signing
            && $step->status === DocumentSigningStepStatus::Active) {
            $this->stepTransitions->transition($step, DocumentSigningStepStatus::Signing, $context);

            return;
        }

        if ($target === EsignAttemptStatus::Failed) {
            $this->stepTransitions->transition(
                $step,
                (int) $attempt->completed_signature_count > 0
                    ? DocumentSigningStepStatus::ReconciliationRequired
                    : DocumentSigningStepStatus::Active,
                $context,
            );

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
            || (int) $resultArtifact->parent_artifact_id !== $this->expectedResultParentId($attempt)) {
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

        if ($from === EsignAttemptStatus::PartiallySigned
            && $target === EsignAttemptStatus::Signing) {
            return EsignAttemptEventType::AttemptResumed;
        }

        return match ($target) {
            EsignAttemptStatus::Signing => EsignAttemptEventType::RequestStarted,
            EsignAttemptStatus::PartiallySigned => EsignAttemptEventType::AttemptPartiallySigned,
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
            'esign_signature_operation_id' => $context->signatureOperationId,
            'event_type' => $eventType,
            'from_status' => $from,
            'to_status' => $to,
            'result' => match ($to) {
                EsignAttemptStatus::Failed => 'failed',
                EsignAttemptStatus::PartiallySigned => 'partial',
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
