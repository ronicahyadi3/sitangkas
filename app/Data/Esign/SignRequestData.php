<?php

namespace App\Data\Esign;

use SensitiveParameter;

final readonly class SignRequestData
{
    public int $pdfSize;

    public string $pdfSha256;

    public function __construct(
        #[SensitiveParameter]
        private string $nik,
        #[SensitiveParameter]
        private string $passphrase,
        #[SensitiveParameter]
        private string $pdfContents,
        public ?string $correlationId = null,
    ) {
        $this->pdfSize = strlen($pdfContents);
        $this->pdfSha256 = hash('sha256', $pdfContents);
    }

    public function nik(): string
    {
        return $this->nik;
    }

    public function passphrase(): string
    {
        return $this->passphrase;
    }

    public function pdfContents(): string
    {
        return $this->pdfContents;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'nik' => '[REDACTED]',
            'passphrase' => '[REDACTED]',
            'pdfContents' => '[REDACTED]',
            'pdfSize' => $this->pdfSize,
            'pdfSha256' => $this->pdfSha256,
            'correlationId' => $this->correlationId,
        ];
    }
}
