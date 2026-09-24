<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\EsignTransitionContext;
use App\Data\Esign\StagedDocumentArtifact;
use App\Enums\Esign\DocumentArtifactType;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowEventType;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Enums\Esign\EsignAttemptStatus;
use App\Exceptions\Esign\EsignArtifactStorageException;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\DocumentSigningWorkflowEvent;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignSignatureOperation;
use App\Support\Esign\DocumentArtifactStoragePath;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

final class DocumentArtifactPersistenceService
{
    public function __construct(
        private FilesystemManager $filesystems,
        private ConfigRepository $config,
        private EsignAttemptPersistenceService $attemptPersistence,
    ) {}

    public function stagePdfContents(
        #[SensitiveParameter] string $contents,
        ?DateTimeInterface $storageDate = null,
    ): StagedDocumentArtifact {
        $temporaryStream = fopen('php://temp/maxmemory:2097152', 'w+b');

        if (! is_resource($temporaryStream)) {
            throw new EsignArtifactStorageException('temporary_stream_open_failed');
        }

        try {
            if (fwrite($temporaryStream, $contents) !== strlen($contents)
                || ! rewind($temporaryStream)) {
                throw new EsignArtifactStorageException('temporary_stream_write_failed');
            }

            return $this->stage($temporaryStream, $storageDate);
        } finally {
            fclose($temporaryStream);
        }
    }

    /** @param resource $stream */
    public function stagePdfStream(
        mixed $stream,
        ?DateTimeInterface $storageDate = null,
    ): StagedDocumentArtifact {
        if (! is_resource($stream)) {
            throw new EsignArtifactStorageException('staging_stream_invalid');
        }

        return $this->stage($stream, $storageDate);
    }

    public function discardStagedArtifact(StagedDocumentArtifact $stagedArtifact): void
    {
        $this->assertStagedArtifact($stagedArtifact);
        $disk = $this->disk();

        if (! $disk->exists($stagedArtifact->stagingPath)) {
            return;
        }

        $this->assertInspectionMatches(
            $stagedArtifact,
            $this->inspectStoredFile(
                $disk,
                $stagedArtifact->stagingPath,
                $stagedArtifact->publicId,
            ),
            'staging_file_changed',
        );

        if (! $disk->delete($stagedArtifact->stagingPath)) {
            throw new EsignArtifactStorageException(
                'staging_delete_failed',
                $stagedArtifact->publicId,
            );
        }
    }

    public function discardUnpersistedSourceArtifact(StagedDocumentArtifact $stagedArtifact): void
    {
        $this->assertStagedArtifact($stagedArtifact);

        if (DocumentArtifact::query()
            ->where('public_id', $stagedArtifact->publicId)
            ->exists()) {
            return;
        }

        $disk = $this->disk();
        $paths = [
            $stagedArtifact->stagingPath,
            $this->finalPath($stagedArtifact, DocumentArtifactType::BeforeSign),
        ];
        $existingPaths = [];

        foreach ($paths as $path) {
            if (! $disk->exists($path)) {
                continue;
            }

            $this->assertInspectionMatches(
                $stagedArtifact,
                $this->inspectStoredFile($disk, $path, $stagedArtifact->publicId),
                'unpersisted_source_file_changed',
            );

            $existingPaths[] = $path;
        }

        foreach ($existingPaths as $path) {
            if (! $disk->delete($path)) {
                throw new EsignArtifactStorageException(
                    'unpersisted_source_delete_failed',
                    $stagedArtifact->publicId,
                );
            }
        }
    }

    /** @param array<string, mixed>|null $metadata */
    public function finalizeSourceArtifact(
        StagedDocumentArtifact $stagedArtifact,
        Document|int $document,
        DocumentArtifact|int|null $parentArtifact = null,
        ?string $originalName = null,
        ?int $createdByUserId = null,
        string $sourceSystem = 'application',
        ?string $sourceReferenceType = null,
        ?string $sourceReferenceId = null,
        ?array $metadata = null,
        ?DocumentSigningWorkflow $replaceableDraftWorkflow = null,
        ?int $actorUserPositionId = null,
        bool $actorIsActing = false,
    ): DocumentArtifact {
        if (! $stagedArtifact->hasPdfHeader || $stagedArtifact->sizeBytes === 0) {
            throw new EsignArtifactStorageException(
                'source_artifact_pdf_invalid',
                $stagedArtifact->publicId,
            );
        }

        return $this->finalizeArtifact(
            stagedArtifact: $stagedArtifact,
            documentId: $document instanceof Document ? (int) $document->getKey() : $document,
            artifactType: DocumentArtifactType::BeforeSign,
            parentArtifactId: $parentArtifact instanceof DocumentArtifact
                ? (int) $parentArtifact->getKey()
                : $parentArtifact,
            makeCurrent: true,
            originalName: $originalName,
            createdByUserId: $createdByUserId,
            sourceSystem: $sourceSystem,
            sourceReferenceType: $sourceReferenceType,
            sourceReferenceId: $sourceReferenceId,
            metadata: $metadata,
            replaceableDraftWorkflowId: $replaceableDraftWorkflow?->getKey(),
            actorUserPositionId: $actorUserPositionId,
            actorIsActing: $actorIsActing,
        );
    }

