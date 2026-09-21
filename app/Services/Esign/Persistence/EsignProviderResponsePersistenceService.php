<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\SignResultData;
use App\Data\Esign\VerificationResultData;
use App\Enums\Esign\EsignErrorCode;
use App\Enums\Esign\EsignProviderOperation;
use App\Enums\Esign\EsignProviderOutcome;
use App\Exceptions\Esign\EsignOperationException;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignProviderResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EsignProviderResponsePersistenceService
{
    public function recordSignSuccess(EsignAttempt $attempt, SignResultData $result): EsignProviderResponse
    {
        return $this->record($attempt, [
            'operation' => EsignProviderOperation::Sign,
            'outcome' => EsignProviderOutcome::Success,
            'http_status' => $result->httpStatus,
            'provider_time' => $result->providerTime,
            'provider_time_unit' => 'ms',
            'latency_ms' => $result->latencyMs,
            'content_type' => 'application/pdf',
            'correlation_id' => $result->correlationId,
            'response_size' => $result->pdfSize,
            'response_sha256' => $result->pdfSha256,
            'safe_payload' => $result->toArray(),
            'input_artifact_id' => $attempt->source_artifact_id,
        ]);
    }

    public function recordVerifyResult(
        EsignAttempt $attempt,
        VerificationResultData $result,
        ?int $outputArtifactId = null,
    ): EsignProviderResponse {
        return $this->record($attempt, [
            'operation' => EsignProviderOperation::Verify,
            'outcome' => $result->isValid()
                ? EsignProviderOutcome::Success
                : EsignProviderOutcome::BusinessFailure,
            'http_status' => $result->httpStatus,
            'provider_code' => $result->conclusion,
            'provider_message' => $result->description,
            'latency_ms' => $result->latencyMs,
            'content_type' => 'application/json',
            'correlation_id' => $result->correlationId,
            'safe_payload' => [
                'signature_count' => $result->signatureCount,
                'description' => $result->description,
                'conclusion' => $result->conclusion,
                'valid' => $result->isValid(),
            ],
            'input_artifact_id' => $attempt->source_artifact_id,
            'output_artifact_id' => $outputArtifactId,
        ]);
    }

    public function recordFailure(
        EsignAttempt $attempt,
        EsignProviderOperation $operation,
        EsignOperationException $exception,
    ): EsignProviderResponse {
        return $this->record($attempt, [
            'operation' => $operation,
            'outcome' => $this->failureOutcome($exception->errorCode),
            'http_status' => $exception->httpStatus,
            'provider_code' => $exception->vendorCode ?? $exception->errorCode->value,
            'provider_message' => $exception->errorCode->message(),
            'correlation_id' => $exception->correlationId,
            'safe_payload' => [
                'application_error_code' => $exception->errorCode->value,
                'retryable' => $exception->retryable,
            ],
            'input_artifact_id' => $attempt->source_artifact_id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function record(EsignAttempt $attempt, array $attributes): EsignProviderResponse
    {
        return DB::transaction(function () use ($attempt, $attributes): EsignProviderResponse {
            $lockedAttempt = EsignAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            $sequence = ((int) $lockedAttempt->providerResponses()->max('response_sequence')) + 1;

            return EsignProviderResponse::query()->create([
                'public_id' => (string) Str::uuid(),
                'esign_attempt_id' => $lockedAttempt->getKey(),
                'response_sequence' => $sequence,
                'provider' => 'bsre',
                'source' => 'live',
                'received_at' => now(),
                ...$attributes,
            ]);
        }, attempts: 3);
    }

    private function failureOutcome(EsignErrorCode $errorCode): EsignProviderOutcome
    {
        return match ($errorCode) {
            EsignErrorCode::OutcomeUnknown => EsignProviderOutcome::Unknown,
            EsignErrorCode::ProviderUnavailable => EsignProviderOutcome::TechnicalFailure,
            EsignErrorCode::ResultInvalid => EsignProviderOutcome::InvalidResponse,
            default => EsignProviderOutcome::BusinessFailure,
        };
    }
}
