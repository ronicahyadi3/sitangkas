<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\CreateEsignAttemptData;
use App\Data\Esign\CreateVisibleEsignAttemptData;
use App\Data\Esign\PreparedSigningRenditionData;
use App\Data\Esign\SigningSessionContextData;
use App\Exceptions\Esign\SigningSessionConflictException;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\EsignAttempt;
use App\Services\Esign\EphemeralPreparedRenditionStore;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class VisibleEsignAttemptPersistenceService
{
    public function __construct(
        private EphemeralPreparedRenditionStore $renditions,
        private DocumentArtifactPersistenceService $artifacts,
        private SignatureVisualPersistenceService $visuals,
        private EsignAttemptPersistenceService $attempts,
    ) {}

    /**
     * @param  (Closure(EsignAttempt, DocumentArtifact, string): void)|null  $withinTransaction
     */
    public function create(
        SigningSessionContextData $context,
        PreparedSigningRenditionData $rendition,
        CreateVisibleEsignAttemptData $data,
        ?Closure $withinTransaction = null,
    ): EsignAttempt {
        $this->assertRenditionMatchesSession($context, $rendition);

        try {
            return $this->renditions->lock($rendition->sessionId)->block(
                5,
                function () use ($context, $rendition, $data, $withinTransaction): EsignAttempt {
                    $currentRendition = $this->renditions->get(
                        $rendition->sessionId,
                        $rendition->revision,
                        $rendition->actorUserId,
                    );

                    if (! $currentRendition instanceof PreparedSigningRenditionData
                        || ! hash_equals($rendition->sha256, $currentRendition->sha256)
                        || ! hash_equals($rendition->requestFingerprint, $currentRendition->requestFingerprint)) {
                        throw new SigningSessionConflictException('esign.prepared_rendition_changed');
                    }

                    $pdfContents = $this->renditions->readVerifiedPdfContents($currentRendition);
                    $qrContents = $this->renditions->readVerifiedQrContents($currentRendition);
                    $stagedArtifact = $this->artifacts->stagePdfContents(
                        $pdfContents,
                        $currentRendition->createdAt,
                    );
                    $visualAssets = [];

                    try {
                        $visualAssets = $this->visuals->materialize($currentRendition, $qrContents);
                        $attempt = DB::transaction(function () use (
                            $context,
                            $currentRendition,
                            $data,
                            $withinTransaction,
                            $stagedArtifact,
                            $visualAssets,
                            $pdfContents,
                        ): EsignAttempt {
                            $preparedArtifact = $this->artifacts->finalizePreparedSourceArtifact(
                                stagedArtifact: $stagedArtifact,
                                workflow: $context->workflow,
                                step: $context->step,
                                parentArtifact: $context->artifact,
                                originalName: $context->artifact->original_name,
                                createdByUserId: $context->session->actorUserId,
                                metadata: [
                                    'prepared_revision' => $currentRendition->revision,
                                    'prepared_request_fingerprint' => $currentRendition->requestFingerprint,
                                    'renderer_version' => $currentRendition->rendererVersion,
                                    'signature_count' => count($currentRendition->signatureOperations),
                                    'baseline_verified_signature_count' => $context->session->verifiedSignatureCount,
                                    'footer_applied' => $context->session->footerApplied
                                        || ($currentRendition->footer !== null
                                            && ($currentRendition->footer['placements'] ?? []) !== []),
                                    'source_artifact_id' => $currentRendition->sourceArtifactId,
                                    'source_artifact_sha256' => $currentRendition->sourceArtifactSha256,
                                ],
                            );
                            $requestFingerprint = $this->requestFingerprint($context, $currentRendition);
                            $attempt = $this->attempts->create(new CreateEsignAttemptData(
                                documentSigningStepId: (int) $context->step->getKey(),
                                sourceArtifactId: (int) $preparedArtifact->getKey(),
                                idempotencyKey: Str::lower($data->idempotencyKey),
                                requestCorrelationId: Str::lower($data->requestCorrelationId),
                                requestFingerprint: $requestFingerprint,
                                previewArtifactSha256: $currentRendition->sha256,
                                plannedSignatureCount: count($currentRendition->signatureOperations),
                                actorUserId: $context->session->actorUserId,
                                actorUserPositionId: $context->session->realUserPositionId,
                                signerUserId: $context->session->signerUserId,
                                signerUserPositionId: $context->session->signerUserPositionId,
                                isActing: false,
                                effectiveRoleCode: $context->session->effectiveRoleCode,
                                effectiveUnitKerjaId: $context->session->effectiveUnitKerjaId,
                                effectiveInstansiId: $context->session->effectiveInstansiId,
                                actorContextSnapshot: $data->actorContextSnapshot,
                                provider: $data->provider,
                            ));
                            $attempt = $this->attempts->persistVisiblePlan(
                                $attempt,
                                $currentRendition,
                                $visualAssets,
                            );

                            if ($withinTransaction instanceof Closure) {
                                $withinTransaction(
                                    $attempt,
                                    $preparedArtifact,
                                    md5($pdfContents),
                                );
                            }

                            return $attempt;
                        }, attempts: 3);
                    } catch (\Throwable $exception) {
                        try {
                            $this->artifacts->discardUnpersistedSourceArtifact($stagedArtifact);
                        } catch (\Throwable $cleanupException) {
                            Log::channel('module_esign')->warning('Cleanup prepared source artifact gagal.', [
                                'artifact_public_id' => $stagedArtifact->publicId,
                                'exception_class' => $cleanupException::class,
                            ]);
                        }

                        try {
                            $this->visuals->discardUnpersisted($visualAssets);
                        } catch (\Throwable $cleanupException) {
                            Log::channel('module_esign')->warning('Cleanup signature visual gagal.', [
                                'prepared_revision' => $currentRendition->revision,
                                'exception_class' => $cleanupException::class,
                            ]);
                        }

                        throw $exception;
                    } finally {
                        unset($pdfContents, $qrContents);
                    }

                    try {
                        $this->renditions->forget(
                            $currentRendition->sessionId,
                            $currentRendition->actorUserId,
                        );
                    } catch (\Throwable) {
                    }

                    return $attempt;
                },
            );
        } catch (LockTimeoutException) {
            throw new SigningSessionConflictException('esign.prepared_rendition_busy');
        }
    }

    private function assertRenditionMatchesSession(
        SigningSessionContextData $context,
        PreparedSigningRenditionData $rendition,
    ): void {
        if (! $context->session->placementRequired
            || ! hash_equals($context->session->sessionId, $rendition->sessionId)
            || $context->session->actorUserId !== $rendition->actorUserId
            || $context->session->sourceArtifactId !== $rendition->sourceArtifactId
            || ! hash_equals($context->session->sourceArtifactSha256, $rendition->sourceArtifactSha256)
            || $rendition->signatureOperations === []) {
            throw new SigningSessionConflictException('esign.prepared_rendition_context_mismatch');
        }
    }

    private function requestFingerprint(
        SigningSessionContextData $context,
        PreparedSigningRenditionData $rendition,
    ): string {
        return hash('sha256', json_encode([
            'document_id' => $context->session->documentId,
            'workflow_id' => $context->session->workflowId,
            'step_id' => $context->session->stepId,
            'source_artifact_id' => $context->session->sourceArtifactId,
            'source_artifact_sha256' => $context->session->sourceArtifactSha256,
            'signer_user_id' => $context->session->signerUserId,
            'signer_user_position_id' => $context->session->signerUserPositionId,
            'prepared_revision' => $rendition->revision,
            'prepared_sha256' => $rendition->sha256,
            'prepared_request_fingerprint' => $rendition->requestFingerprint,
            'signature_count' => count($rendition->signatureOperations),
            'mode' => 'visible',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
