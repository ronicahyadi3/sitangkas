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
        /** @var list<SignaturePropertyData> */
        private array $signatureProperties = [],
    ) {
        foreach ($signatureProperties as $signatureProperty) {
            if (! $signatureProperty instanceof SignaturePropertyData) {
                throw new \InvalidArgumentException('Signature properties must contain SignaturePropertyData instances.');
            }
        }

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

    /** @return list<SignaturePropertyData> */
    public function signatureProperties(): array
    {
        return $this->signatureProperties === []
            ? [SignaturePropertyData::invisible()]
            : $this->signatureProperties;
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
            'signatureProperties' => array_map(
                static fn (SignaturePropertyData $property): array => $property->toSafeArray(),
                $this->signatureProperties(),
            ),
        ];
    }
}
