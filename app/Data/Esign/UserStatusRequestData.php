<?php

namespace App\Data\Esign;

use SensitiveParameter;

final readonly class UserStatusRequestData
{
    public function __construct(
        #[SensitiveParameter]
        private string $nik,
        public ?string $correlationId = null,
    ) {}

    public function nik(): string
    {
        return $this->nik;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'nik' => '[REDACTED]',
            'correlationId' => $this->correlationId,
        ];
    }
}
