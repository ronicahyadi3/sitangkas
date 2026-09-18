<?php

namespace App\Exceptions\Esign;

use App\Enums\Esign\EsignErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class EsignOperationException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly EsignErrorCode $errorCode,
        public readonly bool $retryable,
        public readonly ?string $endpoint = null,
        public readonly ?int $httpStatus = null,
        public readonly ?string $vendorCode = null,
        public readonly ?string $correlationId = null,
    ) {
        parent::__construct($errorCode->message());
    }

    public function outcomeIsUnknown(): bool
    {
        return $this->errorCode === EsignErrorCode::OutcomeUnknown;
    }

    /**
     * @return array{
     *     esign_error_code: string,
     *     esign_retryable: bool,
     *     esign_endpoint: string|null,
     *     esign_http_status: int|null,
     *     esign_vendor_code: string|null,
     *     esign_correlation_id: string|null
     * }
     */
    public function context(): array
    {
        return [
            'esign_error_code' => $this->errorCode->value,
            'esign_retryable' => $this->retryable,
            'esign_endpoint' => $this->endpoint,
            'esign_http_status' => $this->httpStatus,
            'esign_vendor_code' => $this->vendorCode,
            'esign_correlation_id' => $this->correlationId,
        ];
    }
}
