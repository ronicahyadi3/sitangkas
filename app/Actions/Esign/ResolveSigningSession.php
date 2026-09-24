<?php

namespace App\Actions\Esign;

use App\Data\Esign\SigningSessionContextData;
use App\Data\Esign\SigningSessionData;
use App\Exceptions\Esign\SigningSessionConflictException;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use App\Services\Esign\EphemeralPreparedRenditionStore;
use App\Services\Esign\EphemeralSigningSessionStore;
use App\Services\User\YearAccessService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ResolveSigningSession
{
    public function __construct(
        private EphemeralSigningSessionStore $sessions,
        private EphemeralPreparedRenditionStore $preparedRenditions,
        private EsignAuthorizationService $authorization,
        private CurrentUserContext $currentUserContext,
        private YearAccessService $yearAccess,
        private Request $request,
    ) {}

    public function handle(User $user, string $sessionId): SigningSessionContextData
    {
        $session = $this->sessions->get($sessionId, (int) $user->getKey());

        if (! $session instanceof SigningSessionData) {
            throw (new ModelNotFoundException)->setModel(SigningSessionData::class, [$sessionId]);
        }

        $workflow = DocumentSigningWorkflow::query()->find($session->workflowId);
        $step = DocumentSigningStep::query()->find($session->stepId);
        $artifact = DocumentArtifact::query()->find($session->sourceArtifactId);
        $realPosition = $this->currentUserContext->realActivePosition($this->request);
        $effectivePosition = $this->currentUserContext->activePosition($this->request);

        if (! $workflow instanceof DocumentSigningWorkflow
            || ! $step instanceof DocumentSigningStep
            || ! $artifact instanceof DocumentArtifact
            || ! $realPosition instanceof UserPosition
            || ! $effectivePosition instanceof UserPosition
            || $this->currentUserContext->effectiveContextIsActing($this->request)
            || (int) $workflow->document_id !== $session->documentId
            || (int) $workflow->getKey() !== (int) $step->document_signing_workflow_id
            || (int) $workflow->lock_version !== $session->workflowLockVersion
            || (int) $workflow->current_artifact_id !== $session->sourceArtifactId
            || (int) $step->source_artifact_id !== $session->sourceArtifactId
            || (int) $artifact->version !== $session->sourceArtifactVersion
            || ! hash_equals($session->sourceArtifactSha256, (string) $artifact->file_sha256)
            || ! $artifact->is_current
            || (int) $realPosition->getKey() !== $session->realUserPositionId
            || (int) $effectivePosition->unit_kerja_id !== $session->effectiveUnitKerjaId
            || (int) $effectivePosition->instansi_id !== $session->effectiveInstansiId
            || $this->yearAccess->selectedYear() !== $session->selectedYear) {
            $this->preparedRenditions->forget($sessionId, (int) $user->getKey());
            $this->sessions->forget($sessionId);

            throw new SigningSessionConflictException('esign.signing_session_context_changed');
        }

        $effectivePosition->loadMissing('jabatan');
        $roleCode = Str::of((string) $effectivePosition->jabatan?->kode)
            ->trim()
            ->upper()
            ->replace('-', '_')
            ->toString();

        if (! hash_equals($session->effectiveRoleCode, $roleCode)) {
            $this->preparedRenditions->forget($sessionId, (int) $user->getKey());
            $this->sessions->forget($sessionId);

            throw new SigningSessionConflictException('esign.signing_session_role_changed');
        }

        $this->authorization->placeSignature($user, $step)->authorize();

        return new SigningSessionContextData(
            session: $session,
            workflow: $workflow,
            step: $step,
            artifact: $artifact,
        );
    }
}
