<?php

namespace App\Actions\Esign;

use App\Contracts\Esign\EsignGateway;
use App\Data\Esign\EsignTransitionContext;
use App\Data\Esign\SignaturePropertyData;
use App\Data\Esign\SignRequestData;
use App\Data\Esign\StagedDocumentArtifact;
use App\Data\Esign\VerifyPdfData;
use App\Enums\Esign\EsignAttemptStatus;
use App\Enums\Esign\EsignErrorCode;
use App\Enums\Esign\EsignProviderOperation;
use App\Enums\Esign\EsignSignatureOperationStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Exceptions\Esign\EsignOperationException;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignAttemptSignatureProperty;
use App\Models\Esign\EsignSignatureOperation;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Esign\DocumentArtifactIntegrityService;
use App\Services\Esign\EphemeralSigningSecretStore;
use App\Services\Esign\Persistence\DocumentArtifactPersistenceService;
use App\Services\Esign\Persistence\DocumentArtifactSignaturePersistenceService;
use App\Services\Esign\Persistence\EsignAttemptPersistenceService;
use App\Services\Esign\Persistence\EsignProviderResponsePersistenceService;
use App\Services\Esign\Persistence\LegacyEsignLedgerWriter;
use App\Services\Esign\Persistence\SignatureVisualPersistenceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class PerformEsignAttemptAction
{
    public function __construct(
        private EsignGateway $gateway,
        private EphemeralSigningSecretStore $secrets,
        private DocumentArtifactIntegrityService $artifactIntegrity,
        private DocumentArtifactPersistenceService $artifacts,
        private EsignAttemptPersistenceService $attempts,
        private EsignProviderResponsePersistenceService $providerResponses,
        private DocumentArtifactSignaturePersistenceService $signatures,
        private LegacyEsignLedgerWriter $legacyLedger,
        private SignatureVisualPersistenceService $visuals,
    ) {}

    public function handle(int $attemptId, string $secretReference): void
    {
        $attempt = EsignAttempt::query()
            ->with(['resultArtifact', 'signer', 'signerPosition', 'sourceArtifact'])
            ->findOrFail($attemptId);

        if ($attempt->signatureOperations()->exists()) {
            $this->handleVisible($attempt, $secretReference);

            return;
        }

        if ($attempt->status === EsignAttemptStatus::Succeeded) {
            $this->secrets->forget($secretReference);
            $this->replaySucceededCompatibilityProjection($attempt);

            return;
        }

        if ($attempt->status !== EsignAttemptStatus::Prepared) {
            $this->secrets->forget($secretReference);

            return;
        }

        $maskedNik = '****************';

        try {
            $signer = $attempt->signer;
            $signerPosition = $attempt->signerPosition;
            $sourceArtifact = $attempt->sourceArtifact;
            $nik = $this->signerNik($attempt, $signer, $signerPosition);
            $maskedNik = str_repeat('*', 12).substr($nik, -4);
            $context = $this->context($attempt);
            $secret = $this->secrets->take($secretReference, (int) $attempt->actor_user_id);
        } catch (Throwable $exception) {
            $this->secrets->forget($secretReference);

            if (! $this->handleLocalFailure($attempt, $maskedNik, $exception)) {
                throw $exception;
            }

            return;
        }

        if ($secret === null) {
            $failedAttempt = $this->attempts->transition(
                $attempt,
                EsignAttemptStatus::Failed,
                $this->failureContext($attempt, 'esign.signing_secret_expired', true),
            );
            $this->writeLegacyFailure($failedAttempt, $maskedNik, 'Secret TTE telah kedaluwarsa.');

            return;
        }

        $stagedArtifact = null;

        try {
            $pdfContents = $this->artifactIntegrity->readVerifiedPdfContents($sourceArtifact);
            $attempt = $this->attempts->transition($attempt, EsignAttemptStatus::Signing, $context);

            try {
                $signResult = $this->gateway->sign(new SignRequestData(
                    nik: $nik,
                    passphrase: $secret->passphrase(),
                    pdfContents: $pdfContents,
                    correlationId: $attempt->request_correlation_id,
                ));
            } catch (EsignOperationException $exception) {
                $this->handleProviderFailure(
                    attempt: $attempt,
                    operation: EsignProviderOperation::Sign,
                    exception: $exception,
                    maskedNik: $maskedNik,
                );

                return;
            }

            unset($pdfContents);
            $signResponse = $this->providerResponses->recordSignSuccess($attempt, $signResult);
            $attempt = $this->attempts->transition(
                $attempt,
                EsignAttemptStatus::Validating,
                $this->context($attempt, $signResponse->getKey()),
            );
            $signedPdfContents = $signResult->signedPdfContents();
            $stagedArtifact = $this->artifacts->stagePdfContents($signedPdfContents);

            try {
                $verification = $this->gateway->verify(new VerifyPdfData(
                    pdfContents: $signedPdfContents,
                    correlationId: $attempt->request_correlation_id,
                ));
            } catch (EsignOperationException $exception) {
                $outputArtifact = $this->finalizeOutput(
                    stagedArtifact: $stagedArtifact,
                    attempt: $attempt,
                    verificationPassed: false,
                    context: $context,
                    metadata: ['verification_error_code' => $exception->errorCode->value],
                );
                $stagedArtifact = null;
                $providerResponse = $this->providerResponses->recordFailure(
                    $attempt,
                    EsignProviderOperation::Verify,
                    $exception,
                );
                $failedAttempt = $this->attempts->transition(
                    $attempt,
                    EsignAttemptStatus::Failed,
                    $this->failureContext(
                        $attempt,
                        'esign.result_verification_failed',
                        false,
                        $providerResponse->getKey(),
                    ),
                );
                $this->legacyLedger->writeAfter(
                    attempt: $failedAttempt,
                    artifact: $outputArtifact,
                    maskedNik: $maskedNik,
                    md5: md5($signedPdfContents),
                    succeeded: false,
                    safeResponse: 'Hasil TTE tersimpan tetapi verifikasi BSrE gagal.',
                );

                return;
            }

            $outputArtifact = $this->finalizeOutput(
                stagedArtifact: $stagedArtifact,
                attempt: $attempt,
                verificationPassed: $verification->isValid(),
                context: $context,
                metadata: [
                    'verification_conclusion' => $verification->conclusion,
                    'signature_count' => $verification->signatureCount,
                ],
            );
            $stagedArtifact = null;
            $verifyResponse = $this->providerResponses->recordVerifyResult(
                $attempt,
                $verification,
                (int) $outputArtifact->getKey(),
            );

            if (! $verification->isValid()) {
                $failedAttempt = $this->attempts->transition(
                    $attempt,
                    EsignAttemptStatus::Failed,
                    $this->failureContext(
                        $attempt,
                        EsignErrorCode::ResultInvalid->value,
                        false,
                        $verifyResponse->getKey(),
                    ),
                );
                $this->legacyLedger->writeAfter(
                    attempt: $failedAttempt,
                    artifact: $outputArtifact,
                    maskedNik: $maskedNik,
                    md5: md5($signedPdfContents),
                    succeeded: false,
                    safeResponse: EsignErrorCode::ResultInvalid->message(),
                );

                return;
            }

            $this->signatures->persist($outputArtifact, $attempt, $verifyResponse, $verification);
            $succeededAttempt = $this->attempts->transition(
                $attempt,
                EsignAttemptStatus::Succeeded,
                $this->context($attempt, $verifyResponse->getKey()),
            );
            $this->legacyLedger->writeAfter(
                attempt: $succeededAttempt,
                artifact: $outputArtifact,
                maskedNik: $maskedNik,
                md5: md5($signedPdfContents),
                succeeded: true,
                safeResponse: 'Dokumen berhasil ditandatangani dan diverifikasi.',
            );
            $this->legacyLedger->writeSuccessfulDocumentHistory(
                attempt: $succeededAttempt,
                artifact: $outputArtifact,
                md5: md5($signedPdfContents),
            );
        } catch (Throwable $exception) {
            if ($stagedArtifact instanceof StagedDocumentArtifact) {
                $this->discardStagedArtifact($stagedArtifact, $attempt);
            }

            if (! $this->handleLocalFailure($attempt, $maskedNik, $exception)) {
                throw $exception;
            }
        } finally {
            $this->secrets->forget($secretReference);
        }
    }

    private function handleVisible(EsignAttempt $attempt, string $secretReference): void
    {
        if ($attempt->status === EsignAttemptStatus::Succeeded) {
            $this->secrets->forget($secretReference);
            $this->replaySucceededCompatibilityProjection($attempt);

            return;
        }

        if (! in_array($attempt->status, [
            EsignAttemptStatus::Prepared,
            EsignAttemptStatus::Signing,
            EsignAttemptStatus::Validating,
        ], true)) {
            $this->secrets->forget($secretReference);

            return;
        }

        $maskedNik = '****************';
        $stagedArtifact = null;

        try {
            $attempt->loadMissing(['signer', 'signerPosition', 'sourceArtifact']);
            $nik = $this->signerNik($attempt, $attempt->signer, $attempt->signerPosition);
            $maskedNik = str_repeat('*', 12).substr($nik, -4);
            $context = $this->context($attempt);
            $hasIncompleteOperation = $attempt->signatureOperations()
                ->where('status', '!=', EsignSignatureOperationStatus::Completed->value)
                ->exists();
            $secret = $hasIncompleteOperation
                ? $this->secrets->get($secretReference, (int) $attempt->actor_user_id)
                : null;

            if ($hasIncompleteOperation && $secret === null) {
                $target = $attempt->status === EsignAttemptStatus::Signing
                    && (int) $attempt->completed_signature_count > 0
                    ? EsignAttemptStatus::PartiallySigned
                    : EsignAttemptStatus::Failed;
                $failedAttempt = $this->attempts->transition(
                    $attempt,
                    $target,
                    $this->failureContext(
                        $attempt,
                        'esign.signing_secret_expired',
                        $target === EsignAttemptStatus::PartiallySigned,
                    ),
                );

                if ($target === EsignAttemptStatus::Failed) {
                    $this->writeLegacyFailure($failedAttempt, $maskedNik, 'Secret TTE telah kedaluwarsa.');
                }

                return;
            }

            if ($attempt->status === EsignAttemptStatus::Prepared) {
                $attempt = $this->attempts->transition($attempt, EsignAttemptStatus::Signing, $context);
            }

            if ($attempt->status === EsignAttemptStatus::Signing) {
                /** @var EsignSignatureOperation $operation */
                foreach ($attempt->signatureOperations()
                    ->with(['signatureProperty', 'inputArtifact'])
                    ->orderBy('operation_index')
                    ->get() as $operation) {
                    if ($operation->status === EsignSignatureOperationStatus::Completed) {
                        continue;
                    }

                    if ($operation->status === EsignSignatureOperationStatus::OutputReceived) {
                        $this->attempts->completeSignatureOperation($attempt, $operation, $context);

                        continue;
                    }

                    if ($operation->status === EsignSignatureOperationStatus::Signing) {
                        if ($this->recoverVisibleOperationCheckpoint($attempt, $operation, $context)) {
                            continue;
                        }

                        $unknownContext = $this->failureContext(
                            $attempt,
                            'esign.signature_operation_outcome_unknown',
                            false,
                            signatureOperationId: (int) $operation->getKey(),
                        );
                        $this->attempts->closeSignatureOperation(
                            $attempt,
                            $operation,
                            EsignSignatureOperationStatus::Unknown,
                            $unknownContext,
                        );
                        $this->attempts->transition($attempt, EsignAttemptStatus::Unknown, $unknownContext);

                        return;
                    }

                    if (! in_array($operation->status, [
                        EsignSignatureOperationStatus::Pending,
                        EsignSignatureOperationStatus::Failed,
                    ], true) || $secret === null) {
                        throw new EsignInvariantViolationException('visible_signature_operation_not_executable');
                    }

                    $operation = $this->attempts->startSignatureOperation(
                        $attempt,
                        $operation,
                        (string) Str::uuid(),
                        $context,
                    );
                    $operation->loadMissing(['signatureProperty', 'inputArtifact']);
                    $property = $operation->signatureProperty;
                    $inputArtifact = $operation->inputArtifact;

                    if (! $property instanceof EsignAttemptSignatureProperty
                        || ! $inputArtifact instanceof DocumentArtifact) {
                        throw new EsignInvariantViolationException('visible_signature_operation_payload_missing');
                    }

                    $pdfContents = $this->artifactIntegrity->readVerifiedPdfContents($inputArtifact);
                    $visualContents = $this->visuals->readVerifiedContents($property);

                    try {
                        $signResult = $this->gateway->sign(new SignRequestData(
                            nik: $nik,
                            passphrase: $secret->passphrase(),
                            pdfContents: $pdfContents,
                            correlationId: $operation->provider_correlation_id,
                            signatureProperties: [$this->visibleSignatureProperty($property, $visualContents)],
                        ));
                    } catch (EsignOperationException $exception) {
                        unset($pdfContents, $visualContents);
                        $this->handleVisibleProviderFailure(
                            attempt: $attempt,
                            operation: $operation,
                            exception: $exception,
                            maskedNik: $maskedNik,
                        );

                        return;
                    }

                    unset($pdfContents, $visualContents);
                    $signedPdfContents = $signResult->signedPdfContents();
                    $stagedArtifact = $this->artifacts->stagePdfContents($signedPdfContents);
                    $checkpointArtifact = $this->artifacts->finalizeSignatureOperationCheckpoint(
                        stagedArtifact: $stagedArtifact,
                        attempt: $attempt,
                        operation: $operation,
                        createdByUserId: (int) $attempt->actor_user_id,
                        metadata: [
                            'provider_correlation_id' => $operation->provider_correlation_id,
                            'response_sha256' => $signResult->pdfSha256,
                        ],
                    );
                    $stagedArtifact = null;
                    $providerResponse = $this->providerResponses->recordSignSuccess(
                        attempt: $attempt,
                        result: $signResult,
                        signatureOperation: $operation,
                        inputArtifactId: (int) $inputArtifact->getKey(),
                        outputArtifactId: (int) $checkpointArtifact->getKey(),
                    );
                    unset($signedPdfContents, $signResult);
                    $operationContext = $this->context(
                        $attempt,
                        (int) $providerResponse->getKey(),
                        (int) $operation->getKey(),
                    );
                    $operation = $this->attempts->markSignatureOperationOutputReceived(
                        $attempt,
                        $operation,
                        $checkpointArtifact,
                        $operationContext,
                    );
                    $this->attempts->completeSignatureOperation($attempt, $operation, $operationContext);
                }

                $attempt = $this->attempts->transition(
                    $attempt->fresh(),
                    EsignAttemptStatus::Validating,
                    $context,
                );
            }

            $this->verifyVisibleAttempt($attempt, $maskedNik);
        } catch (Throwable $exception) {
            if ($stagedArtifact instanceof StagedDocumentArtifact) {
                $this->discardStagedArtifact($stagedArtifact, $attempt);
            }

            $this->closeVisibleInFlightAsUnknown($attempt);

            if (! $this->handleLocalFailure($attempt, $maskedNik, $exception)) {
                throw $exception;
            }
        } finally {
            $this->secrets->forget($secretReference);
        }
    }

    private function closeVisibleInFlightAsUnknown(EsignAttempt $attempt): void
    {
        try {
            $freshAttempt = EsignAttempt::query()->find($attempt->getKey());

            if (! $freshAttempt instanceof EsignAttempt
                || $freshAttempt->status !== EsignAttemptStatus::Signing) {
                return;
            }

            /** @var EsignSignatureOperation|null $operation */
            $operation = $freshAttempt->signatureOperations()
                ->where('status', EsignSignatureOperationStatus::Signing->value)
                ->orderBy('operation_index')
                ->first();

            if (! $operation instanceof EsignSignatureOperation) {
                return;
            }

            $this->attempts->closeSignatureOperation(
                $freshAttempt,
                $operation,
                EsignSignatureOperationStatus::Unknown,
                $this->failureContext(
                    $freshAttempt,
                    'esign.local_operation_outcome_unknown',
                    false,
                    signatureOperationId: (int) $operation->getKey(),
                ),
            );
        } catch (Throwable $exception) {
            Log::channel('module_esign')->critical('Gagal menutup operasi TTE visible yang sedang berjalan.', [
                'attempt_id' => $attempt->getKey(),
                'exception_class' => $exception::class,
            ]);
        }
    }

    private function recoverVisibleOperationCheckpoint(
        EsignAttempt $attempt,
        EsignSignatureOperation $operation,
        EsignTransitionContext $context,
    ): bool {
        /** @var DocumentArtifact|null $checkpoint */
        $checkpoint = DocumentArtifact::query()
            ->where('source_reference_type', 'esign_signature_operation')
            ->where('source_reference_id', $operation->public_id)
            ->first();

        if (! $checkpoint instanceof DocumentArtifact) {
            return false;
        }

        $this->artifactIntegrity->assertReadablePdf($checkpoint);
        $operation = $this->attempts->markSignatureOperationOutputReceived(
            $attempt,
            $operation,
            $checkpoint,
            $context,
        );
        $this->attempts->completeSignatureOperation($attempt, $operation, $context);

        return true;
    }

    private function verifyVisibleAttempt(EsignAttempt $attempt, string $maskedNik): void
    {
        $attempt = $attempt->fresh(['sourceArtifact']);

        if (! $attempt instanceof EsignAttempt || $attempt->status !== EsignAttemptStatus::Validating) {
            throw new EsignInvariantViolationException('visible_attempt_not_ready_for_verification');
        }

        /** @var EsignSignatureOperation|null $lastOperation */
        $lastOperation = $attempt->signatureOperations()
            ->where('status', EsignSignatureOperationStatus::Completed->value)
            ->whereNotNull('output_artifact_id')
            ->reorder()
            ->orderByDesc('operation_index')
            ->first();

        if (! $lastOperation instanceof EsignSignatureOperation
            || (int) $attempt->completed_signature_count !== (int) $attempt->planned_signature_count) {
            throw new EsignInvariantViolationException('visible_attempt_signature_progress_incomplete');
        }

        /** @var DocumentArtifact $checkpointArtifact */
        $checkpointArtifact = DocumentArtifact::query()->findOrFail($lastOperation->output_artifact_id);
        $signedPdfContents = $this->artifactIntegrity->readVerifiedPdfContents($checkpointArtifact);
        $context = $this->context($attempt);

        try {
            $verification = $this->gateway->verify(new VerifyPdfData(
                pdfContents: $signedPdfContents,
                correlationId: $attempt->request_correlation_id,
            ));
        } catch (EsignOperationException $exception) {
            unset($signedPdfContents);
            $providerResponse = $this->providerResponses->recordFailure(
                attempt: $attempt,
                operation: EsignProviderOperation::Verify,
                exception: $exception,
                inputArtifactId: (int) $checkpointArtifact->getKey(),
            );
            $this->attempts->transition(
                $attempt,
                EsignAttemptStatus::Unknown,
                $this->failureContext(
                    $attempt,
                    'esign.result_verification_outcome_unknown',
                    false,
                    (int) $providerResponse->getKey(),
                ),
            );

            return;
        }

        $sourceMetadata = $attempt->sourceArtifact?->metadata;
        $baselineSignatureCount = is_array($sourceMetadata)
            ? (int) ($sourceMetadata['baseline_verified_signature_count'] ?? 0)
            : 0;
        $expectedSignatureCount = $baselineSignatureCount + (int) $attempt->planned_signature_count;
        $accepted = $verification->isValid()
            && $verification->signatureCount === $expectedSignatureCount;
        $stagedArtifact = $this->artifacts->stagePdfContents($signedPdfContents);

        try {
            $outputArtifact = $this->finalizeOutput(
                stagedArtifact: $stagedArtifact,
                attempt: $attempt,
                verificationPassed: $accepted,
                context: $context,
                metadata: [
                    'verification_conclusion' => $verification->conclusion,
                    'signature_count' => $verification->signatureCount,
                    'expected_signature_count' => $expectedSignatureCount,
                    'baseline_signature_count' => $baselineSignatureCount,
                ],
            );
        } catch (Throwable $exception) {
            $this->discardStagedArtifact($stagedArtifact, $attempt);

            throw $exception;
        }

        $verifyResponse = $this->providerResponses->recordVerifyResult(
            attempt: $attempt,
            result: $verification,
            outputArtifactId: (int) $outputArtifact->getKey(),
            inputArtifactId: (int) $checkpointArtifact->getKey(),
            expectedSignatureCount: $expectedSignatureCount,
            applicationAccepted: $accepted,
        );
        $md5 = md5($signedPdfContents);
        unset($signedPdfContents);

        if (! $accepted) {
            $failedAttempt = $this->attempts->transition(
                $attempt,
                EsignAttemptStatus::Failed,
                $this->failureContext(
                    $attempt,
                    EsignErrorCode::ResultInvalid->value,
                    false,
                    (int) $verifyResponse->getKey(),
                ),
            );
            $this->legacyLedger->writeAfter(
                attempt: $failedAttempt,
                artifact: $outputArtifact,
                maskedNik: $maskedNik,
                md5: $md5,
                succeeded: false,
                safeResponse: EsignErrorCode::ResultInvalid->message(),
            );

            return;
        }

        $this->signatures->persist($outputArtifact, $attempt, $verifyResponse, $verification);
        $succeededAttempt = $this->attempts->transition(
            $attempt,
            EsignAttemptStatus::Succeeded,
            $this->context($attempt, (int) $verifyResponse->getKey()),
        );
        $this->legacyLedger->writeAfter(
            attempt: $succeededAttempt,
            artifact: $outputArtifact,
            maskedNik: $maskedNik,
            md5: $md5,
            succeeded: true,
            safeResponse: 'Dokumen berhasil ditandatangani dan diverifikasi.',
        );
        $this->legacyLedger->writeSuccessfulDocumentHistory(
            attempt: $succeededAttempt,
            artifact: $outputArtifact,
            md5: $md5,
        );
    }

    private function visibleSignatureProperty(
        EsignAttemptSignatureProperty $property,
        string $visualContents,
    ): SignaturePropertyData {
        if ($property->page_number === null
            || $property->origin_x === null
            || $property->origin_y === null
            || $property->width === null
            || $property->height === null) {
            throw new EsignInvariantViolationException('visible_signature_property_geometry_missing');
        }

        return SignaturePropertyData::visible(
            imageContents: $visualContents,
            pageNumber: (int) $property->page_number,
            originX: (float) $property->origin_x,
            originY: (float) $property->origin_y,
            width: (float) $property->width,
            height: (float) $property->height,
            location: $property->location,
            reason: $property->reason,
            contactInfo: $property->contact_info,
        );
    }

    private function handleVisibleProviderFailure(
        EsignAttempt $attempt,
        EsignSignatureOperation $operation,
        EsignOperationException $exception,
        string $maskedNik,
    ): void {
        $providerResponse = $this->providerResponses->recordFailure(
            attempt: $attempt,
            operation: EsignProviderOperation::Sign,
            exception: $exception,
            signatureOperation: $operation,
            inputArtifactId: (int) $operation->input_artifact_id,
        );
        $operationTarget = $exception->outcomeIsUnknown()
            ? EsignSignatureOperationStatus::Unknown
            : EsignSignatureOperationStatus::Failed;
        $attemptTarget = $operationTarget === EsignSignatureOperationStatus::Unknown
            ? EsignAttemptStatus::Unknown
            : ($exception->retryable ? EsignAttemptStatus::PartiallySigned : EsignAttemptStatus::Failed);
        $failureContext = $this->failureContext(
            $attempt,
            $exception->errorCode->value,
            $attemptTarget === EsignAttemptStatus::PartiallySigned,
            (int) $providerResponse->getKey(),
            (int) $operation->getKey(),
        );
        $this->attempts->closeSignatureOperation(
            $attempt,
            $operation,
            $operationTarget,
            $failureContext,
        );
        $closedAttempt = $this->attempts->transition($attempt, $attemptTarget, $failureContext);

        if ($attemptTarget === EsignAttemptStatus::Failed) {
            $this->writeLegacyFailure($closedAttempt, $maskedNik, $exception->errorCode->message());
        }
    }

    private function signerNik(
        EsignAttempt $attempt,
        mixed $signer,
        mixed $signerPosition,
    ): string {
        if (! $signer instanceof User
            || ! $signerPosition instanceof UserPosition
            || ! $signer->isActive()
            || $signer->isLocked()
            || $signer->trashed()
            || ! $signerPosition->isAvailableForSelection()
            || (int) $attempt->actor_user_id !== (int) $attempt->signer_user_id
            || (int) $attempt->actor_user_position_id !== (int) $attempt->signer_user_position_id
            || (int) $signerPosition->user_id !== (int) $signer->getKey()
            || $attempt->is_acting) {
            throw new \RuntimeException('esign.worker_signer_context_invalid');
        }

        $nik = preg_replace('/\D+/', '', (string) $signer->nik);

        if (! is_string($nik) || preg_match('/\A\d{16}\z/', $nik) !== 1) {
            throw new \RuntimeException('esign.worker_signer_nik_invalid');
        }

        return $nik;
    }

    private function handleProviderFailure(
        EsignAttempt $attempt,
        EsignProviderOperation $operation,
        EsignOperationException $exception,
        string $maskedNik,
    ): void {
        $providerResponse = $this->providerResponses->recordFailure($attempt, $operation, $exception);
        $target = $exception->outcomeIsUnknown()
            ? EsignAttemptStatus::Unknown
            : EsignAttemptStatus::Failed;
        $failedAttempt = $this->attempts->transition(
            $attempt,
            $target,
            $this->failureContext(
                $attempt,
                $exception->errorCode->value,
                $target === EsignAttemptStatus::Failed && $exception->retryable,
                $providerResponse->getKey(),
            ),
        );
        $this->writeLegacyFailure($failedAttempt, $maskedNik, $exception->errorCode->message());
    }

    private function handleLocalFailure(
        EsignAttempt $attempt,
        string $maskedNik,
        Throwable $exception,
    ): bool {
        $freshAttempt = EsignAttempt::query()->find($attempt->getKey());

        if ($freshAttempt?->status === EsignAttemptStatus::Succeeded) {
            Log::channel('module_esign')->critical('Projection compatibility gagal setelah attempt TTE sukses.', [
                'attempt_id' => $attempt->getKey(),
                'exception_class' => $exception::class,
            ]);

            return false;
        }

        if ($freshAttempt instanceof EsignAttempt
            && in_array($freshAttempt->status, [
                EsignAttemptStatus::Prepared,
                EsignAttemptStatus::Signing,
                EsignAttemptStatus::Validating,
            ], true)) {
            try {
                $providerDispatchStarted = $this->providerDispatchStarted($freshAttempt);
                $failedBeforeProviderDispatch = $freshAttempt->status === EsignAttemptStatus::Signing
                    && ! $providerDispatchStarted;
                $target = $freshAttempt->status === EsignAttemptStatus::Signing
                    && $providerDispatchStarted
                    ? EsignAttemptStatus::Unknown
                    : EsignAttemptStatus::Failed;
                $freshAttempt = $this->attempts->transition(
                    $freshAttempt,
                    $target,
                    $this->failureContext(
                        $freshAttempt,
                        $failedBeforeProviderDispatch
                            ? 'esign.local_pre_provider_processing_failed'
                            : 'esign.local_processing_failed',
                        $failedBeforeProviderDispatch,
                    ),
                );
                if ($target === EsignAttemptStatus::Failed) {
                    $this->writeLegacyFailure(
                        $freshAttempt,
                        $maskedNik,
                        'Proses internal TTE gagal dengan aman.',
                    );
                }
            } catch (Throwable $transitionException) {
                Log::channel('module_esign')->critical('Gagal menutup attempt TTE setelah error lokal.', [
                    'attempt_id' => $attempt->getKey(),
                    'exception_class' => $transitionException::class,
                ]);

                return false;
            }
        }

        Log::channel('module_esign')->error('Eksekusi attempt TTE gagal di proses internal.', [
            'attempt_id' => $attempt->getKey(),
            'provider_dispatch_started' => $freshAttempt instanceof EsignAttempt
                ? $this->providerDispatchStarted($freshAttempt)
                : null,
            ...$this->safeExceptionContext($exception),
        ]);

        return $freshAttempt instanceof EsignAttempt;
    }

    private function providerDispatchStarted(EsignAttempt $attempt): bool
    {
        if (! $attempt->signatureOperations()->exists()) {
            return $attempt->status !== EsignAttemptStatus::Prepared;
        }

        return $attempt->signatureOperations()
            ->whereNotNull('request_sent_at')
            ->exists();
    }

    /** @return array{exception_class: string, exception_message_sha256: string, exception_file: string, exception_line: int} */
    private function safeExceptionContext(Throwable $exception): array
    {
        $normalizedBasePath = str_replace('\\', '/', base_path()).'/';
        $normalizedFile = str_replace('\\', '/', $exception->getFile());

        return [
            'exception_class' => $exception::class,
            'exception_message_sha256' => hash('sha256', $exception->getMessage()),
            'exception_file' => Str::after($normalizedFile, $normalizedBasePath),
            'exception_line' => $exception->getLine(),
        ];
    }

    private function replaySucceededCompatibilityProjection(EsignAttempt $attempt): void
    {
        $resultArtifact = $attempt->resultArtifact;
        $signer = $attempt->signer;
        $signerPosition = $attempt->signerPosition;

        if (! $resultArtifact instanceof DocumentArtifact) {
            throw new EsignInvariantViolationException('succeeded_attempt_result_artifact_missing');
        }

        $nik = $this->signerNik($attempt, $signer, $signerPosition);
        $maskedNik = str_repeat('*', 12).substr($nik, -4);
        $signedPdfContents = $this->artifactIntegrity->readVerifiedPdfContents($resultArtifact);
        $md5 = md5($signedPdfContents);

        unset($signedPdfContents);

        $this->legacyLedger->writeAfter(
            attempt: $attempt,
            artifact: $resultArtifact,
            maskedNik: $maskedNik,
            md5: $md5,
            succeeded: true,
            safeResponse: 'Dokumen berhasil ditandatangani dan diverifikasi.',
        );
        $this->legacyLedger->writeSuccessfulDocumentHistory(
            attempt: $attempt,
            artifact: $resultArtifact,
            md5: $md5,
        );
    }

    private function finalizeOutput(
        StagedDocumentArtifact $stagedArtifact,
        EsignAttempt $attempt,
        bool $verificationPassed,
        EsignTransitionContext $context,
        array $metadata,
    ): DocumentArtifact {
        return $this->artifacts->finalizeAttemptOutputArtifact(
            stagedArtifact: $stagedArtifact,
            attempt: $attempt,
            verificationPassed: $verificationPassed,
            context: $context,
            metadata: $metadata,
        );
    }

    private function discardStagedArtifact(
        StagedDocumentArtifact $stagedArtifact,
        EsignAttempt $attempt,
    ): void {
        try {
            $this->artifacts->discardStagedArtifact($stagedArtifact);
        } catch (Throwable $exception) {
            Log::channel('module_esign')->warning('Staging artifact TTE tidak dapat dibersihkan.', [
                'attempt_id' => $attempt->getKey(),
                'artifact_public_id' => $stagedArtifact->publicId,
                'exception_class' => $exception::class,
            ]);
        }
    }

    private function writeLegacyFailure(
        EsignAttempt $attempt,
        string $maskedNik,
        string $safeResponse,
    ): void {
        $this->legacyLedger->writeAfter(
            attempt: $attempt,
            artifact: null,
            maskedNik: $maskedNik,
            md5: null,
            succeeded: false,
            safeResponse: $safeResponse,
        );
    }

    private function context(
        EsignAttempt $attempt,
        ?int $providerResponseId = null,
        ?int $signatureOperationId = null,
    ): EsignTransitionContext {
        return new EsignTransitionContext(
            actorUserId: (int) $attempt->actor_user_id,
            actorUserPositionId: (int) $attempt->actor_user_position_id,
            actorIsActing: false,
            correlationId: $attempt->request_correlation_id,
            providerResponseId: $providerResponseId,
            signatureOperationId: $signatureOperationId,
        );
    }

    private function failureContext(
        EsignAttempt $attempt,
        string $errorCode,
        bool $retryable,
        ?int $providerResponseId = null,
        ?int $signatureOperationId = null,
    ): EsignTransitionContext {
        return new EsignTransitionContext(
            actorUserId: (int) $attempt->actor_user_id,
            actorUserPositionId: (int) $attempt->actor_user_position_id,
            actorIsActing: false,
            correlationId: $attempt->request_correlation_id,
            providerResponseId: $providerResponseId,
            applicationErrorCode: $errorCode,
            retryable: $retryable,
            signatureOperationId: $signatureOperationId,
        );
    }
}