    /** @param array<string, mixed>|null $metadata */
    public function finalizePreparedSourceArtifact(
        StagedDocumentArtifact $stagedArtifact,
        DocumentSigningWorkflow|int $workflow,
        DocumentSigningStep|int $step,
        DocumentArtifact|int $parentArtifact,
        ?string $originalName = null,
        ?int $createdByUserId = null,
        ?array $metadata = null,
    ): DocumentArtifact {
        if (! $stagedArtifact->hasPdfHeader || $stagedArtifact->sizeBytes === 0) {
            throw new EsignArtifactStorageException(
                'prepared_source_artifact_pdf_invalid',
                $stagedArtifact->publicId,
            );
        }

        $workflowId = $workflow instanceof DocumentSigningWorkflow
            ? (int) $workflow->getKey()
            : $workflow;
        $stepId = $step instanceof DocumentSigningStep
            ? (int) $step->getKey()
            : $step;
        $parentArtifactId = $parentArtifact instanceof DocumentArtifact
            ? (int) $parentArtifact->getKey()
            : $parentArtifact;
        $documentId = $parentArtifact instanceof DocumentArtifact
            ? (int) $parentArtifact->document_id
            : (int) DocumentArtifact::query()->whereKey($parentArtifactId)->valueOrFail('document_id');

        return $this->finalizeArtifact(
            stagedArtifact: $stagedArtifact,
            documentId: $documentId,
            artifactType: DocumentArtifactType::BeforeSign,
            parentArtifactId: $parentArtifactId,
            makeCurrent: true,
            originalName: $originalName,
            createdByUserId: $createdByUserId,
            sourceSystem: 'application',
            sourceReferenceType: 'prepared_signing_rendition',
            sourceReferenceId: is_array($metadata)
                && is_string($metadata['prepared_revision'] ?? null)
                    ? $metadata['prepared_revision']
                    : null,
            metadata: $metadata,
            replaceableActiveWorkflowId: $workflowId,
            replaceableActiveStepId: $stepId,
        );
    }

    /** @param array<string, mixed>|null $metadata */
    public function finalizeAttemptOutputArtifact(
        StagedDocumentArtifact $stagedArtifact,
        EsignAttempt|int $attempt,
        bool $verificationPassed,
        EsignTransitionContext $context = new EsignTransitionContext,
        ?array $metadata = null,
    ): DocumentArtifact {
        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;
        /** @var EsignAttempt $attemptSnapshot */
        $attemptSnapshot = EsignAttempt::query()
            ->select([
                'id',
                'public_id',
                'document_id',
                'source_artifact_id',
            ])
            ->findOrFail($attemptId);

        if ($attemptSnapshot->document_id === null) {
            throw new EsignInvariantViolationException('attempt_output_document_id_required');
        }

        /** @var EsignSignatureOperation|null $lastCompletedOperation */
        $lastCompletedOperation = EsignSignatureOperation::query()
            ->where('esign_attempt_id', $attemptId)
            ->where('status', 'completed')
            ->whereNotNull('output_artifact_id')
            ->orderByDesc('operation_index')
            ->first();
        $parentArtifactId = $lastCompletedOperation instanceof EsignSignatureOperation
            ? (int) $lastCompletedOperation->output_artifact_id
            : (int) $attemptSnapshot->source_artifact_id;

        if ($verificationPassed
            && (! $stagedArtifact->hasPdfHeader || $stagedArtifact->sizeBytes === 0)) {
            throw new EsignArtifactStorageException(
                'verified_output_pdf_invalid',
                $stagedArtifact->publicId,
            );
        }

        $artifact = $this->finalizeArtifact(
            stagedArtifact: $stagedArtifact,
            documentId: (int) $attemptSnapshot->document_id,
            artifactType: $verificationPassed
                ? DocumentArtifactType::AfterSign
                : DocumentArtifactType::FailedOutput,
            parentArtifactId: $parentArtifactId,
            makeCurrent: false,
            originalName: null,
            createdByUserId: $context->actorUserId,
            sourceSystem: 'bsre',
            sourceReferenceType: 'esign_attempt',
            sourceReferenceId: $attemptSnapshot->public_id,
            metadata: [
                'verification_passed' => $verificationPassed,
                'pdf_header_valid' => $stagedArtifact->hasPdfHeader,
            ] + ($metadata ?? []),
            allowNonCurrentParent: $lastCompletedOperation instanceof EsignSignatureOperation,
        );

        $this->attemptPersistence->attachResultArtifact(
            $attemptId,
            $artifact,
            $context,
        );

        return $artifact;
    }

    /** @param array<string, mixed>|null $metadata */
    public function finalizeSignatureOperationCheckpoint(
        StagedDocumentArtifact $stagedArtifact,
        EsignAttempt|int $attempt,
        EsignSignatureOperation|int $operation,
        ?int $createdByUserId = null,
        ?array $metadata = null,
    ): DocumentArtifact {
        $attemptId = $attempt instanceof EsignAttempt ? (int) $attempt->getKey() : $attempt;
        $operationId = $operation instanceof EsignSignatureOperation
            ? (int) $operation->getKey()
            : $operation;
        /** @var EsignAttempt $attemptSnapshot */
        $attemptSnapshot = EsignAttempt::query()
            ->select(['id', 'document_id'])
            ->findOrFail($attemptId);
        /** @var EsignSignatureOperation $operationSnapshot */
        $operationSnapshot = EsignSignatureOperation::query()
            ->select([
                'id',
                'public_id',
                'esign_attempt_id',
                'operation_index',
                'input_artifact_id',
                'input_sha256',
            ])
            ->findOrFail($operationId);

        if ($attemptSnapshot->document_id === null
            || (int) $operationSnapshot->esign_attempt_id !== $attemptId
            || $operationSnapshot->input_artifact_id === null) {
            throw new EsignInvariantViolationException('signature_checkpoint_context_invalid');
        }

        /** @var DocumentArtifact $inputArtifact */
        $inputArtifact = DocumentArtifact::query()->findOrFail($operationSnapshot->input_artifact_id);

        if ($inputArtifact->document_id !== $attemptSnapshot->document_id
            || ! hash_equals((string) $operationSnapshot->input_sha256, (string) $inputArtifact->file_sha256)) {
            throw new EsignInvariantViolationException('signature_checkpoint_input_mismatch');
        }

        return $this->finalizeArtifact(
            stagedArtifact: $stagedArtifact,
            documentId: (int) $attemptSnapshot->document_id,
            artifactType: DocumentArtifactType::IntermediateSign,
            parentArtifactId: (int) $inputArtifact->getKey(),
            makeCurrent: false,
            originalName: null,
            createdByUserId: $createdByUserId,
            sourceSystem: 'bsre',
            sourceReferenceType: 'esign_signature_operation',
            sourceReferenceId: $operationSnapshot->public_id,
            metadata: [
                'esign_attempt_id' => $attemptId,
                'esign_signature_operation_id' => $operationId,
                'operation_index' => $operationSnapshot->operation_index,
            ] + ($metadata ?? []),
            allowNonCurrentParent: true,
        );
    }

