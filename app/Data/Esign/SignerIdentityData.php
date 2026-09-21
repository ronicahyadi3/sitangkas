<?php

namespace App\Data\Esign;

use SensitiveParameter;

final readonly class SignerIdentityData
{
    public function __construct(
        public int $userId,
        public int $userPositionId,
        public string $name,
        public string $maskedNik,
        #[SensitiveParameter] private string $nik,
    ) {}

    public function nik(): string
    {
        return $this->nik;
    }

    /** @return array{user_id: int, user_position_id: int, name: string, masked_nik: string} */
    public function toSafeArray(): array
    {
        return [
            'user_id' => $this->userId,
            'user_position_id' => $this->userPositionId,
            'name' => $this->name,
            'masked_nik' => $this->maskedNik,
        ];
    }

    /** @return array{userId: int, userPositionId: int, name: string, maskedNik: string, nik: string} */
    public function __debugInfo(): array
    {
        return [
            'userId' => $this->userId,
            'userPositionId' => $this->userPositionId,
            'name' => $this->name,
            'maskedNik' => $this->maskedNik,
            'nik' => '[REDACTED]',
        ];
    }
}
