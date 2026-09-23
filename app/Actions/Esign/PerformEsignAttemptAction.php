<?php

namespace App\Actions\Esign;

use App\Contracts\Esign\EsignGateway;
use App\Data\Esign\EsignTransitionContext;
use App\Data\Esign\SignRequestData;
use App\Data\Esign\StagedDocumentArtifact;
use App\Data\Esign\VerifyPdfData;
use App\Enums\Esign\EsignAttemptStatus;
use App\Enums\Esign\EsignErrorCode;
use App\Enums\Esign\EsignProviderOperation;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Exceptions\Esign\EsignOperationException;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\EsignAttempt;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Esign\DocumentArtifactIntegrityService;
use App\Services\Esign\EphemeralSigningSecretStore;
use App\Services\Esign\Persistence\DocumentArtifactPersistenceService;
use App\Services\Esign\Persistence\DocumentArtifactSignaturePersistenceService;
use App\Services\Esign\Persistence\EsignAttemptPersistenceService;
use App\Services\Esign\Persistence\EsignProviderResponsePersistenceService;
use App\Services\Esign\Persistence\LegacyEsignLedgerWriter;
use Illuminate\Support\Facades\Log;
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
    ) {}

    public function handle(int $attemptId, string $secretReference): void
    {
        $attempt = EsignAttempt::query()
            ->with(['resultArtifact', 'signer', 'signerPosition', 'sourceArtifact'])
            ->findOrFail($attemptId);

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
                $target = $freshAttempt->status === EsignAttemptStatus::Signing
                    ? EsignAttemptStatus::Unknown
                    : EsignAttemptStatus::Failed;
                $freshAttempt = $this->attempts->transition(
                    $freshAttempt,
                    $target,
                    $this->failureContext($freshAttempt, 'esign.local_processing_failed', false),
                );
                $this->writeLegacyFailure(
                    $freshAttempt,
                    $maskedNik,
                    'Proses internal TTE gagal dengan aman.',
                );
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
            'exception_class' => $exception::class,
        ]);

        return $freshAttempt instanceof EsignAttempt;
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

    private function context(EsignAttempt $attempt, ?int $providerResponseId = null): EsignTransitionContext
    {
        return new EsignTransitionContext(
            actorUserId: (int) $attempt->actor_user_id,
            actorUserPositionId: (int) $attempt->actor_user_position_id,
            actorIsActing: false,
            correlationId: $attempt->request_correlation_id,
            providerResponseId: $providerResponseId,
        );
    }

    private function failureContext(
        EsignAttempt $attempt,
        string $errorCode,
        bool $retryable,
        ?int $providerResponseId = null,
    ): EsignTransitionContext {
        return new EsignTransitionContext(
            actorUserId: (int) $attempt->actor_user_id,
            actorUserPositionId: (int) $attempt->actor_user_position_id,
            actorIsActing: false,
            correlationId: $attempt->request_correlation_id,
            providerResponseId: $providerResponseId,
            applicationErrorCode: $errorCode,
            retryable: $retryable,
        );
    }
}