    /** @param resource|string $contents */
    private function stage(
        #[SensitiveParameter] mixed $contents,
        ?DateTimeInterface $storageDate,
    ): StagedDocumentArtifact {
        $diskName = $this->diskName();
        $disk = $this->disk();
        $publicId = (string) Str::uuid();
        $stagingPath = $this->stagingPath($publicId);

        if ($disk->exists($stagingPath)) {
            throw new EsignArtifactStorageException('staging_path_collision', $publicId);
        }

        if (! $disk->put($stagingPath, $contents)) {
            throw new EsignArtifactStorageException('staging_write_failed', $publicId);
        }

        $inspection = $this->inspectStoredFile($disk, $stagingPath, $publicId);
        $artifactDate = $this->artifactDate($storageDate);

        return new StagedDocumentArtifact(
            publicId: $publicId,
            storageDisk: $diskName,
            stagingPath: $stagingPath,
            sizeBytes: $inspection['size_bytes'],
            sha256: $inspection['sha256'],
            hasPdfHeader: $inspection['has_pdf_header'],
            documentYear: $artifactDate->year,
            documentMonth: $artifactDate->month,
        );
    }

    /** @param array<string, mixed>|null $metadata */
    private function finalizeArtifact(
        StagedDocumentArtifact $stagedArtifact,
        int $documentId,
        DocumentArtifactType $artifactType,
        ?int $parentArtifactId,
        bool $makeCurrent,
        ?string $originalName,
        ?int $createdByUserId,
        string $sourceSystem,
        ?string $sourceReferenceType,
        ?string $sourceReferenceId,
        ?array $metadata,
        ?int $replaceableDraftWorkflowId = null,
        ?int $actorUserPositionId = null,
        bool $actorIsActing = false,
        ?int $replaceableActiveWorkflowId = null,
        ?int $replaceableActiveStepId = null,
        bool $allowNonCurrentParent = false,
    ): DocumentArtifact {
        $this->assertStagedArtifact($stagedArtifact);
        $this->assertMetadataLengths($sourceSystem, $sourceReferenceType, $sourceReferenceId);

        if ($replaceableDraftWorkflowId !== null
            && ($replaceableActiveWorkflowId !== null || $replaceableActiveStepId !== null)) {
            throw new EsignInvariantViolationException('artifact_workflow_replacement_mode_conflict');
        }

        $existingArtifact = DocumentArtifact::query()
            ->where('public_id', $stagedArtifact->publicId)
            ->first();

        if ($existingArtifact instanceof DocumentArtifact) {
            $this->assertExistingArtifactMatches(
                $existingArtifact,
                $stagedArtifact,
                $documentId,
                $artifactType,
                $parentArtifactId,
            );
            $this->assertStoredArtifactIntegrity($existingArtifact);

            return $existingArtifact;
        }

        $this->assertFinalizationPreconditions(
            $documentId,
            $artifactType,
            $parentArtifactId,
            $makeCurrent,
            $replaceableDraftWorkflowId,
            $replaceableActiveWorkflowId,
            $replaceableActiveStepId,
            $allowNonCurrentParent,
        );

        $finalPath = $this->finalPath($stagedArtifact, $artifactType);
        $this->ensureFinalFile($stagedArtifact, $finalPath);

        return $this->persistArtifact(
            stagedArtifact: $stagedArtifact,
            documentId: $documentId,
            artifactType: $artifactType,
            parentArtifactId: $parentArtifactId,
            makeCurrent: $makeCurrent,
            originalName: $originalName,
            createdByUserId: $createdByUserId,
            sourceSystem: $sourceSystem,
            sourceReferenceType: $sourceReferenceType,
            sourceReferenceId: $sourceReferenceId,
            metadata: $metadata,
            finalPath: $finalPath,
            replaceableDraftWorkflowId: $replaceableDraftWorkflowId,
            actorUserPositionId: $actorUserPositionId,
            actorIsActing: $actorIsActing,
            replaceableActiveWorkflowId: $replaceableActiveWorkflowId,
            replaceableActiveStepId: $replaceableActiveStepId,
            allowNonCurrentParent: $allowNonCurrentParent,
        );
    }

