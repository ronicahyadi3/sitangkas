<?php

namespace App\Data\Esign;

final readonly class UserStatusData
{
    public function __construct(
        public ?string $statusCode,
        public string $status,
        public ?string $message,
        public int $httpStatus,
        public int $latencyMs,
        public string $correlationId,
    ) {}

    public function eligibleForSigning(): bool
    {
        return $this->status === 'ISSUE';
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'status_code' => $this->statusCode,
            'status' => $this->status,
            'message' => $this->message,
            'eligible_for_signing' => $this->eligibleForSigning(),
            'http_status' => $this->httpStatus,
            'latency_ms' => $this->latencyMs,
            'correlation_id' => $this->correlationId,
        ];
    }
}
