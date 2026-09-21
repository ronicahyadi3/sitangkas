<?php

namespace App\Actions\Esign;

use App\Data\Esign\SigningSessionData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use App\Services\Esign\DocumentArtifactIntegrityService;
use App\Services\Esign\EphemeralSigningSessionStore;
use App\Services\Esign\SignerIdentityResolver;
use App\Services\User\YearAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CreateSigningSession
{
    public function __construct(
        private EsignAuthorizationService $authorization,
        private SignerIdentityResolver $signerIdentityResolver,
        private DocumentArtifactIntegrityService $artifactIntegrity,
        private EphemeralSigningSessionStore $sessions,
        private CurrentUserContext $currentUserContext,
        private YearAccessService $yearAccess,
        private ConfigRepository $config,
        private Request $request,
    ) {}

    public function handle(User $user, DocumentSigningStep $step): SigningSessionData
    {
        $this->authorization->placeSignature($user, $step)->authorize();
        $signer = $this->signerIdentityResolver->resolve($user, $step);
        $workflow = $step->workflow()->first();
        $artifact = $step->sourceArtifact()->first();
        $realPosition = $this->currentUserContext->realActivePosition($this->request);
        $effectivePosition = $this->currentUserContext->activePosition($this->request);

        if (! $workflow instanceof DocumentSigningWorkflow
            || ! $artifact instanceof DocumentArtifact
            || ! $realPosition instanceof UserPosition
            || ! $effectivePosition instanceof UserPosition
            || $this->currentUserContext->effectiveContextIsActing($this->request)) {
            throw new EsignInvariantViolationException('signing_session_context_invalid');
        }

        $effectivePosition->loadMissing('jabatan');
        $roleCode = $effectivePosition->jabatan?->kode;

        if (! is_string($roleCode) || $roleCode === '') {
            throw new EsignInvariantViolationException('signing_session_role_missing');
        }

        $this->artifactIntegrity->assertReadablePdf($artifact);

        $createdAt = CarbonImmutable::now();
        $expiresAt = $createdAt->addMinutes($this->ttlMinutes());
        $session = new SigningSessionData(
            sessionId: (string) Str::uuid(),
            documentId: (int) $workflow->document_id,
            workflowId: (int) $workflow->getKey(),
            workflowLockVersion: (int) $workflow->lock_version,
            stepId: (int) $step->getKey(),
            sourceArtifactId: (int) $artifact->getKey(),
            sourceArtifactVersion: (int) $artifact->version,
            sourceArtifactSha256: (string) $artifact->file_sha256,
            actorUserId: (int) $user->getKey(),
            realUserPositionId: (int) $realPosition->getKey(),
            effectiveRoleCode: Str::of($roleCode)->upper()->replace('-', '_')->toString(),
            effectiveUnitKerjaId: (int) $effectivePosition->unit_kerja_id,
            effectiveInstansiId: (int) $effectivePosition->instansi_id,
            selectedYear: $this->yearAccess->selectedYear(),
            signerUserId: $signer->userId,
            signerUserPositionId: $signer->userPositionId,
            signerName: $signer->name,
            maskedNik: $signer->maskedNik,
            placementRequired: (bool) $step->placement_required,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );

        $this->sessions->put($session);

        return $session;
    }

    private function ttlMinutes(): int
    {
        $ttlMinutes = $this->config->get('esign.signing_session.ttl_minutes', 15);

        if (! is_int($ttlMinutes) || $ttlMinutes < 1 || $ttlMinutes > 60) {
            throw new EsignInvariantViolationException('signing_session_ttl_invalid');
        }

        return $ttlMinutes;
    }
}
