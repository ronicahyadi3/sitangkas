<?php

namespace App\Actions\Esign;

use App\Data\Esign\CreateEsignAttemptData;
use App\Data\Esign\CreateVisibleEsignAttemptData;
use App\Data\Esign\SigningSessionContextData;
use App\Exceptions\Esign\SigningSessionConflictException;
use App\Jobs\Esign\PerformEsignAttempt;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\EsignAttempt;
use App\Models\User;
use App\Services\Auth\CurrentUserContext;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use App\Services\Esign\DocumentArtifactIntegrityService;
use App\Services\Esign\EphemeralPreparedRenditionStore;
use App\Services\Esign\EphemeralSigningSecretStore;
use App\Services\Esign\EphemeralSigningSessionStore;
use App\Services\Esign\Persistence\EsignAttemptPersistenceService;
use App\Services\Esign\Persistence\LegacyEsignLedgerWriter;
use App\Services\Esign\Persistence\VisibleEsignAttemptPersistenceService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

final class SignDocument
{
    public function __construct(
        private ResolveSigningSession $resolveSigningSession,
        private EphemeralSigningSessionStore $sessions,
        private EphemeralPreparedRenditionStore $renditions,
        private EphemeralSigningSecretStore $secrets,
        private EsignAuthorizationService $authorization,
        private DocumentArtifactIntegrityService $artifactIntegrity,
        private EsignAttemptPersistenceService $attempts,
        private VisibleEsignAttemptPersistenceService $visibleAttempts,
        private LegacyEsignLedgerWriter $legacyLedger,
        private CurrentUserContext $currentUserContext,
        private ConfigRepository $config,
        private Request $request,
    ) {}

    public function handle(
        User $user,
        string $sessionId,
        string $idempotencyKey,
        string $previewSha256,
        #[SensitiveParameter] string $passphrase,
        ?string $preparedRevision = null,
    ): EsignAttempt {
        $idempotentAttempt = $this->idempotentAttempt(
            $user,
            $sessionId,
            $idempotencyKey,
            $previewSha256,
            $preparedRevision,
        );

        if ($idempotentAttempt instanceof EsignAttempt) {
            return $idempotentAttempt;
        }

        $context = $this->resolveSigningSession->handle($user, $sessionId);

        if ($context->session->placementRequired) {
            return $this->handleVisible(
                user: $user,
                context: $context,
                idempotencyKey: $idempotencyKey,
                previewSha256: $previewSha256,
                passphrase: $passphrase,
                preparedRevision: $preparedRevision,
            );
        }

        if ($preparedRevision !== null) {
            throw new SigningSessionConflictException('esign.prepared_revision_not_allowed');
        }

        if (! hash_equals($context->session->sourceArtifactSha256, Str::lower($previewSha256))) {
            throw new SigningSessionConflictException('esign.preview_hash_mismatch');
        }

        $hasPreviousAttempt = $context->step->attempts()->exists();
        ($hasPreviousAttempt
            ? $this->authorization->retrySign($user, $context->step)
            : $this->authorization->signStep($user, $context->step)
        )->authorize();

        $inspection = $this->artifactIntegrity->assertReadablePdf($context->artifact);

        if (! hash_equals($context->session->sourceArtifactSha256, $inspection->sha256)) {
            throw new SigningSessionConflictException('esign.source_artifact_changed');
        }

        $secretReference = $this->secrets->put($passphrase, (int) $user->getKey());
        $correlationId = (string) Str::uuid();
        $requestFingerprint = $this->requestFingerprint($context->session->toArray());

        try {
            return DB::transaction(function () use (
                $user,
                $context,
                $idempotencyKey,
                $correlationId,
                $requestFingerprint,
                $secretReference,
                $inspection,
            ): EsignAttempt {
                $attempt = $this->attempts->create(new CreateEsignAttemptData(
                    documentSigningStepId: (int) $context->step->getKey(),
                    sourceArtifactId: (int) $context->artifact->getKey(),
                    idempotencyKey: Str::lower($idempotencyKey),
                    requestCorrelationId: $correlationId,
                    requestFingerprint: $requestFingerprint,
                    previewArtifactSha256: $context->session->sourceArtifactSha256,
                    actorUserId: (int) $user->getKey(),
                    actorUserPositionId: $context->session->realUserPositionId,
                    signerUserId: $context->session->signerUserId,
                    signerUserPositionId: $context->session->signerUserPositionId,
                    isActing: false,
                    effectiveRoleCode: $context->session->effectiveRoleCode,
                    effectiveUnitKerjaId: $context->session->effectiveUnitKerjaId,
                    effectiveInstansiId: $context->session->effectiveInstansiId,
                    actorContextSnapshot: $this->currentUserContext->snapshot($this->request),
                ));

                $this->legacyLedger->writeBefore(
                    attempt: $attempt,
                    artifact: $context->artifact,
                    maskedNik: $context->session->maskedNik,
                    md5: $inspection->md5,
                );

                if ($attempt->status->value === 'prepared') {
                    PerformEsignAttempt::dispatch(
                        (int) $attempt->getKey(),
                        $secretReference,
                    )->afterCommit();
                } else {
                    $this->secrets->forget($secretReference);
                }

                return $attempt;
            }, attempts: 3);
        } catch (\Throwable $exception) {
            $this->secrets->forget($secretReference);

            throw $exception;
        }
    }

