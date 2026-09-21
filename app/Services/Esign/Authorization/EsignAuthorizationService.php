<?php

namespace App\Services\Esign\Authorization;

use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Enums\Esign\EsignAttemptStatus;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\User\YearAccessService;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class EsignAuthorizationService
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private YearAccessService $yearAccess,
        private Request $request,
    ) {}

    public function viewWorkflow(User $user, DocumentSigningWorkflow $workflow): Response
    {
        $context = $this->contextFor($user);

        if ($context === null || ! $this->matchesSelectedYear($workflow)) {
            return $this->notFound();
        }

        $realPosition = $context['real'];
        $effectivePosition = $context['effective'];

        if ((int) $workflow->owner_user_id === (int) $user->getKey()
            || (int) $workflow->owner_user_position_id === (int) $realPosition->getKey()) {
            return Response::allow();
        }

        $isParticipant = $workflow->steps()
            ->where(function (Builder $query) use ($user, $realPosition): void {
                $query->where('assigned_user_id', $user->getKey())
                    ->orWhere('assigned_user_position_id', $realPosition->getKey());
            })
            ->exists();

        if ($isParticipant) {
            return Response::allow();
        }

        if ($workflow->unit_kerja_id !== null
            && (int) $workflow->unit_kerja_id === (int) $effectivePosition->unit_kerja_id) {
            return Response::allow();
        }

        if ($this->hasParentScopeAfterHandoff($workflow, $effectivePosition)) {
            return Response::allow();
        }

        return $this->notFound();
    }

    public function downloadWorkflow(User $user, DocumentSigningWorkflow $workflow): Response
    {
        $view = $this->viewWorkflow($user, $workflow);

        if ($view->denied()) {
            return $view;
        }

        $effectivePosition = $this->currentUserContext->activePosition($this->request);

        if (! $effectivePosition instanceof UserPosition
            || $this->roleCode($effectivePosition) === 'AUDITOR') {
            return $this->notFound();
        }

        return Response::allow();
    }

    public function viewStep(User $user, DocumentSigningStep $step): Response
    {
        $workflow = $this->workflowForStep($step);

        return $workflow instanceof DocumentSigningWorkflow
            ? $this->viewWorkflow($user, $workflow)
            : $this->notFound();
    }

    public function placeSignature(User $user, DocumentSigningStep $step): Response
    {
        return $this->selfSignPrerequisites($user, $step);
    }

    public function signStep(User $user, DocumentSigningStep $step): Response
    {
        $prerequisites = $this->selfSignPrerequisites($user, $step);

        if ($prerequisites->denied()) {
            return $prerequisites;
        }

        if ($step->attempts()->exists()) {
            return Response::deny(
                'Langkah ini sudah mempunyai riwayat percobaan TTE. Gunakan alur retry atau rekonsiliasi yang sesuai.',
                'esign_step_requires_retry_or_reconciliation',
            );
        }

        return Response::allow();
    }

    public function retrySign(User $user, DocumentSigningStep $step): Response
    {
        $prerequisites = $this->selfSignPrerequisites($user, $step);

        if ($prerequisites->denied()) {
            return $prerequisites;
        }

        $latestAttempt = $step->attempts()
            ->latest('attempt_number')
            ->first();

        if ($latestAttempt === null
            || $latestAttempt->status !== EsignAttemptStatus::Failed
            || ! $latestAttempt->retryable) {
            return Response::deny(
                'Percobaan TTE terakhir tidak dapat diulang secara langsung.',
                'esign_attempt_not_retryable',
            );
        }

        return Response::allow();
    }

    public function rejectStep(User $user, DocumentSigningStep $step): Response
    {
        $assignment = $this->assignedActorPrerequisites($user, $step);

        if ($assignment->denied()) {
            return $assignment;
        }

        $workflow = $this->workflowForStep($step);

        if (! $workflow instanceof DocumentSigningWorkflow
            || $workflow->status !== DocumentSigningWorkflowStatus::Active
            || $step->status !== DocumentSigningStepStatus::Active
            || (int) $workflow->current_sequence !== (int) $step->sequence) {
            return Response::deny(
                'Hanya langkah workflow aktif yang dapat ditolak.',
                'esign_step_not_active',
            );
        }

        $artifact = $workflow->currentArtifact()->first();

        if (! $artifact instanceof DocumentArtifact
            || ! $this->yearAccess->canWrite(
                $this->currentUserContext->realActivePosition($this->request),
                (int) $artifact->document_year,
            )) {
            return Response::deny(
                'Pengguna tidak memiliki izin tulis pada tahun dokumen.',
                'esign_workflow_year_not_writable',
            );
        }

        return Response::allow();
    }

    public function rejectPackage(User $user, DocumentSigningWorkflow $workflow): Response
    {
        if (Str::upper((string) $workflow->document_type) !== 'SP2D') {
            return Response::deny(
                'Penolakan seluruh paket hanya tersedia pada workflow SP2D.',
                'esign_package_rejection_not_supported',
            );
        }

        if (! in_array($workflow->status, [
            DocumentSigningWorkflowStatus::Active,
            DocumentSigningWorkflowStatus::Completed,
        ], true)) {
            return Response::deny(
                'Paket SP2D tidak berada pada state yang dapat ditolak.',
                'esign_package_rejection_state_invalid',
            );
        }

        $currentStep = $workflow->steps()
            ->where('sequence', $workflow->current_sequence)
            ->first();

        if (! $currentStep instanceof DocumentSigningStep) {
            return Response::deny(
                'Langkah aktif SP2D tidak ditemukan.',
                'esign_current_step_missing',
            );
        }

        $assignment = $this->assignedActorPrerequisites($user, $currentStep);

        if ($assignment->denied()) {
            return $assignment;
        }

        if (! in_array($this->roleCode($this->currentUserContext->activePosition($this->request)), [
            'BUD',
            'KUASA_BUD',
        ], true)) {
            return Response::deny(
                'Hanya BUD atau Kuasa BUD yang ditugaskan dapat menolak paket SP2D.',
                'esign_package_rejection_role_mismatch',
            );
        }

        $artifact = $workflow->currentArtifact()->first();

        if (! $artifact instanceof DocumentArtifact
            || ! $this->yearAccess->canWrite(
                $this->currentUserContext->realActivePosition($this->request),
                (int) $artifact->document_year,
            )) {
            return Response::deny(
                'Pengguna tidak memiliki izin tulis pada tahun dokumen.',
                'esign_workflow_year_not_writable',
            );
        }

        $sp2dDocument = $workflow->document()->first();

        if ($sp2dDocument === null || $sp2dDocument->finished_at !== null) {
            return Response::deny(
                'Paket yang sudah diselesaikan BANK tidak dapat ditolak.',
                'esign_package_already_finished_by_bank',
            );
        }

        return Response::allow();
    }

    public function viewArtifact(User $user, DocumentArtifact $artifact): Response
    {
        if ($artifact->document_id === null || ! $this->artifactMatchesSelectedYear($artifact)) {
            return $this->notFound();
        }

        $context = $this->contextFor($user);

        if ($context === null) {
            return $this->notFound();
        }

        if ((int) $artifact->created_by_user_id === (int) $user->getKey()) {
            return Response::allow();
        }

        $workflows = DocumentSigningWorkflow::query()
            ->where('document_id', $artifact->document_id)
            ->orderByDesc('cycle_number')
            ->get();

        foreach ($workflows as $workflow) {
            if ($this->viewWorkflow($user, $workflow)->allowed()) {
                return Response::allow();
            }
        }

        $document = $artifact->document()->first();

        if ($document !== null
            && ((int) $document->uploaded_by === (int) $context['real']->getKey()
                || (int) $document->id_unit_kerja === (int) $context['effective']->unit_kerja_id)) {
            return Response::allow();
        }

        return $this->notFound();
    }

    public function downloadArtifact(User $user, DocumentArtifact $artifact): Response
    {
        $view = $this->viewArtifact($user, $artifact);

        if ($view->denied()) {
            return $view;
        }

        $effectivePosition = $this->currentUserContext->activePosition($this->request);

        if (! $effectivePosition instanceof UserPosition
            || $this->roleCode($effectivePosition) === 'AUDITOR') {
            return $this->notFound();
        }

        return Response::allow();
    }

    private function selfSignPrerequisites(User $user, DocumentSigningStep $step): Response
    {
        $assignment = $this->assignedActorPrerequisites($user, $step);

        if ($assignment->denied()) {
            return $assignment;
        }

        $workflow = $this->workflowForStep($step);

        if (! $workflow instanceof DocumentSigningWorkflow
            || $workflow->status !== DocumentSigningWorkflowStatus::Active
            || $step->status !== DocumentSigningStepStatus::Active
            || (int) $workflow->current_sequence !== (int) $step->sequence) {
            return Response::deny(
                'Dokumen belum berada pada langkah TTE aktif pengguna ini.',
                'esign_step_not_current',
            );
        }

        if ($step->source_artifact_id === null
            || (int) $workflow->current_artifact_id !== (int) $step->source_artifact_id) {
            return Response::deny(
                'Artifact sumber TTE tidak lagi sesuai dengan workflow aktif.',
                'esign_source_artifact_conflict',
            );
        }

        $sourceArtifact = $step->sourceArtifact()->first();

        if (! $sourceArtifact instanceof DocumentArtifact
            || ! $sourceArtifact->is_current
            || $sourceArtifact->document_id !== $workflow->document_id
            || ! $this->artifactMatchesSelectedYear($sourceArtifact)
            || ! $this->yearAccess->canWrite(
                $this->currentUserContext->realActivePosition($this->request),
                (int) $sourceArtifact->document_year,
            )) {
            return Response::deny(
                'Artifact atau izin tahun dokumen tidak memenuhi prasyarat TTE.',
                'esign_artifact_or_year_not_authorized',
            );
        }

        $hasIncompletePreviousStep = $workflow->steps()
            ->where('sequence', '<', $step->sequence)
            ->whereNotIn('status', [
                DocumentSigningStepStatus::Completed->value,
                DocumentSigningStepStatus::Skipped->value,
            ])
            ->exists();

        if ($hasIncompletePreviousStep) {
            return Response::deny(
                'Urutan penandatangan sebelumnya belum selesai.',
                'esign_previous_step_incomplete',
            );
        }

        $hasBlockingAttempt = $step->attempts()
            ->whereIn('status', [
                EsignAttemptStatus::Prepared->value,
                EsignAttemptStatus::Signing->value,
                EsignAttemptStatus::Validating->value,
                EsignAttemptStatus::Succeeded->value,
                EsignAttemptStatus::Unknown->value,
            ])
            ->exists();

        if ($hasBlockingAttempt) {
            return Response::deny(
                'Langkah ini masih mempunyai attempt aktif, berhasil, atau membutuhkan rekonsiliasi.',
                'esign_step_has_blocking_attempt',
            );
        }

        return Response::allow();
    }

    private function assignedActorPrerequisites(User $user, DocumentSigningStep $step): Response
    {
        $context = $this->contextFor($user);

        if ($context === null) {
            return Response::deny(
                'Konteks pengguna atau posisi aktif tidak valid.',
                'esign_actor_context_invalid',
            );
        }

        if ($context['is_acting']) {
            return Response::deny(
                'Acting context Admin Super tidak dapat digunakan untuk tanda tangan elektronik.',
                'esign_acting_context_cannot_self_sign',
            );
        }

        $workflow = $this->workflowForStep($step);

        if (! $workflow instanceof DocumentSigningWorkflow
            || ! $this->matchesSelectedYear($workflow)) {
            return Response::deny(
                'Workflow tidak berada pada tahun aktif pengguna.',
                'esign_workflow_year_mismatch',
            );
        }

        $position = $context['real'];

        if ($step->assigned_user_id === null
            || $step->assigned_user_position_id === null
            || (int) $step->assigned_user_id !== (int) $user->getKey()
            || (int) $step->assigned_user_position_id !== (int) $position->getKey()) {
            return Response::deny(
                'Pengguna bukan signer yang ditugaskan pada langkah ini.',
                'esign_signer_assignment_mismatch',
            );
        }

        if ($step->assigned_unit_kerja_id !== null
            && (int) $step->assigned_unit_kerja_id !== (int) $position->unit_kerja_id) {
            return Response::deny(
                'Unit kerja signer tidak sesuai assignment.',
                'esign_signer_unit_mismatch',
            );
        }

        if ($step->assigned_instansi_id !== null
            && (int) $step->assigned_instansi_id !== (int) $position->instansi_id) {
            return Response::deny(
                'Instansi signer tidak sesuai assignment.',
                'esign_signer_instansi_mismatch',
            );
        }

        if ($this->canonicalRoleCode((string) $step->role_code) !== $this->roleCode($position)) {
            return Response::deny(
                'Jabatan signer tidak sesuai definisi langkah workflow.',
                'esign_signer_role_mismatch',
            );
        }

        return Response::allow();
    }

    /**
     * @return array{real: UserPosition, effective: UserPosition, is_acting: bool}|null
     */
    private function contextFor(User $user): ?array
    {
        if (! $user->isActive() || $user->isLocked()) {
            return null;
        }

        $requestUser = $this->currentUserContext->user($this->request);
        $realPosition = $this->currentUserContext->realActivePosition($this->request);
        $effectivePosition = $this->currentUserContext->activePosition($this->request);

        if (! $requestUser instanceof User
            || (int) $requestUser->getKey() !== (int) $user->getKey()
            || ! $realPosition instanceof UserPosition
            || ! $effectivePosition instanceof UserPosition
            || (int) $realPosition->user_id !== (int) $user->getKey()
            || ! $realPosition->isAvailableForSelection()) {
            return null;
        }

        return [
            'real' => $realPosition,
            'effective' => $effectivePosition,
            'is_acting' => $this->currentUserContext->effectiveContextIsActing($this->request),
        ];
    }

    private function hasParentScopeAfterHandoff(
        DocumentSigningWorkflow $workflow,
        UserPosition $effectivePosition,
    ): bool {
        if ($workflow->status !== DocumentSigningWorkflowStatus::Active
            || $workflow->instansi_id === null
            || (int) $workflow->instansi_id !== (int) $effectivePosition->instansi_id) {
            return false;
        }

        return $workflow->steps()
            ->where('sequence', $workflow->current_sequence)
            ->where('assigned_unit_kerja_id', $effectivePosition->unit_kerja_id)
            ->exists();
    }

    private function matchesSelectedYear(DocumentSigningWorkflow $workflow): bool
    {
        $artifact = $workflow->currentArtifact()->first();

        return $artifact instanceof DocumentArtifact
            && $this->artifactMatchesSelectedYear($artifact);
    }

    private function artifactMatchesSelectedYear(DocumentArtifact $artifact): bool
    {
        return $artifact->document_year !== null
            && (int) $artifact->document_year === $this->yearAccess->selectedYear();
    }

    private function workflowForStep(DocumentSigningStep $step): ?DocumentSigningWorkflow
    {
        $workflow = $step->workflow()->first();

        return $workflow instanceof DocumentSigningWorkflow ? $workflow : null;
    }

    private function roleCode(?UserPosition $position): ?string
    {
        if (! $position instanceof UserPosition) {
            return null;
        }

        $position->loadMissing('jabatan');
        $code = $position->jabatan?->kode;

        return is_string($code) && $code !== '' ? $this->canonicalRoleCode($code) : null;
    }

    private function canonicalRoleCode(string $roleCode): string
    {
        return Str::of($roleCode)
            ->trim()
            ->upper()
            ->replace('-', '_')
            ->toString();
    }

    private function notFound(): Response
    {
        return Response::denyAsNotFound(
            'Dokumen tidak ditemukan atau tidak dapat diakses.',
            'esign_document_not_accessible',
        );
    }
}
