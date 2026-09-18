<?php

namespace App\Services\Esign;

use App\Data\Esign\CertificateDetailData;
use App\Data\Esign\SignatureInformationData;
use App\Data\Esign\SignResultData;
use App\Data\Esign\TimestampInformationData;
use App\Data\Esign\UserStatusData;
use App\Data\Esign\VerificationResultData;
use App\Enums\Esign\EsignErrorCode;
use App\Exceptions\Esign\EsignOperationException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class BsreResponseMapper
{
    public function sign(Response $response, int $latencyMs, string $correlationId): SignResultData
    {
        $this->assertSuccessful($response, 'sign', $correlationId, signing: true);
        $payload = $this->jsonPayload($response, 'sign', $correlationId);
        $providerTime = Arr::get($payload, 'time');
        $files = Arr::get($payload, 'file');

        if (! is_int($providerTime)
            || ! is_array($files)
            || ! array_is_list($files)
            || count($files) !== 1
            || ! is_string($files[0])) {
            throw $this->invalidResult('sign', $correlationId, $response->status());
        }

        $signedPdfContents = base64_decode($files[0], true);

        if (! is_string($signedPdfContents)
            || $signedPdfContents === ''
            || ! str_starts_with($signedPdfContents, '%PDF-')) {
            throw $this->invalidResult('sign', $correlationId, $response->status());
        }

        return new SignResultData(
            signedPdfContents: $signedPdfContents,
            providerTime: $providerTime,
            httpStatus: $response->status(),
            latencyMs: $latencyMs,
            correlationId: $correlationId,
        );
    }

    public function verify(Response $response, int $latencyMs, string $correlationId): VerificationResultData
    {
        $this->assertSuccessful($response, 'verify', $correlationId);
        $payload = $this->jsonPayload($response, 'verify', $correlationId);
        $signatureCount = Arr::get($payload, 'signatureCount');
        $conclusion = $this->nullableString(Arr::get($payload, 'conclusion'));
        $signaturePayloads = Arr::get($payload, 'signatureInformations');

        if (! is_int($signatureCount)
            || $signatureCount < 0
            || $conclusion === null
            || ! is_array($signaturePayloads)
            || ! array_is_list($signaturePayloads)
            || count($signaturePayloads) !== $signatureCount) {
            throw $this->invalidResult('verify', $correlationId, $response->status());
        }

        $signatures = [];

        foreach ($signaturePayloads as $signaturePayload) {
            if (! is_array($signaturePayload)) {
                throw $this->invalidResult('verify', $correlationId, $response->status());
            }

            $signatures[] = $this->signatureInformation($signaturePayload);
        }

        return new VerificationResultData(
            signatureCount: $signatureCount,
            description: $this->nullableString(Arr::get($payload, 'description')),
            conclusion: Str::upper($conclusion),
            signatures: $signatures,
            httpStatus: $response->status(),
            latencyMs: $latencyMs,
            correlationId: $correlationId,
        );
    }

    public function userStatus(Response $response, int $latencyMs, string $correlationId): UserStatusData
    {
        $this->assertSuccessful($response, 'user_status', $correlationId);
        $payload = $this->jsonPayload($response, 'user_status', $correlationId);
        $status = $this->nullableString(Arr::get($payload, 'status'));

        if ($status === null) {
            throw $this->invalidResult('user_status', $correlationId, $response->status());
        }

        return new UserStatusData(
            statusCode: $this->scalarString(Arr::get($payload, 'status_code')),
            status: Str::upper($status),
            message: $this->nullableString(Arr::get($payload, 'message')),
            httpStatus: $response->status(),
            latencyMs: $latencyMs,
            correlationId: $correlationId,
        );
    }

    private function assertSuccessful(
        Response $response,
        string $endpoint,
        string $correlationId,
        bool $signing = false,
    ): void {
        if ($response->successful()) {
            return;
        }

        $httpStatus = $response->status();
        $vendorCode = $this->safeVendorCode($response);

        if ($signing && ($response->serverError() || $httpStatus === 408)) {
            throw new EsignOperationException(
                errorCode: EsignErrorCode::OutcomeUnknown,
                retryable: false,
                endpoint: $endpoint,
                httpStatus: $httpStatus,
                vendorCode: $vendorCode,
                correlationId: $correlationId,
            );
        }

        $errorCode = match (true) {
            $response->serverError(), $httpStatus === 408, $httpStatus === 429 => EsignErrorCode::ProviderUnavailable,
            default => EsignErrorCode::ProviderRejected,
        };

        throw new EsignOperationException(
            errorCode: $errorCode,
            retryable: ! $signing && $errorCode === EsignErrorCode::ProviderUnavailable,
            endpoint: $endpoint,
            httpStatus: $httpStatus,
            vendorCode: $vendorCode,
            correlationId: $correlationId,
        );
    }

    /** @return array<string, mixed> */
    private function jsonPayload(Response $response, string $endpoint, string $correlationId): array
    {
        if (! $this->hasJsonContentType($response)) {
            throw $this->invalidResult($endpoint, $correlationId, $response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw $this->invalidResult($endpoint, $correlationId, $response->status());
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function signatureInformation(array $payload): SignatureInformationData
    {
        $certificatePayloads = Arr::get($payload, 'certificateDetails', []);
        $certificateDetails = [];

        if (is_array($certificatePayloads) && array_is_list($certificatePayloads)) {
            foreach ($certificatePayloads as $certificatePayload) {
                if (is_array($certificatePayload)) {
                    $certificateDetails[] = $this->certificateDetail($certificatePayload);
                }
            }
        }

        $timestampPayload = Arr::get(
            $payload,
            'timestampInfomation',
            Arr::get($payload, 'timestampInformation'),
        );

        return new SignatureInformationData(
            location: $this->nullableString(Arr::get($payload, 'location')),
            id: $this->nullableString(Arr::get($payload, 'id')),
            fieldName: $this->nullableString(Arr::get($payload, 'fieldName')),
            certificateDetails: $certificateDetails,
            signatureDate: $this->nullableString(Arr::get($payload, 'signatureDate')),
            certificateLevelCode: $this->nullableInt(Arr::get($payload, 'certLevelCode')),
            integrityValid: $this->nullableBool(Arr::get($payload, 'integrityValid')),
            signatureFormat: $this->nullableString(Arr::get($payload, 'signatureFormat')),
            lastSignature: $this->nullableBool(Arr::get($payload, 'lastSignature')),
            longTermValidation: $this->nullableBool(Arr::get($payload, 'ltv')),
            timestampInformation: is_array($timestampPayload)
                ? $this->timestampInformation($timestampPayload)
                : null,
            certificateTrusted: $this->nullableBool(Arr::get($payload, 'certificateTrusted')),
            digestAlgorithm: $this->nullableString(Arr::get($payload, 'digestAlgorithm')),
            signatureAlgorithm: $this->nullableString(Arr::get($payload, 'signatureAlgorithm')),
            signerName: $this->nullableString(Arr::get($payload, 'signerName')),
            reason: $this->nullableString(Arr::get($payload, 'reason')),
        );
    }

    /** @param array<string, mixed> $payload */
    private function certificateDetail(array $payload): CertificateDetailData
    {
        $keyUsages = Arr::get($payload, 'keyUsages', []);

        return new CertificateDetailData(
            id: $this->nullableString(Arr::get($payload, 'id')),
            signatureAlgorithm: $this->nullableString(
                Arr::get($payload, 'signatureAlgorithm', Arr::get($payload, 'signatureAlgoritm')),
            ),
            notBeforeDate: $this->nullableString(Arr::get($payload, 'notBeforeDate')),
            notAfterDate: $this->nullableString(Arr::get($payload, 'notAfterDate')),
            keyUsages: is_array($keyUsages)
                ? array_values(array_filter($keyUsages, is_string(...)))
                : [],
            issuerName: $this->nullableString(Arr::get($payload, 'issuerName')),
            serialNumber: $this->scalarString(Arr::get($payload, 'serialNumber')),
            commonName: $this->nullableString(Arr::get($payload, 'commonName')),
        );
    }

    /** @param array<string, mixed> $payload */
    private function timestampInformation(array $payload): TimestampInformationData
    {
        return new TimestampInformationData(
            id: $this->nullableString(Arr::get($payload, 'id')),
            timestampDate: $this->nullableString(Arr::get($payload, 'timestampDate')),
            signerName: $this->nullableString(Arr::get($payload, 'signerName')),
        );
    }

    private function safeVendorCode(Response $response): ?string
    {
        if (! $this->hasJsonContentType($response)) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        foreach (['status_code', 'code', 'error'] as $key) {
            $candidate = $this->scalarString(Arr::get($payload, $key));

            if ($candidate !== null && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    private function hasJsonContentType(Response $response): bool
    {
        $contentType = Str::lower((string) $response->header('Content-Type'));

        return Str::contains($contentType, ['application/json', '+json']);
    }

    private function invalidResult(
        string $endpoint,
        string $correlationId,
        ?int $httpStatus = null,
    ): EsignOperationException {
        return new EsignOperationException(
            errorCode: EsignErrorCode::ResultInvalid,
            retryable: false,
            endpoint: $endpoint,
            httpStatus: $httpStatus,
            correlationId: $correlationId,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function scalarString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        return $this->nullableString((string) $value);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function nullableBool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
