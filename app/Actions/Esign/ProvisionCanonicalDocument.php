<?php

declare(strict_types=1);

namespace App\Actions\Esign;

use App\Data\Esign\DocumentSigningWorkflowDefinition;
use App\Enums\Esign\DocumentArtifactType;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowEventType;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\DocumentSigningWorkflowEvent;
use App\Models\UserPosition;
use App\Services\Esign\Persistence\DocumentArtifactPersistenceService;
use App\Services\Esign\Provisioning\DocumentSigningWorkflowDefinitionRegistry;
use App\Services\Esign\Provisioning\LegacyDocumentSourceLocator;
use App\Services\User\PositionIdentityResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

final class ProvisionCanonicalDocument
{
    public function __construct(
        private DocumentArtifactPersistenceService $artifactPersistence,
        private DocumentSigningWorkflowDefinitionRegistry $definitions,
        private LegacyDocumentSourceLocator $sourceLocator,
        private PositionIdentityResolver $positionIdentityResolver,
    ) {}

    public function handle(
        int $documentId,
        ?int $actorUserId = null,
        ?int $actorUserPositionId = null,
        bool $actorIsActing = false,
    ): DocumentArtifact {
        /** @var Document $document */
        $document = Document::withTrashed()
            ->with([
                'recipientPosition.jabatan',
                'unitKerja',
                'uploadedByPosition.jabatan',
            ])
            ->findOrFail($documentId);
        $rootDocument = $this->rootDocument($document);
        $artifact = $this->sourceArtifact($document, $actorUserId);
        $definition = $this->definitions->forDocument($document, $rootDocument);

        if ($definition instanceof DocumentSigningWorkflowDefinition) {
            $this->provisionWorkflow(
                document: $document,
                rootDocument: $rootDocument,
                artifact: $artifact,
                definition: $definition,
                actorUserId: $actorUserId,
                actorUserPositionId: $actorUserPositionId,
                actorIsActing: $actorIsActing,
            );
        }

        return $artifact;
    }

    private function sourceArtifact(Document $document, ?int $actorUserId): DocumentArtifact
    {
        /** @var DocumentArtifact|null $existingArtifact */
        $existingArtifact = DocumentArtifact::query()
            ->where('document_id', $document->getKey())
            ->where('artifact_type', DocumentArtifactType::BeforeSign->value)
            ->where('is_current', true)
            ->first();

        if ($existingArtifact instanceof DocumentArtifact) {
            return $existingArtifact;
        }

        $sourcePath = $this->sourceLocator->locate($document);
        $stream = fopen($sourcePath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('legacy_document_source_unreadable');
        }

        try {
            $stagedArtifact = $this->artifactPersistence->stagePdfStream(
                $stream,
                $document->created_at,
            );
        } finally {
            fclose($stream);
        }

        try {
            $legacyOriginalName = $this->legacyOriginalName($document);

            return $this->artifactPersistence->finalizeSourceArtifact(
                stagedArtifact: $stagedArtifact,
                document: $document,
                originalName: $legacyOriginalName,
                createdByUserId: $actorUserId ?? $this->ownerPosition($document)?->user_id,
                sourceSystem: 'application',
                sourceReferenceType: 'document',
                sourceReferenceId: (string) $document->getKey(),
                metadata: [
                    'legacy_document_type' => (string) $document->src_type,
                    'legacy_payment_type' => (string) $document->payment_type,
                    'legacy_stored_name' => (string) $document->src_name,
                    'original_name_available' => $legacyOriginalName !== null,
                    'provisioned_from' => 'payment_controller_upload',
                ],
            );
        } catch (Throwable $exception) {
            $this->artifactPersistence->discardStagedArtifact($stagedArtifact);

            throw $exception;
        }
    }

