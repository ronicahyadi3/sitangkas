<?php

namespace App\Data\Esign;

use SensitiveParameter;

final readonly class VerifyPdfData
{
    public int $pdfSize;

    public string $pdfSha256;

    public function __construct(
        #[SensitiveParameter]
        private string $pdfContents,
        public ?string $correlationId = null,
    ) {
        $this->pdfSize = strlen($pdfContents);
        $this->pdfSha256 = hash('sha256', $pdfContents);
    }

    public function pdfContents(): string
    {
        return $this->pdfContents;
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'pdfContents' => '[REDACTED]',
            'pdfSize' => $this->pdfSize,
            'pdfSha256' => $this->pdfSha256,
            'correlationId' => $this->correlationId,
        ];
    }
}
