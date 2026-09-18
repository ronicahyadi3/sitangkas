<?php

namespace App\Data\Esign;

final readonly class VerificationResultData
{
    /**
     * @param  list<SignatureInformationData>  $signatures
     */
    public function __construct(
        public int $signatureCount,
        public ?string $description,
        public string $conclusion,
        public array $signatures,
        public int $httpStatus,
        public int $latencyMs,
        public string $correlationId,
    ) {}

    public function isValid(): bool
    {
        return $this->conclusion === 'VALID'
            && $this->signatureCount > 0
            && $this->signatures !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'signature_count' => $this->signatureCount,
            'description' => $this->description,
            'conclusion' => $this->conclusion,
            'valid' => $this->isValid(),
            'signatures' => array_map(
                static fn (SignatureInformationData $signature): array => $signature->toArray(),
                $this->signatures,
            ),
            'http_status' => $this->httpStatus,
            'latency_ms' => $this->latencyMs,
            'correlation_id' => $this->correlationId,
        ];
    }
}
