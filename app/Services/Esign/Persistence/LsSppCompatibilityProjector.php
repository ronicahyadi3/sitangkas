<?php

declare(strict_types=1);

namespace App\Services\Esign\Persistence;

use App\Enums\Esign\DocumentArtifactType;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Enums\Esign\EsignAttemptStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\DocumentHistory;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignAttemptLegacyLink;
use App\Models\UserPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LsSppCompatibilityProjector
{
    public function supports(Document $document): bool
    {
        return Str::upper(trim((string) $document->payment_type)) === 'LS'
            && Str::upper(trim((string) $document->src_type)) === Document::TYPE_SPP
            && $document->reference_id === null
            && $document->parent_id === null;
    }

    public function projectSuccessfulSignature(
        EsignAttempt $attempt,
        DocumentArtifact $artifact,
        string $md5,
    ): void {
        $attemptId = (int) $attempt->getKey();
        $stepId = (int) $attempt->document_signing_step_id;
        $workflowId = (int) DocumentSigningStep::query()
            ->whereKey($stepId)
            ->valueOrFail('document_signing_workflow_id');
        $artifactId = (int) $artifact->getKey();

        DB::transaction(function () use (
            $attemptId,
            $stepId,
            $workflowId,
            $artifactId,
            $md5,
        ): void {
            /** @var DocumentSigningWorkflow $workflow */
            $workflow = DocumentSigningWorkflow::query()
                ->lockForUpdate()
                ->findOrFail($workflowId);
            /** @var DocumentSigningStep $step */
            $step = DocumentSigningStep::query()
                ->lockForUpdate()
                ->findOrFail($stepId);
            /** @var EsignAttempt $lockedAttempt */
            $lockedAttempt = EsignAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attemptId);

            $this->assertCanonicalState($lockedAttempt, $step, $workflow, $artifactId, $md5);

            $existingLinks = EsignAttemptLegacyLink::query()
                ->where('esign_attempt_id', $lockedAttempt->getKey())
                ->where('legacy_table', 'document_process')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($existingLinks->count() > 1) {
                throw new EsignInvariantViolationException('ls_spp_projection_duplicate_legacy_links');
            }

            /** @var EsignAttemptLegacyLink|null $existingLink */
            $existingLink = $existingLinks->first();

            if ($existingLink instanceof EsignAttemptLegacyLink) {
                $this->assertIdempotentReplay($existingLink, $lockedAttempt, $artifactId, $workflowId, $stepId);

                return;
            }

            /** @var Document $document */
            $document = Document::withTrashed()
                ->lockForUpdate()
                ->findOrFail($lockedAttempt->document_id);

            if (! $this->supports($document)) {
                throw new EsignInvariantViolationException('ls_spp_projection_document_not_root');
            }

            if ((int) $workflow->document_id !== (int) $document->getKey()
                || (int) $workflow->root_document_id !== (int) $document->getKey()
                || Str::upper(trim((string) $workflow->payment_type)) !== 'LS'
                || Str::upper(trim((string) $workflow->document_type)) !== Document::TYPE_SPP) {
                throw new EsignInvariantViolationException('ls_spp_projection_workflow_document_mismatch');
            }

            /** @var DocumentArtifact $lockedArtifact */
            $lockedArtifact = DocumentArtifact::query()
                ->lockForUpdate()
                ->findOrFail($artifactId);

            if ($lockedArtifact->artifact_type !== DocumentArtifactType::AfterSign
                || (int) $lockedArtifact->document_id !== (int) $document->getKey()
                || ! $lockedArtifact->is_current) {
                throw new EsignInvariantViolationException('ls_spp_projection_result_artifact_invalid');
            }

            /** @var UserPosition $signerPosition */
            $signerPosition = UserPosition::withTrashed()
                ->with('jabatan')
                ->findOrFail($lockedAttempt->signer_user_position_id);
            $jabatanId = (int) $signerPosition->jabatan_id;

            if ($jabatanId <= 0
                || $this->normalizeRoleCode($signerPosition->jabatan?->kode) !== $this->normalizeRoleCode($step->role_code)) {
                throw new EsignInvariantViolationException('ls_spp_projection_signer_role_mismatch');
            }

            $statusList = $document->status_list;
            $jabatanKey = (string) $jabatanId;

            if (! in_array($jabatanKey, $statusList, true)) {
                $statusList[] = $jabatanKey;
                $document->status = implode(',', $statusList);
            }

            if ($workflow->status === DocumentSigningWorkflowStatus::Completed
                && $document->signed_at === null) {
                $document->signed_at = now();
            }

            if ($document->isDirty()) {
                $document->save();
            }

            $srcName = $document->src_name ?: $lockedArtifact->stored_name;
            $history = DocumentHistory::query()->create([
                'id_user' => $signerPosition->getKey(),
                'id_unit_kerja' => $signerPosition->unit_kerja_id ?? $document->id_unit_kerja,
                'id_jabatan' => $jabatanId,
                'id_dokumen' => $document->getKey(),
                'src_name' => Str::substr((string) $srcName, 0, 55),
                'md5' => Str::lower($md5),
                'assigned_to' => Str::substr((string) ($document->assigned_to ?? ''), 0, 20) ?: null,
                'action' => DocumentHistory::ACTION_TTE,
            ]);

            EsignAttemptLegacyLink::query()->create([
                'esign_attempt_id' => $lockedAttempt->getKey(),
                'document_artifact_id' => $lockedArtifact->getKey(),
                'document_signing_workflow_id' => $workflow->getKey(),
                'document_signing_step_id' => $step->getKey(),
                'legacy_table' => 'document_process',
                'legacy_id' => $history->getKey(),
                'legacy_document_id' => $document->getKey(),
                'legacy_document_process_id' => $history->getKey(),
                'legacy_src_name' => Str::limit((string) $srcName, 150, ''),
                'legacy_checksum' => $lockedArtifact->file_sha256,
                'mapping_status' => 'mapped',
                'metadata' => [
                    'legacy_status_jabatan_id' => $jabatanId,
                    'projection' => 'canonical_ls_spp_success',
                    'workflow_cycle' => $workflow->cycle_number,
                    'workflow_sequence' => $step->sequence,
                ],
                'mapped_at' => now(),
            ]);
        }, attempts: 3);
    }

    private function assertCanonicalState(
        EsignAttempt $attempt,
        DocumentSigningStep $step,
        DocumentSigningWorkflow $workflow,
        int $artifactId,
        string $md5,
    ): void {
        if ($attempt->status !== EsignAttemptStatus::Succeeded) {
            throw new EsignInvariantViolationException('ls_spp_projection_attempt_not_succeeded');
        }

        if ((int) $attempt->document_signing_step_id !== (int) $step->getKey()
            || (int) $step->document_signing_workflow_id !== (int) $workflow->getKey()) {
            throw new EsignInvariantViolationException('ls_spp_projection_attempt_parent_mismatch');
        }

        if ($step->status !== DocumentSigningStepStatus::Completed
            || (int) $step->result_artifact_id !== $artifactId
            || (int) $attempt->result_artifact_id !== $artifactId
            || (int) $workflow->current_artifact_id !== $artifactId) {
            throw new EsignInvariantViolationException('ls_spp_projection_canonical_result_mismatch');
        }

        if (preg_match('/\A[a-f0-9]{32}\z/i', $md5) !== 1) {
            throw new EsignInvariantViolationException('ls_spp_projection_md5_invalid');
        }
    }

    private function assertIdempotentReplay(
        EsignAttemptLegacyLink $link,
        EsignAttempt $attempt,
        int $artifactId,
        int $workflowId,
        int $stepId,
    ): void {
        if ((int) $link->esign_attempt_id !== (int) $attempt->getKey()
            || (int) $link->document_artifact_id !== $artifactId
            || (int) $link->document_signing_workflow_id !== $workflowId
            || (int) $link->document_signing_step_id !== $stepId
            || (int) $link->legacy_document_id !== (int) $attempt->document_id
            || (int) $link->legacy_document_process_id !== (int) $link->legacy_id) {
            throw new EsignInvariantViolationException('ls_spp_projection_idempotency_conflict');
        }
    }

    private function normalizeRoleCode(mixed $roleCode): string
    {
        return Str::of((string) $roleCode)
            ->trim()
            ->upper()
            ->replace('-', '_')
            ->toString();
    }
}