    private function assertFinalizationPreconditions(
        int $documentId,
        DocumentArtifactType $artifactType,
        ?int $parentArtifactId,
        bool $makeCurrent,
        ?int $replaceableDraftWorkflowId,
        ?int $replaceableActiveWorkflowId,
        ?int $replaceableActiveStepId,
        bool $allowNonCurrentParent,
    ): void {
        Document::withTrashed()->findOrFail($documentId);

        if ($makeCurrent) {
            $inProgressWorkflows = DocumentSigningWorkflow::query()
                ->where('document_id', $documentId)
                ->whereIn('status', [
                    DocumentSigningWorkflowStatus::Draft->value,
                    DocumentSigningWorkflowStatus::Active->value,
                    DocumentSigningWorkflowStatus::NeedsReview->value,
                ])
                ->orderBy('id')
                ->get();

            if ($replaceableDraftWorkflowId !== null) {
                /** @var DocumentSigningWorkflow|null $replaceableWorkflow */
                $replaceableWorkflow = $inProgressWorkflows->firstWhere('id', $replaceableDraftWorkflowId);

                if (! $replaceableWorkflow instanceof DocumentSigningWorkflow) {
                    throw new EsignInvariantViolationException('replaceable_draft_workflow_not_found');
                }

                $this->assertReplaceableDraftWorkflow(
                    $replaceableWorkflow,
                    $documentId,
                    $parentArtifactId,
                );
            }

            if ($replaceableActiveWorkflowId !== null || $replaceableActiveStepId !== null) {
                if ($replaceableActiveWorkflowId === null || $replaceableActiveStepId === null) {
                    throw new EsignInvariantViolationException('prepared_source_active_binding_incomplete');
                }

                /** @var DocumentSigningWorkflow|null $replaceableWorkflow */
                $replaceableWorkflow = $inProgressWorkflows->firstWhere('id', $replaceableActiveWorkflowId);

                if (! $replaceableWorkflow instanceof DocumentSigningWorkflow) {
                    throw new EsignInvariantViolationException('prepared_source_active_workflow_not_found');
                }

                $this->assertReplaceableActiveStep(
                    $replaceableWorkflow,
                    $replaceableActiveStepId,
                    $documentId,
                    $parentArtifactId,
                );
            }

            if ($inProgressWorkflows
                ->reject(static fn (DocumentSigningWorkflow $workflow): bool => in_array(
                    (int) $workflow->getKey(),
                    array_filter([$replaceableDraftWorkflowId, $replaceableActiveWorkflowId]),
                    true,
                ))
                ->isNotEmpty()) {
                throw new EsignInvariantViolationException('source_artifact_has_in_progress_workflow');
            }
        }

        $artifacts = DocumentArtifact::query()
            ->where('document_id', $documentId)
            ->where(function (Builder $query) use ($parentArtifactId): void {
                $query->where('is_current', true);

                if ($parentArtifactId !== null) {
                    $query->orWhere('id', $parentArtifactId);
                }
            })
            ->get();
        $currentArtifacts = $artifacts->where('is_current', true);
        /** @var DocumentArtifact|null $parentArtifact */
        $parentArtifact = $parentArtifactId === null
            ? null
            : $artifacts->firstWhere('id', $parentArtifactId);

        $this->assertArtifactChain(
            $artifactType,
            $parentArtifactId,
            $parentArtifact,
            $currentArtifacts,
            $makeCurrent,
            $allowNonCurrentParent,
        );
    }