    private function provisionWorkflow(
        Document $document,
        Document $rootDocument,
        DocumentArtifact $artifact,
        DocumentSigningWorkflowDefinition $definition,
        ?int $actorUserId,
        ?int $actorUserPositionId,
        bool $actorIsActing,
    ): DocumentSigningWorkflow {
        return DB::transaction(function () use (
            $document,
            $rootDocument,
            $artifact,
            $definition,
            $actorUserId,
            $actorUserPositionId,
            $actorIsActing,
        ): DocumentSigningWorkflow {
            /** @var Document $lockedDocument */
            $lockedDocument = Document::withTrashed()
                ->lockForUpdate()
                ->findOrFail($document->getKey());
            /** @var DocumentSigningWorkflow|null $latestWorkflow */
            $latestWorkflow = DocumentSigningWorkflow::query()
                ->where('document_id', $lockedDocument->getKey())
                ->orderByDesc('cycle_number')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $cycleNumber = 1;
            $previousWorkflowId = null;

            if ($latestWorkflow instanceof DocumentSigningWorkflow) {
                if ((int) $latestWorkflow->current_artifact_id === (int) $artifact->getKey()) {
                    $this->assertExistingWorkflowMatches($latestWorkflow, $artifact, $definition);

                    return $latestWorkflow;
                }

                if ($latestWorkflow->status !== DocumentSigningWorkflowStatus::Rejected) {
                    throw new EsignInvariantViolationException('canonical_workflow_definition_conflict');
                }

                $cycleNumber = (int) $latestWorkflow->cycle_number + 1;
                $previousWorkflowId = (int) $latestWorkflow->getKey();
            }

            $ownerPosition = $this->ownerPosition($document);
            $candidatePositions = $this->candidatePositions($document);
            $assignments = [];

            foreach ($definition->roleCodes as $sequence => $roleCode) {
                $assignments[$sequence + 1] = $this->assignedPositionForRole($candidatePositions, $roleCode);
            }

            $unresolvedSequences = array_keys(array_filter(
                $assignments,
                static fn (?UserPosition $position): bool => ! $position instanceof UserPosition,
            ));

            /** @var DocumentSigningWorkflow $workflow */
            $workflow = DocumentSigningWorkflow::query()->create([
                'public_id' => (string) Str::uuid(),
                'document_id' => $lockedDocument->getKey(),
                'root_document_id' => $rootDocument->getKey(),
                'payment_type' => Str::upper((string) $lockedDocument->payment_type),
                'document_type' => Str::upper((string) $lockedDocument->src_type),
                'workflow_variant' => $definition->variant,
                'definition_version' => $definition->version,
                'cycle_number' => $cycleNumber,
                'status' => DocumentSigningWorkflowStatus::Draft,
                'current_artifact_id' => $artifact->getKey(),
                'unit_kerja_id' => $lockedDocument->id_unit_kerja,
                'instansi_id' => $lockedDocument->unitKerja?->instansi_id,
                'owner_user_id' => $ownerPosition?->user_id,
                'owner_user_position_id' => $ownerPosition?->getKey(),
                'source_system' => 'application',
                'metadata' => [
                    'provisioned_from' => 'payment_controller_upload',
                    'assignment_state' => $unresolvedSequences === [] ? 'resolved' : 'partial',
                    'previous_workflow_id' => $previousWorkflowId,
                    'revision_cycle' => $cycleNumber > 1,
                    'unresolved_sequences' => $unresolvedSequences,
                ],
            ]);

            foreach ($definition->roleCodes as $offset => $roleCode) {
                $sequence = $offset + 1;
                $assignedPosition = $assignments[$sequence];

                DocumentSigningStep::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'document_signing_workflow_id' => $workflow->getKey(),
                    'sequence' => $sequence,
                    'step_type' => 'sign',
                    'role_code' => $roleCode,
                    'assigned_user_id' => $assignedPosition?->user_id,
                    'assigned_user_position_id' => $assignedPosition?->getKey(),
                    'assigned_unit_kerja_id' => $assignedPosition?->unit_kerja_id,
                    'assigned_instansi_id' => $assignedPosition?->instansi_id,
                    'status' => DocumentSigningStepStatus::Pending,
                    'is_required' => true,
                    'placement_required' => true,
                    'source_artifact_id' => $sequence === 1 ? $artifact->getKey() : null,
                    'assignment_snapshot' => $assignedPosition instanceof UserPosition
                        ? $this->assignmentSnapshot($assignedPosition)
                        : null,
                    'metadata' => [
                        'assignment_state' => $assignedPosition instanceof UserPosition ? 'resolved' : 'unresolved',
                        'definition_version' => $definition->version,
                    ],
                ]);
            }

            DocumentSigningWorkflowEvent::query()->create([
                'public_id' => (string) Str::uuid(),
                'document_signing_workflow_id' => $workflow->getKey(),
                'event_type' => DocumentSigningWorkflowEventType::WorkflowCreated,
                'to_workflow_status' => DocumentSigningWorkflowStatus::Draft,
                'actor_user_id' => $actorUserId,
                'actor_user_position_id' => $actorUserPositionId,
                'actor_is_acting' => $actorIsActing,
                'metadata' => [
                    'definition_version' => $definition->version,
                    'previous_workflow_id' => $previousWorkflowId,
                    'role_codes' => $definition->roleCodes,
                    'revision_cycle' => $cycleNumber > 1,
                    'workflow_variant' => $definition->variant,
                    'unresolved_sequences' => $unresolvedSequences,
                ],
                'occurred_at' => now(),
            ]);

            return $workflow;
        }, attempts: 3);
    }

    private function rootDocument(Document $document): Document
    {
        $root = $document;
        $visited = [(int) $document->getKey() => true];

        for ($depth = 0; $depth < 32; $depth++) {
            $parentId = $root->reference_id ?? $root->parent_id;

            if ($parentId === null || isset($visited[(int) $parentId])) {
                break;
            }

            /** @var Document|null $parent */
            $parent = Document::withTrashed()
                ->with(['uploadedByPosition.jabatan'])
                ->find($parentId);

            if (! $parent instanceof Document
                || Str::upper((string) $parent->payment_type) !== Str::upper((string) $document->payment_type)) {
                break;
            }

            $visited[(int) $parent->getKey()] = true;
            $root = $parent;
        }

        return $root;
    }

    private function ownerPosition(Document $document): ?UserPosition
    {
        return $this->canonicalPosition($document->uploadedByPosition);
    }

    private function legacyOriginalName(Document $document): ?string
    {
        $name = basename(str_replace('\\', '/', trim((string) $document->src_name)));
        $filenameWithoutExtension = pathinfo($name, PATHINFO_FILENAME);

        if ($name === '' || Str::isUuid($filenameWithoutExtension)) {
            return null;
        }

        return $name;
    }

    /** @return list<UserPosition> */
    private function candidatePositions(Document $document): array
    {
        $positions = [];

        foreach ([$document->uploadedByPosition, $document->recipientPosition] as $position) {
            $canonicalPosition = $this->canonicalPosition($position);

            if ($canonicalPosition instanceof UserPosition) {
                $positions[(int) $canonicalPosition->getKey()] = $canonicalPosition;
            }
        }

        return array_values($positions);
    }

    private function canonicalPosition(?UserPosition $position): ?UserPosition
    {
        if (! $position instanceof UserPosition) {
            return null;
        }

        try {
            $canonicalPosition = $this->positionIdentityResolver->canonicalPosition($position);
        } catch (LogicException) {
            return null;
        }

        $canonicalPosition->loadMissing('jabatan');

        return $canonicalPosition;
    }

    /** @param list<UserPosition> $positions */
    private function assignedPositionForRole(array $positions, string $roleCode): ?UserPosition
    {
        foreach ($positions as $position) {
            $candidateRole = Str::of((string) $position->jabatan?->kode)
                ->trim()
                ->upper()
                ->replace('-', '_')
                ->toString();

            if ($candidateRole === $roleCode && $position->isAvailableForSelection()) {
                return $position;
            }
        }

        return null;
    }

    /** @return array<string, int|string> */
    private function assignmentSnapshot(UserPosition $position): array
    {
        return [
            'user_id' => (int) $position->user_id,
            'user_position_id' => (int) $position->getKey(),
            'role_code' => (string) $position->jabatan?->kode,
            'unit_kerja_id' => (int) $position->unit_kerja_id,
            'instansi_id' => (int) $position->instansi_id,
            'source' => 'legacy_document_assignment',
        ];
    }

    private function assertExistingWorkflowMatches(
        DocumentSigningWorkflow $workflow,
        DocumentArtifact $artifact,
        DocumentSigningWorkflowDefinition $definition,
    ): void {
        $existingRoleCodes = $workflow->steps()
            ->orderBy('sequence')
            ->pluck('role_code')
            ->all();

        if ((int) $workflow->current_artifact_id !== (int) $artifact->getKey()
            || $workflow->workflow_variant !== $definition->variant
            || (int) $workflow->definition_version !== $definition->version
            || $existingRoleCodes !== $definition->roleCodes) {
            throw new EsignInvariantViolationException('canonical_workflow_definition_conflict');
        }
    }
}
