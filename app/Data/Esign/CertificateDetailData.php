<?php

namespace App\Data\Esign;

final readonly class CertificateDetailData
{
    /**
     * @param  list<string>  $keyUsages
     */
    public function __construct(
        public ?string $id,
        public ?string $signatureAlgorithm,
        public ?string $notBeforeDate,
        public ?string $notAfterDate,
        public array $keyUsages,
        public ?string $issuerName,
        public ?string $serialNumber,
        public ?string $commonName,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'signature_algorithm' => $this->signatureAlgorithm,
            'not_before_date' => $this->notBeforeDate,
            'not_after_date' => $this->notAfterDate,
            'key_usages' => $this->keyUsages,
            'issuer_name' => $this->issuerName,
            'serial_number' => $this->serialNumber,
            'common_name' => $this->commonName,
        ];
    }
}