    private function idempotentAttempt(
        User $user,
        string $sessionId,
        string $idempotencyKey,
        string $previewSha256,
        ?string $preparedRevision,
    ): ?EsignAttempt {
        $attempt = EsignAttempt::query()
            ->where('idempotency_key', Str::lower($idempotencyKey))
            ->first();

        if (! $attempt instanceof EsignAttempt) {
            return null;
        }

        $session = $this->sessions->get($sessionId, (int) $user->getKey());

        if ($session === null
            || (int) $attempt->actor_user_id !== (int) $user->getKey()
            || (int) $attempt->document_signing_step_id !== $session->stepId
            || ! hash_equals((string) $attempt->preview_artifact_sha256, Str::lower($previewSha256))) {
            throw new SigningSessionConflictException('esign.idempotency_payload_mismatch');
        }

        $visibleProperty = $attempt->signatureProperties()
            ->where('display_mode', 'visible')
            ->orderBy('property_index')
            ->first();

        if ($visibleProperty === null) {
            if ($preparedRevision !== null
                || (int) $attempt->source_artifact_id !== $session->sourceArtifactId) {
                throw new SigningSessionConflictException('esign.idempotency_payload_mismatch');
            }

            return $attempt;
        }

        $properties = $visibleProperty->safe_provider_properties;
        $sourceArtifact = $attempt->sourceArtifact()->first();

        if (! is_string($preparedRevision)
            || ! is_array($properties)
            || ! hash_equals((string) ($properties['prepared_revision'] ?? ''), Str::lower($preparedRevision))
            || ! $sourceArtifact instanceof DocumentArtifact
            || (int) $sourceArtifact->parent_artifact_id !== $session->sourceArtifactId) {
            throw new SigningSessionConflictException('esign.idempotency_payload_mismatch');
        }

        return $attempt;
    }

    private function handleVisible(
        User $user,
        SigningSessionContextData $context,
        string $idempotencyKey,
        string $previewSha256,
        #[SensitiveParameter] string $passphrase,
        ?string $preparedRevision,
    ): EsignAttempt {
        if ($this->config->get('esign.processing.multi_operation_enabled') !== true
            || ! PerformEsignAttempt::SUPPORTS_VISIBLE_MULTI_OPERATION) {
            throw new SigningSessionConflictException('esign.visible_worker_not_ready');
        }

        if (! is_string($preparedRevision) || ! Str::isUuid($preparedRevision)) {
            throw new SigningSessionConflictException('esign.prepared_revision_required');
        }

        $rendition = $this->renditions->get(
            $context->session->sessionId,
            Str::lower($preparedRevision),
            (int) $user->getKey(),
        );

        if ($rendition === null) {
            throw new SigningSessionConflictException('esign.prepared_rendition_not_found');
        }

        if (! hash_equals($rendition->sha256, Str::lower($previewSha256))) {
            throw new SigningSessionConflictException('esign.preview_hash_mismatch');
        }

        $hasPreviousAttempt = $context->step->attempts()->exists();
        ($hasPreviousAttempt
            ? $this->authorization->retrySign($user, $context->step)
            : $this->authorization->signStep($user, $context->step)
        )->authorize();

        $inspection = $this->artifactIntegrity->assertReadablePdf($context->artifact);

        if (! hash_equals($context->session->sourceArtifactSha256, $inspection->sha256)) {
            throw new SigningSessionConflictException('esign.source_artifact_changed');
        }

        $secretReference = $this->secrets->put($passphrase, (int) $user->getKey());

        try {
            return $this->visibleAttempts->create(
                context: $context,
                rendition: $rendition,
                data: new CreateVisibleEsignAttemptData(
                    idempotencyKey: Str::lower($idempotencyKey),
                    requestCorrelationId: (string) Str::uuid(),
                    actorContextSnapshot: $this->currentUserContext->snapshot($this->request),
                ),
                withinTransaction: function (
                    EsignAttempt $attempt,
                    DocumentArtifact $artifact,
                    string $md5,
                ) use ($context, $secretReference): void {
                    $this->legacyLedger->writeBefore(
                        attempt: $attempt,
                        artifact: $artifact,
                        maskedNik: $context->session->maskedNik,
                        md5: $md5,
                    );

                    PerformEsignAttempt::dispatch(
                        (int) $attempt->getKey(),
                        $secretReference,
                    )->afterCommit();
                },
            );
        } catch (\Throwable $exception) {
            $this->secrets->forget($secretReference);

            throw $exception;
        }
    }

    /** @param array<string, bool|int|string> $session */
    private function requestFingerprint(array $session): string
    {
        return hash('sha256', json_encode([
            'document_id' => $session['document_id'],
            'workflow_id' => $session['workflow_id'],
            'step_id' => $session['step_id'],
            'source_artifact_id' => $session['source_artifact_id'],
            'source_artifact_sha256' => $session['source_artifact_sha256'],
            'signer_user_id' => $session['signer_user_id'],
            'signer_user_position_id' => $session['signer_user_position_id'],
            'mode' => 'invisible',
        ], JSON_THROW_ON_ERROR));
    }
}