    /** @param array<string, mixed>|null $metadata */
    private function persistArtifact(
        StagedDocumentArtifact $stagedArtifact,
        int $documentId,
        DocumentArtifactType $artifactType,
        ?int $parentArtifactId,
        bool $makeCurrent,
        ?string $originalName,
        ?int $createdByUserId,
        string $sourceSystem,
        ?string $sourceReferenceType,
        ?string $sourceReferenceId,
        ?array $metadata,
        string $finalPath,
        ?int $replaceableDraftWorkflowId,
        ?int $actorUserPositionId,
        bool $actorIsActing,
        ?int $replaceableActiveWorkflowId,
        ?int $replaceableActiveStepId,
        bool $allowNonCurrentParent,
    ): DocumentArtifact {
        return DB::transaction(function () use (
            $stagedArtifact,
            $documentId,
            $artifactType,
            $parentArtifactId,
            $makeCurrent,
            $originalName,
            $createdByUserId,
            $sourceSystem,
            $sourceReferenceType,
            $sourceReferenceId,
            $metadata,
            $finalPath,
            $replaceableDraftWorkflowId,
            $actorUserPositionId,
            $actorIsActing,
            $replaceableActiveWorkflowId,
            $replaceableActiveStepId,
            $allowNonCurrentParent,
        ): DocumentArtifact {
            Document::withTrashed()
                ->lockForUpdate()
                ->findOrFail($documentId);

            /** @var DocumentArtifact|null $existingArtifact */
            $existingArtifact = DocumentArtifact::query()
                ->where('public_id', $stagedArtifact->publicId)
                ->lockForUpdate()
                ->first();

            if ($existingArtifact instanceof DocumentArtifact) {
                $this->assertExistingArtifactMatches(
                    $existingArtifact,
                    $stagedArtifact,
                    $documentId,
                    $artifactType,
                    $parentArtifactId,
                );

                return $existingArtifact;
            }

            $replaceableWorkflow = null;
            $replaceableWorkflowSteps = collect();
            $replaceableActiveWorkflow = null;
            $replaceableActiveStep = null;

            if ($makeCurrent) {
                $inProgressWorkflows = DocumentSigningWorkflow::query()
                    ->where('document_id', $documentId)
                    ->whereIn('status', [
                        DocumentSigningWorkflowStatus::Draft->value,
                        DocumentSigningWorkflowStatus::Active->value,
                        DocumentSigningWorkflowStatus::NeedsReview->value,
                    ])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($replaceableDraftWorkflowId !== null) {
                    /** @var DocumentSigningWorkflow|null $replaceableWorkflow */
                    $replaceableWorkflow = $inProgressWorkflows->firstWhere('id', $replaceableDraftWorkflowId);

                    if (! $replaceableWorkflow instanceof DocumentSigningWorkflow) {
                        throw new EsignInvariantViolationException('replaceable_draft_workflow_not_found');
                    }

                    $replaceableWorkflowSteps = $this->assertReplaceableDraftWorkflow(
                        $replaceableWorkflow,
                        $documentId,
                        $parentArtifactId,
                        lockForUpdate: true,
                    );
                }

                if ($replaceableActiveWorkflowId !== null || $replaceableActiveStepId !== null) {
                    if ($replaceableActiveWorkflowId === null || $replaceableActiveStepId === null) {
                        throw new EsignInvariantViolationException('prepared_source_active_binding_incomplete');
                    }

                    /** @var DocumentSigningWorkflow|null $replaceableActiveWorkflow */
                    $replaceableActiveWorkflow = $inProgressWorkflows->firstWhere('id', $replaceableActiveWorkflowId);

                    if (! $replaceableActiveWorkflow instanceof DocumentSigningWorkflow) {
                        throw new EsignInvariantViolationException('prepared_source_active_workflow_not_found');
                    }

                    $replaceableActiveStep = $this->assertReplaceableActiveStep(
                        $replaceableActiveWorkflow,
                        $replaceableActiveStepId,
                        $documentId,
                        $parentArtifactId,
                        lockForUpdate: true,
                    );
                }

                if ($inProgressWorkflows
                    ->reject(static fn (DocumentSigningWorkflow $workflow): bool => in_array(
                        (int) $workflow->getKey(),
                        array_filter([$replaceableDraftWorkflowId, $replaceableActiveWorkflowId]),
                        true,
                    ))
                    ->isNotEmpty()) {
                    throw new EsignInvariantViolationException('source_artifact_has_in_progress_workflow');
                }
            }

            $lockedArtifacts = $this->lockCurrentAndParentArtifacts($documentId, $parentArtifactId);
            $currentArtifacts = $lockedArtifacts->where('is_current', true);
            /** @var DocumentArtifact|null $parentArtifact */
            $parentArtifact = $parentArtifactId === null
                ? null
                : $lockedArtifacts->firstWhere('id', $parentArtifactId);

            $this->assertArtifactChain(
                $artifactType,
                $parentArtifactId,
                $parentArtifact,
                $currentArtifacts,
                $makeCurrent,
                $allowNonCurrentParent,
            );

            $version = ((int) DocumentArtifact::query()
                ->where('document_id', $documentId)
                ->max('version')) + 1;

            if ($makeCurrent && $currentArtifacts->count() === 1) {
                /** @var DocumentArtifact $currentArtifact */
                $currentArtifact = $currentArtifacts->first();
                $currentArtifact->is_current = false;
                $currentArtifact->save();
            }

            $artifact = DocumentArtifact::query()->create([
                'public_id' => $stagedArtifact->publicId,
                'document_id' => $documentId,
                'parent_artifact_id' => $parentArtifactId,
                'artifact_type' => $artifactType,
                'version' => $version,
                'is_current' => $makeCurrent,
                'storage_disk' => $stagedArtifact->storageDisk,
                'file_path' => $finalPath,
                'storage_path_sha256' => DocumentArtifactStoragePath::checksum(
                    $stagedArtifact->storageDisk,
                    $finalPath,
                ),
                'original_name' => $this->normalizeOriginalName($originalName),
                'stored_name' => basename($finalPath),
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => $stagedArtifact->sizeBytes,
                'file_sha256' => $stagedArtifact->sha256,
                'document_year' => $stagedArtifact->documentYear,
                'document_month' => $stagedArtifact->documentMonth,
                'source_system' => $sourceSystem,
                'source_reference_type' => $sourceReferenceType,
                'source_reference_id' => $sourceReferenceId,
                'metadata' => $metadata,
                'created_by_user_id' => $createdByUserId,
            ]);

            if ($replaceableWorkflow instanceof DocumentSigningWorkflow) {
                $this->rebindDraftWorkflowToArtifact(
                    $replaceableWorkflow,
                    $replaceableWorkflowSteps,
                    $artifact,
                    $parentArtifactId,
                    $createdByUserId,
                    $actorUserPositionId,
                    $actorIsActing,
                );
            }

            if ($replaceableActiveWorkflow instanceof DocumentSigningWorkflow
                && $replaceableActiveStep instanceof DocumentSigningStep) {
                $this->rebindActiveStepToArtifact(
                    $replaceableActiveWorkflow,
                    $replaceableActiveStep,
                    $artifact,
                    $parentArtifactId,
                    $createdByUserId,
                    $actorUserPositionId,
                    $actorIsActing,
                );
            }

            return $artifact;
        }, attempts: 3);
    }

    /** @return Collection<int, DocumentSigningStep> */
    private function assertReplaceableDraftWorkflow(
        DocumentSigningWorkflow $workflow,
        int $documentId,
        ?int $parentArtifactId,
        bool $lockForUpdate = false,
    ): Collection {
        if ($parentArtifactId === null
            || $workflow->document_id !== $documentId
            || $workflow->status !== DocumentSigningWorkflowStatus::Draft
            || $workflow->started_at !== null
            || $workflow->completed_at !== null
            || $workflow->rejected_at !== null
            || $workflow->current_sequence !== null
            || (int) $workflow->current_artifact_id !== $parentArtifactId) {
            throw new EsignInvariantViolationException('source_artifact_draft_workflow_not_replaceable');
        }

        $stepsQuery = DocumentSigningStep::query()
            ->where('document_signing_workflow_id', $workflow->getKey())
            ->orderBy('sequence')
            ->orderBy('id');

        if ($lockForUpdate) {
            $stepsQuery->lockForUpdate();
        }

        $steps = $stepsQuery->get();

        if ($steps->isEmpty()) {
            throw new EsignInvariantViolationException('source_artifact_draft_workflow_steps_missing');
        }

        foreach ($steps as $step) {
            $expectedSourceArtifactId = $step->sequence === 1
                ? $parentArtifactId
                : null;
            $actualSourceArtifactId = $step->source_artifact_id === null
                ? null
                : (int) $step->source_artifact_id;

            if ($step->status !== DocumentSigningStepStatus::Pending
                || $step->activated_at !== null
                || $step->completed_at !== null
                || $step->rejected_at !== null
                || $step->result_artifact_id !== null
                || $actualSourceArtifactId !== $expectedSourceArtifactId) {
                throw new EsignInvariantViolationException('source_artifact_draft_workflow_steps_not_pristine');
            }
        }

        if (EsignAttempt::query()
            ->whereIn('document_signing_step_id', $steps->modelKeys())
            ->exists()) {
            throw new EsignInvariantViolationException('source_artifact_draft_workflow_has_attempts');
        }

        return $steps;
    }

