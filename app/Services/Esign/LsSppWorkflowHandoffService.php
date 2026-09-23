<?php

declare(strict_types=1);

namespace App\Services\Esign;

use App\Data\Esign\EsignTransitionContext;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowEventType;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Exceptions\Esign\LsSppSubmitGateException;
use App\Models\Document;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\DocumentSigningWorkflowEvent;
use App\Models\Esign\EsignAttempt;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Esign\Persistence\DocumentSigningStepTransitionService;
use App\Services\Esign\Persistence\DocumentSigningWorkflowTransitionService;
use App\Services\User\PositionIdentityResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class LsSppWorkflowHandoffService
{
    public function __construct(
        private DocumentSigningStepTransitionService $stepTransitions,
        private DocumentSigningWorkflowTransitionService $workflowTransitions,
        private PositionIdentityResolver $positionIdentityResolver,
    ) {}

    public function prepareInitialStepForSigning(
        DocumentSigningStep $step,
        User $actor,
        UserPosition $realPosition,
        bool $actorIsActing,
    ): DocumentSigningStep {
        $stepId = (int) $step->getKey();
        $workflowId = (int) DocumentSigningStep::query()
            ->whereKey($stepId)
            ->valueOrFail('document_signing_workflow_id');
        $documentId = (int) DocumentSigningWorkflow::query()
            ->whereKey($workflowId)
            ->valueOrFail('document_id');

        return DB::transaction(function () use (
            $stepId,
            $workflowId,
            $documentId,
            $actor,
            $realPosition,
            $actorIsActing,
        ): DocumentSigningStep {
            /** @var Document $document */
            $document = Document::withTrashed()
                ->lockForUpdate()
                ->findOrFail($documentId);
            /** @var DocumentSigningWorkflow $workflow */
            $workflow = DocumentSigningWorkflow::query()
                ->lockForUpdate()
                ->findOrFail($workflowId);
            $steps = DocumentSigningStep::query()
                ->where('document_signing_workflow_id', $workflowId)
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();
            /** @var DocumentSigningStep|null $lockedStep */
            $lockedStep = $steps->firstWhere('id', $stepId);

            if (! $lockedStep instanceof DocumentSigningStep) {
                throw new EsignInvariantViolationException('ls_spp_initial_activation_step_missing');
            }

            if (! $this->isRootLsSppWorkflow($document, $workflow)) {
                return $lockedStep;
            }

            if ($workflow->status !== DocumentSigningWorkflowStatus::Draft
                || $lockedStep->status !== DocumentSigningStepStatus::Pending) {
                return $lockedStep;
            }

            $realPosition->loadMissing('jabatan');
            $actorRole = $this->canonicalRole((string) $realPosition->jabatan?->kode);
            $stepRole = $this->canonicalRole((string) $lockedStep->role_code);

            if ($actorIsActing
                || (int) $realPosition->user_id !== (int) $actor->getKey()
                || (int) $lockedStep->assigned_user_id !== (int) $actor->getKey()
                || (int) $lockedStep->assigned_user_position_id !== (int) $realPosition->getKey()
                || $actorRole !== $stepRole) {
                return $lockedStep;
            }

            if ((int) $lockedStep->document_signing_workflow_id !== $workflowId
                || (int) $lockedStep->sequence !== 1
                || ! in_array($stepRole, ['BP', 'BPP'], true)
                || $workflow->current_sequence !== null
                || (int) $lockedStep->source_artifact_id !== (int) $workflow->current_artifact_id) {
                throw new EsignInvariantViolationException('ls_spp_initial_activation_state_invalid');
            }

            if ($steps->isEmpty()
                || $steps->contains(
                    static fn (DocumentSigningStep $candidate): bool => $candidate->status !== DocumentSigningStepStatus::Pending,
                )
                || $steps->pluck('sequence')->all() !== range(1, $steps->count())) {
                throw new EsignInvariantViolationException('ls_spp_initial_activation_steps_invalid');
            }

            if (EsignAttempt::query()
                ->whereIn('document_signing_step_id', $steps->modelKeys())
                ->exists()) {
                throw new EsignInvariantViolationException('ls_spp_initial_activation_has_attempts');
            }

            $context = new EsignTransitionContext(
                actorUserId: (int) $actor->getKey(),
                actorUserPositionId: (int) $realPosition->getKey(),
                actorIsActing: false,
                reasonCode: 'initial_signing_session_started',
                message: 'Workflow LS SPP diaktifkan saat signer pertama membuka sesi TTE.',
                metadata: [
                    'activation_source' => 'signing_session',
                    'effective_role_code' => $actorRole,
                ],
            );

            $this->workflowTransitions->transition(
                $workflow,
                DocumentSigningWorkflowStatus::Active,
                $context,
            );

            return $this->stepTransitions->transition(
                $lockedStep,
                DocumentSigningStepStatus::Active,
                $context,
            );
        }, attempts: 3);
    }

    public function assignPptkAndActivate(
        Document $document,
        UserPosition $effectiveActorPosition,
        int $selectedPositionId,
        EsignTransitionContext $context,
    ): UserPosition {
        return DB::transaction(function () use (
            $document,
            $effectiveActorPosition,
            $selectedPositionId,
            $context,
        ): UserPosition {
            [$lockedDocument, $workflow, $steps] = $this->lockedHandoffContext($document);
            $previousStep = $this->completedActorStep(
                $lockedDocument,
                $workflow,
                $steps,
                $effectiveActorPosition,
                ['BP', 'BPP'],
            );
            $targetStep = $this->nextStep($steps, $previousStep, 'PPTK');
            $targetPosition = $this->lockedSelectedPosition(
                $selectedPositionId,
                $lockedDocument,
                $workflow,
                'PPTK',
            );

            $this->assignAndActivate(
                $workflow,
                $previousStep,
                $targetStep,
                $targetPosition,
                $effectiveActorPosition,
                $context,
            );

            return $targetPosition;
        }, attempts: 3);
    }

    public function assignHeadAndActivate(
        Document $document,
        UserPosition $effectiveActorPosition,
        EsignTransitionContext $context,
    ): UserPosition {
        return DB::transaction(function () use (
            $document,
            $effectiveActorPosition,
            $context,
        ): UserPosition {
            [$lockedDocument, $workflow, $steps] = $this->lockedHandoffContext($document);
            $previousStep = $this->completedActorStep(
                $lockedDocument,
                $workflow,
                $steps,
                $effectiveActorPosition,
                ['PPTK'],
            );
            $headRole = match (Str::lower((string) $workflow->workflow_variant)) {
                'bp' => 'PA',
                'bpp' => 'KPA',
                default => $this->deny(
                    'canonical_handoff_variant_invalid',
                    'Varian workflow LS SPP tidak dapat menentukan PA/KPA tujuan.',
                ),
            };
            $targetStep = $this->nextStep($steps, $previousStep, $headRole);
            $targetPosition = $this->lockedUniquePositionForRole(
                $lockedDocument,
                $workflow,
                $headRole,
            );

            $this->assignAndActivate(
                $workflow,
                $previousStep,
                $targetStep,
                $targetPosition,
                $effectiveActorPosition,
                $context,
            );

            return $targetPosition;
        }, attempts: 3);
    }

    /**
     * @return array{Document, DocumentSigningWorkflow, Collection<int, DocumentSigningStep>}
     */
    private function lockedHandoffContext(Document $document): array
    {
        /** @var Document $lockedDocument */
        $lockedDocument = Document::query()
            ->lockForUpdate()
            ->findOrFail($document->getKey());
        /** @var DocumentSigningWorkflow|null $workflow */
        $workflow = DocumentSigningWorkflow::query()
            ->where('document_id', $lockedDocument->getKey())
            ->orderByDesc('cycle_number')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $workflow instanceof DocumentSigningWorkflow
            || ! $this->isRootLsSppWorkflow($lockedDocument, $workflow)) {
            $this->deny(
                'canonical_handoff_workflow_invalid',
                'Workflow canonical LS SPP tidak ditemukan atau tidak sesuai.',
            );
        }

        if ($workflow->status !== DocumentSigningWorkflowStatus::Active) {
            $this->deny(
                'canonical_handoff_workflow_not_active',
                'Workflow TTE harus aktif sebelum assignment signer berikutnya.',
            );
        }

        $steps = DocumentSigningStep::query()
            ->where('document_signing_workflow_id', $workflow->getKey())
            ->orderBy('sequence')
            ->lockForUpdate()
            ->get();

        if ($steps->isEmpty()) {
            $this->deny(
                'canonical_handoff_steps_missing',
                'Urutan signer canonical tidak tersedia.',
            );
        }

        return [$lockedDocument, $workflow, $steps];
    }

    /**
     * @param  Collection<int, DocumentSigningStep>  $steps
     * @param  non-empty-list<string>  $allowedRoles
     */
    private function completedActorStep(
        Document $document,
        DocumentSigningWorkflow $workflow,
        Collection $steps,
        UserPosition $actorPosition,
        array $allowedRoles,
    ): DocumentSigningStep {
        $actorPosition->loadMissing('jabatan');
        $actorRole = $this->canonicalRole((string) $actorPosition->jabatan?->kode);

        if (! in_array($actorRole, $allowedRoles, true)) {
            $this->deny(
                'canonical_handoff_actor_role_invalid',
                'Jabatan aktif tidak sesuai dengan tahap handoff canonical.',
            );
        }

        $matchingSteps = $steps->filter(
            fn (DocumentSigningStep $step): bool => $this->canonicalRole((string) $step->role_code) === $actorRole,
        );

        if ($matchingSteps->count() !== 1) {
            $this->deny(
                'canonical_handoff_actor_step_invalid',
                'Step canonical untuk jabatan aktif tidak ditemukan atau ambigu.',
            );
        }

        /** @var DocumentSigningStep $step */
        $step = $matchingSteps->first();

        if ($step->status !== DocumentSigningStepStatus::Completed
            || (int) $step->assigned_user_position_id !== (int) $actorPosition->getKey()
            || $step->result_artifact_id === null
            || (int) $step->result_artifact_id !== (int) $workflow->current_artifact_id
            || (int) $workflow->current_sequence !== (int) $step->sequence
            || (int) $workflow->document_id !== (int) $document->getKey()) {
            $this->deny(
                'canonical_handoff_completed_step_invalid',
                'Step signer aktif belum siap untuk handoff canonical.',
            );
        }

        return $step;
    }

    /** @param Collection<int, DocumentSigningStep> $steps */
    private function nextStep(
        Collection $steps,
        DocumentSigningStep $previousStep,
        string $expectedRole,
    ): DocumentSigningStep {
        /** @var DocumentSigningStep|null $nextStep */
        $nextStep = $steps->first(
            static fn (DocumentSigningStep $step): bool => (int) $step->sequence === (int) $previousStep->sequence + 1,
        );

        if (! $nextStep instanceof DocumentSigningStep
            || $this->canonicalRole((string) $nextStep->role_code) !== $expectedRole) {
            $this->deny(
                'canonical_handoff_next_step_invalid',
                'Step signer tujuan tidak sesuai urutan workflow canonical.',
            );
        }

        return $nextStep;
    }

    private function lockedSelectedPosition(
        int $selectedPositionId,
        Document $document,
        DocumentSigningWorkflow $workflow,
        string $expectedRole,
    ): UserPosition {
        try {
            $canonicalPositionId = $this->positionIdentityResolver->canonicalId($selectedPositionId);
        } catch (LogicException) {
            $this->deny(
                'canonical_handoff_target_position_invalid',
                'Posisi signer yang dipilih tidak valid.',
            );
        }

        /** @var UserPosition|null $position */
        $position = $this->availablePositionQuery($document, $workflow, $expectedRole)
            ->whereKey($canonicalPositionId)
            ->lockForUpdate()
            ->first();

        if (! $position instanceof UserPosition) {
            $this->deny(
                'canonical_handoff_target_position_not_available',
                'PPTK yang dipilih tidak aktif atau tidak berada pada unit kerja dokumen.',
            );
        }

        return $position;
    }

    private function lockedUniquePositionForRole(
        Document $document,
        DocumentSigningWorkflow $workflow,
        string $roleCode,
    ): UserPosition {
        $positions = $this->availablePositionQuery($document, $workflow, $roleCode)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($positions->count() !== 1) {
            $this->deny(
                'canonical_handoff_head_position_ambiguous',
                "Posisi {$roleCode} aktif pada unit kerja dokumen tidak ditemukan atau lebih dari satu.",
            );
        }

        /** @var UserPosition $position */
        $position = $positions->first();

        return $position;
    }

    private function availablePositionQuery(
        Document $document,
        DocumentSigningWorkflow $workflow,
        string $roleCode,
    ): Builder {
        return UserPosition::query()
            ->with(['jabatan', 'user'])
            ->availableForSelection()
            ->withActiveReferences()
            ->where('unit_kerja_id', $document->id_unit_kerja)
            ->when(
                $workflow->instansi_id !== null,
                static fn (Builder $query): Builder => $query->where('instansi_id', $workflow->instansi_id),
            )
            ->whereHas(
                'jabatan',
                static fn (Builder $query): Builder => $query->where('kode', $roleCode),
            )
            ->whereHas(
                'user',
                static fn (Builder $query): Builder => $query->availableForPasswordLogin(),
            );
    }

    private function assignAndActivate(
        DocumentSigningWorkflow $workflow,
        DocumentSigningStep $previousStep,
        DocumentSigningStep $targetStep,
        UserPosition $targetPosition,
        UserPosition $effectiveActorPosition,
        EsignTransitionContext $context,
    ): void {
        $sourceArtifactId = (int) $workflow->current_artifact_id;
        $isIdempotentReplay = $targetStep->status === DocumentSigningStepStatus::Active
            && (int) $targetStep->assigned_user_id === (int) $targetPosition->user_id
            && (int) $targetStep->assigned_user_position_id === (int) $targetPosition->getKey()
            && (int) $targetStep->source_artifact_id === $sourceArtifactId;

        if ($isIdempotentReplay) {
            return;
        }

        if ($targetStep->status !== DocumentSigningStepStatus::Pending
            || $targetStep->attempts()->exists()
            || ($targetStep->source_artifact_id !== null
                && (int) $targetStep->source_artifact_id !== $sourceArtifactId)) {
            $this->deny(
                'canonical_handoff_target_step_not_assignable',
                'Step signer tujuan sudah diproses atau tidak dapat diubah assignment-nya.',
            );
        }

        $targetPosition->loadMissing('jabatan');
        $targetRole = $this->canonicalRole((string) $targetPosition->jabatan?->kode);

        if ($targetRole !== $this->canonicalRole((string) $targetStep->role_code)) {
            $this->deny(
                'canonical_handoff_target_role_mismatch',
                'Jabatan signer tujuan tidak sesuai dengan step canonical.',
            );
        }

        $previousAssignment = [
            'user_id' => $targetStep->assigned_user_id,
            'user_position_id' => $targetStep->assigned_user_position_id,
            'unit_kerja_id' => $targetStep->assigned_unit_kerja_id,
            'instansi_id' => $targetStep->assigned_instansi_id,
        ];
        $assignedAt = now();
        $targetStep->assigned_user_id = $targetPosition->user_id;
        $targetStep->assigned_user_position_id = $targetPosition->getKey();
        $targetStep->assigned_unit_kerja_id = $targetPosition->unit_kerja_id;
        $targetStep->assigned_instansi_id = $targetPosition->instansi_id;
        $targetStep->source_artifact_id = $sourceArtifactId;
        $targetStep->assignment_snapshot = [
            'user_id' => (int) $targetPosition->user_id,
            'user_position_id' => (int) $targetPosition->getKey(),
            'role_code' => $targetRole,
            'unit_kerja_id' => (int) $targetPosition->unit_kerja_id,
            'instansi_id' => (int) $targetPosition->instansi_id,
            'assigned_at' => $assignedAt->toIso8601String(),
            'source' => 'ls_spp_handoff',
        ];
        $stepMetadata = $targetStep->metadata ?? [];
        $stepMetadata['assignment_state'] = 'resolved';
        $stepMetadata['assignment_source'] = 'ls_spp_handoff';
        $stepMetadata['assigned_from_sequence'] = (int) $previousStep->sequence;
        $targetStep->metadata = $stepMetadata;
        $targetStep->save();

        $workflowMetadata = $workflow->metadata ?? [];
        $unresolvedSequences = collect($workflowMetadata['unresolved_sequences'] ?? [])
            ->map(static fn (mixed $sequence): int => (int) $sequence)
            ->reject(static fn (int $sequence): bool => $sequence === (int) $targetStep->sequence)
            ->filter(static fn (int $sequence): bool => $sequence > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $workflowMetadata['unresolved_sequences'] = $unresolvedSequences;
        $workflowMetadata['assignment_state'] = $unresolvedSequences === [] ? 'resolved' : 'partial';
        $workflow->metadata = $workflowMetadata;
        $workflow->save();

        DocumentSigningWorkflowEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'document_signing_workflow_id' => $workflow->getKey(),
            'document_signing_step_id' => $targetStep->getKey(),
            'event_type' => DocumentSigningWorkflowEventType::StepAssigned,
            'from_workflow_status' => $workflow->status,
            'to_workflow_status' => $workflow->status,
            'from_step_status' => DocumentSigningStepStatus::Pending,
            'to_step_status' => DocumentSigningStepStatus::Pending,
            'actor_user_id' => $context->actorUserId,
            'actor_user_position_id' => $context->actorUserPositionId,
            'actor_is_acting' => $context->actorIsActing,
            'reason_code' => 'ls_spp_handoff_assignment',
            'message' => 'Signer canonical berikutnya ditetapkan saat handoff LS SPP.',
            'correlation_id' => $context->correlationId,
            'metadata' => [
                ...($context->metadata ?? []),
                'effective_actor_position_id' => (int) $effectiveActorPosition->getKey(),
                'from_sequence' => (int) $previousStep->sequence,
                'previous_assignment' => $previousAssignment,
                'source_artifact_id' => $sourceArtifactId,
                'target_assignment' => $targetStep->assignment_snapshot,
                'to_sequence' => (int) $targetStep->sequence,
            ],
            'occurred_at' => $assignedAt,
        ]);

        $this->stepTransitions->transition(
            $targetStep,
            DocumentSigningStepStatus::Active,
            $context,
        );
    }

    private function isRootLsSppWorkflow(
        Document $document,
        DocumentSigningWorkflow $workflow,
    ): bool {
        return Str::upper(trim((string) $document->payment_type)) === 'LS'
            && Str::upper(trim((string) $document->src_type)) === Document::TYPE_SPP
            && $document->reference_id === null
            && $document->parent_id === null
            && (int) $workflow->document_id === (int) $document->getKey()
            && (int) $workflow->root_document_id === (int) $document->getKey()
            && Str::upper(trim((string) $workflow->payment_type)) === 'LS'
            && Str::upper(trim((string) $workflow->document_type)) === Document::TYPE_SPP;
    }

    private function canonicalRole(string $roleCode): string
    {
        return Str::of($roleCode)
            ->trim()
            ->upper()
            ->replace('-', '_')
            ->toString();
    }

    private function deny(string $reasonCode, string $message): never
    {
        throw new LsSppSubmitGateException($reasonCode, $message);
    }
}
