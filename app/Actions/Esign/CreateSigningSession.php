<?php

namespace App\Actions\Esign;

use App\Data\Esign\SigningSessionData;
use App\Enums\Esign\DocumentArtifactType;
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
use App\Services\Esign\LsSppWorkflowHandoffService;
use App\Services\Esign\PdfPageGeometryInspector;
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
        private PdfPageGeometryInspector $pageGeometryInspector,
        private EphemeralSigningSessionStore $sessions,
        private LsSppWorkflowHandoffService $lsSppWorkflowHandoff,
        private CurrentUserContext $currentUserContext,
        private YearAccessService $yearAccess,
        private ConfigRepository $config,
        private Request $request,
    ) {}

    public function handle(User $user, DocumentSigningStep $step): SigningSessionData
    {
        $realPosition = $this->currentUserContext->realActivePosition($this->request);
        $effectivePosition = $this->currentUserContext->activePosition($this->request);
        $actorIsActing = $this->currentUserContext->effectiveContextIsActing($this->request);

        if ($user->isActive()
            && ! $user->isLocked()
            && $realPosition instanceof UserPosition
            && $effectivePosition instanceof UserPosition
            && $realPosition->isAvailableForSelection()
            && ! $actorIsActing) {
            $step = $this->lsSppWorkflowHandoff->prepareInitialStepForSigning(
                $step,
                $user,
                $realPosition,
                false,
            );
        }

        $this->authorization->placeSignature($user, $step)->authorize();
        $signer = $this->signerIdentityResolver->resolve($user, $step);
        $workflow = $step->workflow()->first();
        $artifact = $step->sourceArtifact()->first();

        if (! $workflow instanceof DocumentSigningWorkflow
            || ! $artifact instanceof DocumentArtifact
            || ! $realPosition instanceof UserPosition
            || ! $effectivePosition instanceof UserPosition
            || $actorIsActing) {
            throw new EsignInvariantViolationException('signing_session_context_invalid');
        }

        $effectivePosition->loadMissing('jabatan');
        $roleCode = $effectivePosition->jabatan?->kode;

        if (! is_string($roleCode) || $roleCode === '') {
            throw new EsignInvariantViolationException('signing_session_role_missing');
        }

        $pdfContents = $this->artifactIntegrity->readVerifiedPdfContents($artifact);
        $pageGeometries = array_map(
            static fn ($page): array => $page->toArray(),
            $this->pageGeometryInspector->inspect($pdfContents),
        );
        unset($pdfContents);

        $verifiedSignatureCount = $artifact->signatures()
            ->where('integrity_valid', true)
            ->where('certificate_trusted', true)
            ->count();
        $signatureState = match ($artifact->artifact_type) {
            DocumentArtifactType::BeforeSign => $verifiedSignatureCount === 0 ? 'unsigned' : null,
            DocumentArtifactType::IntermediateSign, DocumentArtifactType::AfterSign => $verifiedSignatureCount > 0 ? 'signed' : null,
            default => null,
        };

        if ($signatureState === null) {
            throw new EsignInvariantViolationException('artifact_signature_state_ambiguous');
        }

        $artifactMetadata = $artifact->metadata;
        $footerApplied = (is_array($artifactMetadata)
                && ($artifactMetadata['footer_applied'] ?? false) === true)
            || $artifact->decorations()
                ->where('decoration_type', 'footer')
                ->exists();

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
            signatureState: $signatureState,
            verifiedSignatureCount: $verifiedSignatureCount,
            footerApplied: $footerApplied,
            pageGeometries: $pageGeometries,
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
