<?php

declare(strict_types=1);

namespace App\Services\Esign\Authorization;

use App\Enums\Esign\DocumentArtifactType;
use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Enums\Esign\EsignAttemptStatus;
use App\Exceptions\Esign\LsSppSubmitGateException;
use App\Models\Document;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignAttemptLegacyLink;
use App\Models\UserPosition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LsSppSubmitGate
{
    /** @var array<string, list<string>> */
    private const WORKFLOW_DEFINITIONS = [
        'bp' => ['BP', 'PPTK', 'PA'],
        'bpp' => ['BPP', 'PPTK', 'KPA'],
    ];

    /**
     * @return bool True when the canonical gate was applied, false for a pure legacy document.
     */
    public function assertInitialHandoffToPptk(
        Document $document,
        UserPosition $actorPosition,
    ): bool {
        $context = $this->lockedCanonicalContext($document);

        if ($context === null) {
            return false;
        }

        [$workflow, $steps] = $context;
        $actorRole = $this->actorRole($actorPosition);

        if (! in_array($actorRole, ['BP', 'BPP'], true)) {
            $this->deny(
                'canonical_handoff_actor_role_invalid',
                'Hanya BP atau BPP yang dapat mengirim dokumen ke PPTK.',
            );
        }

        $this->assertWorkflowVariantMatchesActor($workflow, $actorRole);
        $this->assertWorkflowAllowsStepHandoff($workflow);
        $step = $this->completedStepForRole($steps, $actorRole);
        $this->assertCompletedStepProof($document, $workflow, $step, $actorPosition);
        $this->assertCurrentArtifactMatchesStep($workflow, $step);

        return true;
    }

    /**
     * @param  list<int>  $legacySubmitList
     * @return bool True when the canonical gate was applied, false for a pure legacy document.
     */
    public function assertSubsequentHandoff(
        Document $document,
        UserPosition $actorPosition,
        array $legacySubmitList,
    ): bool {
        $context = $this->lockedCanonicalContext($document);

        if ($context === null) {
            return false;
        }

        [$workflow, $steps] = $context;
        $actorRole = $this->actorRole($actorPosition);

        if (! in_array($actorRole, ['BP', 'BPP', 'PPTK', 'PA', 'KPA'], true)) {
            $this->deny(
                'canonical_handoff_actor_role_invalid',
                'Jabatan aktif tidak termasuk alur TTE LS SPP.',
            );
        }

        $this->assertActorBelongsToWorkflow($workflow, $actorRole);

        $isFinalCashierHandoff = in_array($actorRole, ['BP', 'BPP'], true)
            && (in_array(5, $legacySubmitList, true) || in_array(6, $legacySubmitList, true));

        if ($isFinalCashierHandoff) {
            $this->assertCompletedWorkflow($document, $workflow, $steps, $actorPosition);

            return true;
        }

        $this->assertWorkflowAllowsStepHandoff($workflow);
        $step = $this->completedStepForRole($steps, $actorRole);
        $this->assertCompletedStepProof($document, $workflow, $step, $actorPosition);
        $this->assertCurrentArtifactMatchesStep($workflow, $step);

        return true;
    }

    /**
     * @return array{DocumentSigningWorkflow, Collection<int, DocumentSigningStep>}|null
     */
    private function lockedCanonicalContext(Document $document): ?array
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('LS SPP submit gate must run inside a database transaction.');
        }

        if (! $this->isRootLsSpp($document)) {
            $this->deny(
                'canonical_handoff_document_scope_invalid',
                'Submit gate hanya berlaku untuk dokumen induk LS SPP.',
            );
        }

        $workflows = DocumentSigningWorkflow::query()
            ->where('document_id', $document->getKey())
            ->orderByDesc('cycle_number')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        /** @var DocumentSigningWorkflow|null $workflow */
        $workflow = $workflows->first();

        if (! $workflow instanceof DocumentSigningWorkflow) {
            $hasCanonicalArtifact = DocumentArtifact::query()
                ->where('document_id', $document->getKey())
                ->exists();

            if ($hasCanonicalArtifact) {
                $this->deny(
                    'canonical_workflow_missing',
                    'Workflow TTE canonical belum tersedia. Dokumen belum dapat disubmit.',
                );
            }

            return null;
        }

        if ((int) $workflow->document_id !== (int) $document->getKey()
            || (int) $workflow->root_document_id !== (int) $document->getKey()
            || Str::upper(trim((string) $workflow->payment_type)) !== 'LS'
            || Str::upper(trim((string) $workflow->document_type)) !== Document::TYPE_SPP) {
            $this->deny(
                'canonical_workflow_document_mismatch',
                'Workflow TTE tidak sesuai dengan dokumen LS SPP ini.',
            );
        }

        $steps = DocumentSigningStep::query()
            ->where('document_signing_workflow_id', $workflow->getKey())
            ->orderBy('sequence')
            ->lockForUpdate()
            ->get();

        $this->assertWorkflowDefinition($workflow, $steps);

        return [$workflow, $steps];
    }

    /** @param Collection<int, DocumentSigningStep> $steps */
    private function assertWorkflowDefinition(
        DocumentSigningWorkflow $workflow,
        Collection $steps,
    ): void {
        $variant = Str::lower(trim((string) $workflow->workflow_variant));
        $expectedRoles = self::WORKFLOW_DEFINITIONS[$variant] ?? null;
        $actualRoles = $steps
            ->map(fn (DocumentSigningStep $step): string => $this->canonicalRole((string) $step->role_code))
            ->values()
            ->all();

        if ($expectedRoles === null || $actualRoles !== $expectedRoles) {
            $this->deny(
                'canonical_workflow_definition_mismatch',
                'Definisi urutan signer LS SPP tidak sesuai konfigurasi canonical.',
            );
        }

        if ($steps->contains(
            static fn (DocumentSigningStep $step): bool => ! $step->is_required,
        )) {
            $this->deny(
                'canonical_workflow_required_step_mismatch',
                'Seluruh langkah signer LS SPP harus berstatus wajib.',
            );
        }
    }

    private function assertWorkflowVariantMatchesActor(
        DocumentSigningWorkflow $workflow,
        string $actorRole,
    ): void {
        $expectedVariant = Str::lower($actorRole);

        if (Str::lower((string) $workflow->workflow_variant) !== $expectedVariant) {
            $this->deny(
                'canonical_workflow_variant_mismatch',
                'Jalur BP/BPP tidak sesuai dengan workflow TTE dokumen.',
            );
        }
    }

    private function assertActorBelongsToWorkflow(
        DocumentSigningWorkflow $workflow,
        string $actorRole,
    ): void {
        $expectedRoles = self::WORKFLOW_DEFINITIONS[Str::lower((string) $workflow->workflow_variant)] ?? [];

        if (! in_array($actorRole, $expectedRoles, true)) {
            $this->deny(
                'canonical_handoff_actor_workflow_mismatch',
                'Jabatan aktif tidak sesuai jalur signer canonical dokumen.',
            );
        }
    }

    private function assertWorkflowAllowsStepHandoff(DocumentSigningWorkflow $workflow): void
    {
        if (! in_array($workflow->status, [
            DocumentSigningWorkflowStatus::Active,
            DocumentSigningWorkflowStatus::Completed,
        ], true)) {
            $this->deny(
                'canonical_workflow_not_ready_for_handoff',
                'Workflow TTE belum aktif atau sedang membutuhkan peninjauan.',
            );
        }
    }

    /** @param Collection<int, DocumentSigningStep> $steps */
    private function completedStepForRole(Collection $steps, string $roleCode): DocumentSigningStep
    {
        $matchingSteps = $steps->filter(
            fn (DocumentSigningStep $step): bool => $this->canonicalRole((string) $step->role_code) === $roleCode,
        );

        if ($matchingSteps->count() !== 1) {
            $this->deny(
                'canonical_handoff_step_missing',
                'Langkah TTE untuk jabatan aktif tidak ditemukan atau ambigu.',
            );
        }

        /** @var DocumentSigningStep $step */
        $step = $matchingSteps->first();

        if ($step->status !== DocumentSigningStepStatus::Completed) {
            $this->deny(
                'canonical_handoff_step_not_completed',
                'TTE pada langkah jabatan aktif belum berhasil. Selesaikan TTE sebelum submit.',
            );
        }

        return $step;
    }

    private function assertCompletedStepProof(
        Document $document,
        DocumentSigningWorkflow $workflow,
        DocumentSigningStep $step,
        ?UserPosition $actorPosition = null,
    ): void {
        if ($step->result_artifact_id === null) {
            $this->deny(
                'canonical_handoff_result_artifact_missing',
                'Hasil TTE canonical belum tersedia untuk langkah ini.',
            );
        }

        $succeededAttempts = EsignAttempt::query()
            ->where('document_signing_step_id', $step->getKey())
            ->where('document_id', $document->getKey())
            ->where('status', EsignAttemptStatus::Succeeded->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($succeededAttempts->count() !== 1) {
            $this->deny(
                'canonical_handoff_succeeded_attempt_missing',
                'Bukti keberhasilan TTE canonical tidak tersedia atau ambigu.',
            );
        }

        /** @var EsignAttempt $attempt */
        $attempt = $succeededAttempts->first();

        if ((int) $attempt->result_artifact_id !== (int) $step->result_artifact_id) {
            $this->deny(
                'canonical_handoff_attempt_result_mismatch',
                'Hasil attempt TTE tidak sesuai dengan hasil langkah canonical.',
            );
        }

        if ($actorPosition instanceof UserPosition
            && (int) $attempt->signer_user_position_id !== (int) $actorPosition->getKey()) {
            $this->deny(
                'canonical_handoff_signer_position_mismatch',
                'Submit hanya dapat dilakukan oleh posisi yang menyelesaikan TTE pada langkah ini.',
            );
        }

        /** @var DocumentArtifact|null $resultArtifact */
        $resultArtifact = DocumentArtifact::query()
            ->whereKey($step->result_artifact_id)
            ->lockForUpdate()
            ->first();

        if (! $resultArtifact instanceof DocumentArtifact
            || $resultArtifact->artifact_type !== DocumentArtifactType::AfterSign
            || (int) $resultArtifact->document_id !== (int) $document->getKey()) {
            $this->deny(
                'canonical_handoff_result_artifact_invalid',
                'Artifact hasil TTE canonical tidak valid.',
            );
        }

        $projectionLinks = EsignAttemptLegacyLink::query()
            ->where('esign_attempt_id', $attempt->getKey())
            ->where('document_artifact_id', $resultArtifact->getKey())
            ->where('document_signing_workflow_id', $workflow->getKey())
            ->where('document_signing_step_id', $step->getKey())
            ->where('legacy_table', 'document_process')
            ->where('legacy_document_id', $document->getKey())
            ->where('mapping_status', 'mapped')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($projectionLinks->count() !== 1) {
            $this->deny(
                'canonical_handoff_legacy_projection_pending',
                'Sinkronisasi hasil TTE ke riwayat dokumen belum selesai. Silakan coba kembali.',
            );
        }

        if ($actorPosition instanceof UserPosition
            && ! in_array((string) $actorPosition->jabatan_id, $document->status_list, true)) {
            $this->deny(
                'canonical_handoff_legacy_status_pending',
                'Status TTE kompatibilitas belum tersinkron untuk jabatan aktif.',
            );
        }
    }

    private function assertCurrentArtifactMatchesStep(
        DocumentSigningWorkflow $workflow,
        DocumentSigningStep $step,
    ): void {
        if ((int) $workflow->current_artifact_id !== (int) $step->result_artifact_id) {
            $this->deny(
                'canonical_handoff_current_artifact_mismatch',
                'Artifact aktif workflow tidak sesuai hasil TTE langkah terakhir.',
            );
        }
    }

    /** @param Collection<int, DocumentSigningStep> $steps */
    private function assertCompletedWorkflow(
        Document $document,
        DocumentSigningWorkflow $workflow,
        Collection $steps,
        UserPosition $actorPosition,
    ): void {
        if ($workflow->status !== DocumentSigningWorkflowStatus::Completed) {
            $this->deny(
                'canonical_handoff_workflow_not_completed',
                'Seluruh urutan TTE BP/BPP, PPTK, dan PA/KPA harus selesai sebelum diteruskan.',
            );
        }

        $actorRole = $this->actorRole($actorPosition);
        $lastStep = null;

        foreach ($steps as $step) {
            if ($step->status !== DocumentSigningStepStatus::Completed) {
                $this->deny(
                    'canonical_handoff_required_step_not_completed',
                    'Masih ada langkah signer wajib yang belum selesai.',
                );
            }

            $position = $this->canonicalRole((string) $step->role_code) === $actorRole
                ? $actorPosition
                : null;
            $this->assertCompletedStepProof($document, $workflow, $step, $position);
            $lastStep = $step;
        }

        if (! $lastStep instanceof DocumentSigningStep
            || (int) $workflow->current_artifact_id !== (int) $lastStep->result_artifact_id
            || $document->signed_at === null) {
            $this->deny(
                'canonical_handoff_completed_workflow_projection_invalid',
                'Hasil akhir TTE belum siap diteruskan ke PPK SKPD.',
            );
        }
    }

    private function actorRole(UserPosition $position): string
    {
        $position->loadMissing('jabatan');
        $roleCode = $this->canonicalRole((string) $position->jabatan?->kode);

        if ($roleCode === '') {
            $this->deny(
                'canonical_handoff_actor_role_missing',
                'Kode jabatan posisi aktif tidak tersedia.',
            );
        }

        return $roleCode;
    }

    private function isRootLsSpp(Document $document): bool
    {
        return Str::upper(trim((string) $document->payment_type)) === 'LS'
            && Str::upper(trim((string) $document->src_type)) === Document::TYPE_SPP
            && $document->reference_id === null
            && $document->parent_id === null;
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
