<?php

namespace App\Data\Esign;

use Carbon\CarbonImmutable;
use SensitiveParameter;

final readonly class SigningSecretData
{
    public function __construct(
        public string $reference,
        public int $actorUserId,
        #[SensitiveParameter] private string $passphrase,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $expiresAt,
    ) {}

    public function passphrase(): string
    {
        return $this->passphrase;
    }

    /** @return array{reference: string, actor_user_id: int, passphrase: string, created_at: string, expires_at: string} */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'actor_user_id' => $this->actorUserId,
            'passphrase' => $this->passphrase,
            'created_at' => $this->createdAt->toIso8601String(),
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }

    /** @return array<string, int|string> */
    public function __debugInfo(): array
    {
        return [
            'reference' => $this->reference,
            'actorUserId' => $this->actorUserId,
            'passphrase' => '[REDACTED]',
            'createdAt' => $this->createdAt->toIso8601String(),
            'expiresAt' => $this->expiresAt->toIso8601String(),
        ];
    }
}
