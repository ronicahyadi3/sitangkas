<?php

namespace App\Data\Esign;

use SensitiveParameter;

final readonly class SignResultData
{
    public int $pdfSize;

    public string $pdfSha256;

    public function __construct(
        #[SensitiveParameter]
        private string $signedPdfContents,
        public int $providerTime,
        public int $httpStatus,
        public int $latencyMs,
        public string $correlationId,
    ) {
        $this->pdfSize = strlen($signedPdfContents);
        $this->pdfSha256 = hash('sha256', $signedPdfContents);
    }

    public function signedPdfContents(): string
    {
        return $this->signedPdfContents;
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'provider_time' => $this->providerTime,
            'http_status' => $this->httpStatus,
            'latency_ms' => $this->latencyMs,
            'correlation_id' => $this->correlationId,
            'pdf_size' => $this->pdfSize,
            'pdf_sha256' => $this->pdfSha256,
        ];
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            ...$this->toArray(),
            'signedPdfContents' => '[REDACTED]',
        ];
    }
}
