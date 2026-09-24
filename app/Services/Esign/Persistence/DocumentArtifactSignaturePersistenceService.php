<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\CertificateDetailData;
use App\Data\Esign\SignatureInformationData;
use App\Data\Esign\VerificationResultData;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentArtifactSignature;
use App\Models\Esign\DocumentArtifactSignatureCertificate;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignProviderResponse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentArtifactSignaturePersistenceService
{
    public function persist(
        DocumentArtifact $artifact,
        EsignAttempt $attempt,
        EsignProviderResponse $providerResponse,
        VerificationResultData $verification,
    ): void {
        DB::transaction(function () use ($artifact, $attempt, $providerResponse, $verification): void {
            $hasVisibleOperations = $attempt->signatureOperations()->exists();
            $sourceArtifact = $hasVisibleOperations ? $attempt->sourceArtifact()->first() : null;
            $sourceMetadata = $sourceArtifact?->metadata;
            $baselineSignatureCount = is_array($sourceMetadata)
                ? (int) ($sourceMetadata['baseline_verified_signature_count'] ?? 0)
                : 0;

            foreach ($verification->signatures as $index => $signatureData) {
                $operationIndex = ($hasVisibleOperations
                    && $index >= $baselineSignatureCount
                    && $index < $baselineSignatureCount + (int) $attempt->planned_signature_count)
                        ? $index - $baselineSignatureCount
                        : null;
                $signature = $this->persistSignature(
                    artifact: $artifact,
                    attempt: $attempt,
                    providerResponse: $providerResponse,
                    verification: $verification,
                    data: $signatureData,
                    index: $index,
                    belongsToAttempt: $operationIndex !== null,
                    operationIndex: $operationIndex,
                );

                foreach ($signatureData->certificateDetails as $certificateIndex => $certificate) {
                    $this->persistCertificate($signature, $certificate, $certificateIndex);
                }
            }
        }, attempts: 3);
    }

    private function persistSignature(
        DocumentArtifact $artifact,
        EsignAttempt $attempt,
        EsignProviderResponse $providerResponse,
        VerificationResultData $verification,
        SignatureInformationData $data,
        int $index,
        bool $belongsToAttempt,
        ?int $operationIndex,
    ): DocumentArtifactSignature {
        $isLastSignature = $data->lastSignature
            ?? ($index === count($verification->signatures) - 1);

        return DocumentArtifactSignature::query()->firstOrCreate(
            [
                'document_artifact_id' => $artifact->getKey(),
                'signature_index' => $index,
            ],
            [
                'esign_provider_response_id' => $providerResponse->getKey(),
                'provider_signature_id' => $this->limit($data->id, 255),
                'field_name' => $this->limit($data->fieldName, 255),
                'signer_user_id' => $belongsToAttempt || $isLastSignature
                    ? $attempt->signer_user_id
                    : null,
                'signer_name' => $this->limit($data->signerName, 255),
                'signed_at' => $this->date($data->signatureDate),
                'location' => $this->limit($data->location, 255),
                'reason' => $this->limit($data->reason, 500),
                'certificate_level_code' => $data->certificateLevelCode,
                'integrity_valid' => $data->integrityValid,
                'certificate_trusted' => $data->certificateTrusted,
                'signature_format' => $this->limit($data->signatureFormat, 100),
                'is_last_signature' => $isLastSignature,
                'long_term_validation' => $data->longTermValidation,
                'digest_algorithm' => $this->limit($data->digestAlgorithm, 100),
                'signature_algorithm' => $this->limit($data->signatureAlgorithm, 100),
                'timestamp_id' => $this->limit($data->timestampInformation?->id, 255),
                'timestamp_at' => $this->date($data->timestampInformation?->timestampDate),
                'timestamp_signer_name' => $this->limit($data->timestampInformation?->signerName, 255),
                'verification_conclusion' => $verification->conclusion,
                'verified_at' => now(),
                'safe_metadata' => [
                    'provider_signature_count' => $verification->signatureCount,
                    'esign_signature_operation_index' => $operationIndex,
                ],
            ],
        );
    }

    private function persistCertificate(
        DocumentArtifactSignature $signature,
        CertificateDetailData $data,
        int $index,
    ): void {
        DocumentArtifactSignatureCertificate::query()->firstOrCreate(
            [
                'document_artifact_signature_id' => $signature->getKey(),
                'chain_index' => $index,
            ],
            [
                'provider_certificate_id' => $this->limit($data->id, 255),
                'signature_algorithm' => $this->limit($data->signatureAlgorithm, 100),
                'not_before_at' => $this->date($data->notBeforeDate),
                'not_after_at' => $this->date($data->notAfterDate),
                'key_usages' => $data->keyUsages,
                'issuer_name' => $this->limit($data->issuerName, 500),
                'serial_number' => $this->limit($data->serialNumber, 255),
                'common_name' => $this->limit($data->commonName, 255),
            ],
        );
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function limit(?string $value, int $length): ?string
    {
        return $value === null ? null : Str::limit($value, $length, '');
    }
}
