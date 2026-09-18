<?php

namespace App\Services\Esign;

use App\Contracts\Esign\EsignGateway;
use App\Data\Esign\SignRequestData;
use App\Data\Esign\SignResultData;
use App\Data\Esign\UserStatusData;
use App\Data\Esign\UserStatusRequestData;
use App\Data\Esign\VerificationResultData;
use App\Data\Esign\VerifyPdfData;
use App\Enums\Esign\EsignErrorCode;
use App\Exceptions\Esign\EsignOperationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Log\LogManager;
use Illuminate\Support\Str;

final class BsreClient implements EsignGateway
{
    public function __construct(
        private readonly BsreConfiguration $configuration,
        private readonly EsignPayloadBuilder $payloadBuilder,
        private readonly BsreResponseMapper $responseMapper,
        private readonly Factory $http,
        private readonly LogManager $log,
    ) {}

    public function sign(SignRequestData $request): SignResultData
    {
        $correlationId = $this->correlationId($request->correlationId, 'sign');
        $startedAt = hrtime(true);
        $payload = $this->payloadBuilder->sign($request, $correlationId);

        try {
            $response = $this->pendingRequest('sign')->post(
                $this->configuration->endpoint('sign'),
                $payload,
            );
        } catch (ConnectionException) {
            $exception = new EsignOperationException(
                errorCode: EsignErrorCode::OutcomeUnknown,
                retryable: false,
                endpoint: 'sign',
                correlationId: $correlationId,
            );

            $this->logFailure($exception, $this->elapsedMilliseconds($startedAt));

            throw $exception;
        }

        $latencyMs = $this->elapsedMilliseconds($startedAt);

        try {
            $result = $this->responseMapper->sign($response, $latencyMs, $correlationId);
        } catch (EsignOperationException $exception) {
            $this->logFailure($exception, $latencyMs);

            throw $exception;
        }

        $this->logSuccess(
            endpoint: 'sign',
            httpStatus: $result->httpStatus,
            latencyMs: $result->latencyMs,
            correlationId: $result->correlationId,
            vendorCode: null,
            extra: [
                'output_size' => $result->pdfSize,
                'output_sha256' => $result->pdfSha256,
            ],
        );

        return $result;
    }

    public function verify(VerifyPdfData $request): VerificationResultData
    {
        $correlationId = $this->correlationId($request->correlationId, 'verify');
        $startedAt = hrtime(true);
        $payload = $this->payloadBuilder->verify($request, $correlationId);

        try {
            $response = $this->pendingRequest('verify')->post(
                $this->configuration->endpoint('verify'),
                $payload,
            );
        } catch (ConnectionException) {
            $exception = $this->providerUnavailable('verify', $correlationId);
            $this->logFailure($exception, $this->elapsedMilliseconds($startedAt));

            throw $exception;
        }

        $latencyMs = $this->elapsedMilliseconds($startedAt);

        try {
            $result = $this->responseMapper->verify($response, $latencyMs, $correlationId);
        } catch (EsignOperationException $exception) {
            $this->logFailure($exception, $latencyMs);

            throw $exception;
        }

        $this->logSuccess(
            endpoint: 'verify',
            httpStatus: $result->httpStatus,
            latencyMs: $result->latencyMs,
            correlationId: $result->correlationId,
            vendorCode: $result->conclusion,
            extra: ['signature_count' => $result->signatureCount],
        );

        return $result;
    }

    public function checkUserStatus(UserStatusRequestData $request): UserStatusData
    {
        $correlationId = $this->correlationId($request->correlationId, 'user_status');
        $startedAt = hrtime(true);
        $payload = $this->payloadBuilder->userStatus($request, $correlationId);

        try {
            $response = $this->pendingRequest('status')->post(
                $this->configuration->endpoint('user_status'),
                $payload,
            );
        } catch (ConnectionException) {
            $exception = $this->providerUnavailable('user_status', $correlationId);
            $this->logFailure($exception, $this->elapsedMilliseconds($startedAt));

            throw $exception;
        }

        $latencyMs = $this->elapsedMilliseconds($startedAt);

        try {
            $result = $this->responseMapper->userStatus($response, $latencyMs, $correlationId);
        } catch (EsignOperationException $exception) {
            $this->logFailure($exception, $latencyMs);

            throw $exception;
        }

        $this->logSuccess(
            endpoint: 'user_status',
            httpStatus: $result->httpStatus,
            latencyMs: $result->latencyMs,
            correlationId: $result->correlationId,
            vendorCode: $result->statusCode ?? $result->status,
        );

        return $result;
    }

    private function pendingRequest(string $operation): PendingRequest
    {
        return $this->http
            ->baseUrl($this->configuration->baseUrl())
            ->withBasicAuth(
                $this->configuration->username(),
                $this->configuration->password(),
            )
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->configuration->connectTimeout($operation))
            ->timeout($this->configuration->timeout($operation))
            ->withOptions(['verify' => $this->configuration->verifyTls]);
    }

    private function correlationId(?string $correlationId, string $endpoint): string
    {
        if ($correlationId === null) {
            return (string) Str::uuid();
        }

        if (! Str::isUuid($correlationId)) {
            throw new EsignOperationException(
                errorCode: EsignErrorCode::InvalidRequest,
                retryable: false,
                endpoint: $endpoint,
            );
        }

        return Str::lower($correlationId);
    }

    private function providerUnavailable(
        string $endpoint,
        string $correlationId,
    ): EsignOperationException {
        return new EsignOperationException(
            errorCode: EsignErrorCode::ProviderUnavailable,
            retryable: true,
            endpoint: $endpoint,
            correlationId: $correlationId,
        );
    }

    /**
     * @param  array<string, bool|int|string|null>  $extra
     */
    private function logSuccess(
        string $endpoint,
        int $httpStatus,
        int $latencyMs,
        string $correlationId,
        ?string $vendorCode,
        array $extra = [],
    ): void {
        $this->log->channel('module_esign')->info('BSrE eSign request completed.', [
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'latency_ms' => $latencyMs,
            'vendor_code' => $vendorCode,
            'correlation_id' => $correlationId,
            ...$extra,
        ]);
    }

    private function logFailure(EsignOperationException $exception, int $latencyMs): void
    {
        $this->log->channel('module_esign')->warning('BSrE eSign request failed.', [
            ...$exception->context(),
            'latency_ms' => $latencyMs,
        ]);
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