    private function assertReplaceableActiveStep(
        DocumentSigningWorkflow $workflow,
        int $stepId,
        int $documentId,
        ?int $parentArtifactId,
        bool $lockForUpdate = false,
    ): DocumentSigningStep {
        if ($parentArtifactId === null
            || $workflow->document_id !== $documentId
            || $workflow->status !== DocumentSigningWorkflowStatus::Active
            || (int) $workflow->current_artifact_id !== $parentArtifactId
            || $workflow->current_sequence === null) {
            throw new EsignInvariantViolationException('prepared_source_active_workflow_not_replaceable');
        }

        $stepQuery = DocumentSigningStep::query()
            ->whereKey($stepId)
            ->where('document_signing_workflow_id', $workflow->getKey());

        if ($lockForUpdate) {
            $stepQuery->lockForUpdate();
        }

        /** @var DocumentSigningStep|null $step */
        $step = $stepQuery->first();

        if (! $step instanceof DocumentSigningStep
            || $step->status !== DocumentSigningStepStatus::Active
            || (int) $step->sequence !== (int) $workflow->current_sequence
            || (int) $step->source_artifact_id !== $parentArtifactId
            || $step->result_artifact_id !== null) {
            throw new EsignInvariantViolationException('prepared_source_active_step_not_replaceable');
        }

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
            throw new EsignInvariantViolationException('prepared_source_active_step_has_blocking_attempt');
        }

        return $step;
    }

    /** @param Collection<int, DocumentSigningStep> $steps */
    private function rebindDraftWorkflowToArtifact(
        DocumentSigningWorkflow $workflow,
        Collection $steps,
        DocumentArtifact $artifact,
        ?int $parentArtifactId,
        ?int $actorUserId,
        ?int $actorUserPositionId,
        bool $actorIsActing,
    ): void {
        /** @var DocumentSigningStep|null $firstStep */
        $firstStep = $steps->first();

        if (! $firstStep instanceof DocumentSigningStep || $parentArtifactId === null) {
            throw new EsignInvariantViolationException('source_artifact_draft_workflow_rebind_invalid');
        }

        $firstStep->source_artifact_id = $artifact->getKey();
        $firstStep->save();

        $workflow->current_artifact_id = $artifact->getKey();
        $workflow->lock_version = (int) $workflow->lock_version + 1;
        $workflow->save();

        DocumentSigningWorkflowEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'document_signing_workflow_id' => $workflow->getKey(),
            'document_signing_step_id' => $firstStep->getKey(),
            'event_type' => DocumentSigningWorkflowEventType::SourceArtifactReplaced,
            'from_workflow_status' => DocumentSigningWorkflowStatus::Draft,
            'to_workflow_status' => DocumentSigningWorkflowStatus::Draft,
            'actor_user_id' => $actorUserId,
            'actor_user_position_id' => $actorUserPositionId,
            'actor_is_acting' => $actorIsActing,
            'reason_code' => 'document_source_replaced',
            'message' => 'Source artifact diganti sebelum workflow tanda tangan dimulai.',
            'metadata' => [
                'previous_artifact_id' => $parentArtifactId,
                'replacement_artifact_id' => $artifact->getKey(),
                'replacement_artifact_public_id' => $artifact->public_id,
                'replacement_artifact_version' => $artifact->version,
            ],
            'occurred_at' => now(),
        ]);
    }

    private function rebindActiveStepToArtifact(
        DocumentSigningWorkflow $workflow,
        DocumentSigningStep $step,
        DocumentArtifact $artifact,
        ?int $parentArtifactId,
        ?int $actorUserId,
        ?int $actorUserPositionId,
        bool $actorIsActing,
    ): void {
        if ($parentArtifactId === null) {
            throw new EsignInvariantViolationException('prepared_source_active_rebind_invalid');
        }

        $step->source_artifact_id = $artifact->getKey();
        $step->save();

        $workflow->current_artifact_id = $artifact->getKey();
        $workflow->lock_version = (int) $workflow->lock_version + 1;
        $workflow->save();

        DocumentSigningWorkflowEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'document_signing_workflow_id' => $workflow->getKey(),
            'document_signing_step_id' => $step->getKey(),
            'event_type' => DocumentSigningWorkflowEventType::SourceArtifactReplaced,
            'from_workflow_status' => DocumentSigningWorkflowStatus::Active,
            'to_workflow_status' => DocumentSigningWorkflowStatus::Active,
            'actor_user_id' => $actorUserId,
            'actor_user_position_id' => $actorUserPositionId,
            'actor_is_acting' => $actorIsActing,
            'reason_code' => 'prepared_rendition_materialized',
            'message' => 'Prepared rendition dipromosikan menjadi source artifact canonical.',
            'metadata' => [
                'previous_artifact_id' => $parentArtifactId,
                'prepared_artifact_id' => $artifact->getKey(),
                'prepared_artifact_public_id' => $artifact->public_id,
                'prepared_artifact_version' => $artifact->version,
            ],
            'occurred_at' => now(),
        ]);
    }

    /** @return Collection<int, DocumentArtifact> */
    private function lockCurrentAndParentArtifacts(int $documentId, ?int $parentArtifactId): Collection
    {
        return DocumentArtifact::query()
            ->where('document_id', $documentId)
            ->where(function (Builder $query) use ($parentArtifactId): void {
                $query->where('is_current', true);

                if ($parentArtifactId !== null) {
                    $query->orWhere('id', $parentArtifactId);
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @param Collection<int, DocumentArtifact> $currentArtifacts */
    private function assertArtifactChain(
        DocumentArtifactType $artifactType,
        ?int $parentArtifactId,
        ?DocumentArtifact $parentArtifact,
        Collection $currentArtifacts,
        bool $makeCurrent,
        bool $allowNonCurrentParent = false,
    ): void {
        if ($currentArtifacts->count() > 1) {
            throw new EsignInvariantViolationException('document_has_multiple_current_artifacts');
        }

        if ($parentArtifactId !== null && ! $parentArtifact instanceof DocumentArtifact) {
            throw new EsignInvariantViolationException('artifact_parent_document_mismatch');
        }

        if ($makeCurrent) {
            if ($artifactType !== DocumentArtifactType::BeforeSign) {
                throw new EsignInvariantViolationException('only_source_artifact_can_be_activated_directly');
            }

            if ($currentArtifacts->isEmpty() && $parentArtifactId !== null) {
                throw new EsignInvariantViolationException('initial_source_artifact_cannot_have_parent');
            }

            if ($currentArtifacts->count() === 1
                && (int) $currentArtifacts->first()->getKey() !== $parentArtifactId) {
                throw new EsignInvariantViolationException('source_artifact_parent_must_be_current');
            }

            return;
        }

        if ($parentArtifactId === null || ! $parentArtifact instanceof DocumentArtifact) {
            throw new EsignInvariantViolationException('output_artifact_parent_required');
        }

        if ($allowNonCurrentParent) {
            if (! in_array($artifactType, [
                DocumentArtifactType::IntermediateSign,
                DocumentArtifactType::AfterSign,
                DocumentArtifactType::FailedOutput,
            ], true)
                || $currentArtifacts->count() !== 1) {
                throw new EsignInvariantViolationException('checkpoint_artifact_chain_invalid');
            }

            return;
        }

        if ($currentArtifacts->count() !== 1
            || (int) $currentArtifacts->first()->getKey() !== $parentArtifactId) {
            throw new EsignInvariantViolationException('output_artifact_parent_must_be_current');
        }
    }

    private function ensureFinalFile(
        StagedDocumentArtifact $stagedArtifact,
        string $finalPath,
    ): void {
        $disk = $this->disk();

        if ($disk->exists($finalPath)) {
            $this->assertInspectionMatches(
                $stagedArtifact,
                $this->inspectStoredFile($disk, $finalPath, $stagedArtifact->publicId),
                'final_file_conflict',
            );

            if ($disk->exists($stagedArtifact->stagingPath)) {
                $this->assertInspectionMatches(
                    $stagedArtifact,
                    $this->inspectStoredFile(
                        $disk,
                        $stagedArtifact->stagingPath,
                        $stagedArtifact->publicId,
                    ),
                    'staging_file_changed',
                );
                $disk->delete($stagedArtifact->stagingPath);
            }

            return;
        }

        if (! $disk->exists($stagedArtifact->stagingPath)) {
            throw new EsignArtifactStorageException(
                'staging_and_final_file_missing',
                $stagedArtifact->publicId,
            );
        }

        $this->assertInspectionMatches(
            $stagedArtifact,
            $this->inspectStoredFile(
                $disk,
                $stagedArtifact->stagingPath,
                $stagedArtifact->publicId,
            ),
            'staging_file_changed',
        );

        if (! $disk->move($stagedArtifact->stagingPath, $finalPath)
            && ! $disk->exists($finalPath)) {
            throw new EsignArtifactStorageException(
                'final_file_move_failed',
                $stagedArtifact->publicId,
            );
        }

        $this->assertInspectionMatches(
            $stagedArtifact,
            $this->inspectStoredFile($disk, $finalPath, $stagedArtifact->publicId),
            'final_file_integrity_mismatch',
        );
    }

    private function assertStoredArtifactIntegrity(DocumentArtifact $artifact): void
    {
        $disk = $this->diskForName($artifact->storage_disk);
        $inspection = $this->inspectStoredFile($disk, $artifact->file_path, $artifact->public_id);

        if ($inspection['size_bytes'] !== (int) $artifact->size_bytes
            || ! hash_equals($artifact->file_sha256, $inspection['sha256'])) {
            throw new EsignArtifactStorageException(
                'existing_artifact_integrity_mismatch',
                $artifact->public_id,
            );
        }
    }

    private function assertExistingArtifactMatches(
        DocumentArtifact $artifact,
        StagedDocumentArtifact $stagedArtifact,
        int $documentId,
        DocumentArtifactType $artifactType,
        ?int $parentArtifactId,
    ): void {
        $existingParentArtifactId = $artifact->parent_artifact_id === null
            ? null
            : (int) $artifact->parent_artifact_id;

        if ($artifact->document_id !== $documentId
            || $artifact->artifact_type !== $artifactType
            || $existingParentArtifactId !== $parentArtifactId
            || $artifact->storage_disk !== $stagedArtifact->storageDisk
            || (int) $artifact->size_bytes !== $stagedArtifact->sizeBytes
            || ! hash_equals($artifact->file_sha256, $stagedArtifact->sha256)
            || (int) $artifact->document_year !== $stagedArtifact->documentYear
            || (int) $artifact->document_month !== $stagedArtifact->documentMonth) {
            throw new EsignInvariantViolationException('artifact_public_id_payload_mismatch');
        }
    }

    private function assertStagedArtifact(StagedDocumentArtifact $stagedArtifact): void
    {
        if (! Str::isUuid($stagedArtifact->publicId)
            || $stagedArtifact->storageDisk !== $this->diskName()
            || $stagedArtifact->stagingPath !== $this->stagingPath($stagedArtifact->publicId)
            || preg_match('/\A[a-f0-9]{64}\z/', $stagedArtifact->sha256) !== 1
            || $stagedArtifact->sizeBytes < 0
            || $stagedArtifact->documentYear < 2000
            || $stagedArtifact->documentYear > 9999
            || $stagedArtifact->documentMonth < 1
            || $stagedArtifact->documentMonth > 12) {
            throw new EsignArtifactStorageException(
                'staged_artifact_metadata_invalid',
                $stagedArtifact->publicId,
            );
        }
    }

    /** @param array{size_bytes: int, sha256: string, has_pdf_header: bool} $inspection */
    private function assertInspectionMatches(
        StagedDocumentArtifact $stagedArtifact,
        array $inspection,
        string $errorCode,
    ): void {
        if ($inspection['size_bytes'] !== $stagedArtifact->sizeBytes
            || ! hash_equals($stagedArtifact->sha256, $inspection['sha256'])
            || $inspection['has_pdf_header'] !== $stagedArtifact->hasPdfHeader) {
            throw new EsignArtifactStorageException($errorCode, $stagedArtifact->publicId);
        }
    }

    /** @return array{size_bytes: int, sha256: string, has_pdf_header: bool} */
    private function inspectStoredFile(
        FilesystemAdapter $disk,
        string $path,
        string $publicId,
    ): array {
        if (! $disk->exists($path)) {
            throw new EsignArtifactStorageException('artifact_file_missing', $publicId);
        }

        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new EsignArtifactStorageException('artifact_file_unreadable', $publicId);
        }

        try {
            $header = fread($stream, 5);

            if ($header === false) {
                throw new EsignArtifactStorageException('artifact_header_unreadable', $publicId);
            }

            $hashContext = hash_init('sha256');
            hash_update($hashContext, $header);
            $remainingBytes = hash_update_stream($hashContext, $stream);

            if ($remainingBytes === false) {
                throw new EsignArtifactStorageException('artifact_hash_failed', $publicId);
            }

            $sizeBytes = strlen($header) + $remainingBytes;

            if ($sizeBytes !== $disk->size($path)) {
                throw new EsignArtifactStorageException('artifact_size_changed_during_read', $publicId);
            }

            return [
                'size_bytes' => $sizeBytes,
                'sha256' => hash_final($hashContext),
                'has_pdf_header' => $header === '%PDF-',
            ];
        } finally {
            fclose($stream);
        }
    }

    private function artifactDate(?DateTimeInterface $storageDate): CarbonImmutable
    {
        $date = $storageDate === null
            ? CarbonImmutable::now()
            : CarbonImmutable::instance($storageDate);

        return $date->setTimezone($this->artifactTimezone());
    }

    private function finalPath(
        StagedDocumentArtifact $stagedArtifact,
        DocumentArtifactType $artifactType,
    ): string {
        $directory = match ($artifactType) {
            DocumentArtifactType::BeforeSign => 'source',
            DocumentArtifactType::IntermediateSign => 'intermediate',
            DocumentArtifactType::AfterSign => 'signed',
            DocumentArtifactType::FailedOutput => 'failed-output',
        };
        $month = str_pad((string) $stagedArtifact->documentMonth, 2, '0', STR_PAD_LEFT);
        $shard = Str::lower(Str::substr($stagedArtifact->publicId, 0, 2));

        return implode('/', [
            $this->root(),
            $directory,
            (string) $stagedArtifact->documentYear,
            $month,
            $shard,
            $stagedArtifact->publicId.'.pdf',
        ]);
    }

    private function stagingPath(string $publicId): string
    {
        return $this->root().'/staging/'.$publicId.'.pdf.part';
    }

    private function normalizeOriginalName(?string $originalName): ?string
    {
        if ($originalName === null) {
            return null;
        }

        $normalizedName = Str::of($originalName)
            ->replace('\\', '/')
            ->afterLast('/')
            ->replace("\0", '')
            ->trim()
            ->toString();

        return $normalizedName === ''
            ? null
            : Str::limit($normalizedName, 255, '');
    }

    private function assertMetadataLengths(
        string $sourceSystem,
        ?string $sourceReferenceType,
        ?string $sourceReferenceId,
    ): void {
        if (trim($sourceSystem) === '' || Str::length($sourceSystem) > 50) {
            throw new EsignInvariantViolationException('artifact_source_system_invalid');
        }

        if ($sourceReferenceType !== null && Str::length($sourceReferenceType) > 50) {
            throw new EsignInvariantViolationException('artifact_source_reference_type_invalid');
        }

        if ($sourceReferenceId !== null && Str::length($sourceReferenceId) > 100) {
            throw new EsignInvariantViolationException('artifact_source_reference_id_invalid');
        }
    }

    private function disk(): FilesystemAdapter
    {
        return $this->diskForName($this->diskName());
    }

    private function diskForName(string $diskName): FilesystemAdapter
    {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $diskName) !== 1) {
            throw new EsignArtifactStorageException('artifact_disk_invalid');
        }

        if ($this->config->get("filesystems.disks.{$diskName}.driver") !== 'local') {
            throw new EsignArtifactStorageException('artifact_disk_must_be_local');
        }

        return $this->filesystems->disk($diskName);
    }

    private function diskName(): string
    {
        $diskName = (string) $this->config->get('esign.artifacts.disk', 'private');

        if ($diskName === ''
            || Str::length($diskName) > 50
            || preg_match('/\A[a-zA-Z0-9_-]+\z/', $diskName) !== 1) {
            throw new EsignArtifactStorageException('artifact_disk_invalid');
        }

        return $diskName;
    }

    private function root(): string
    {
        $root = trim((string) $this->config->get('esign.artifacts.root', 'documents'), '/');

        if (preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/', $root) !== 1) {
            throw new EsignArtifactStorageException('artifact_root_invalid');
        }

        return $root;
    }

    private function artifactTimezone(): string
    {
        $timezone = (string) $this->config->get('esign.artifacts.timezone', 'Asia/Jakarta');

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new EsignArtifactStorageException('artifact_timezone_invalid');
        }

        return $timezone;
    }
}
