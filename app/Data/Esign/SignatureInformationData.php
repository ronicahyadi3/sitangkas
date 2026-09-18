<?php

namespace App\Data\Esign;

final readonly class SignatureInformationData
{
    /**
     * @param  list<CertificateDetailData>  $certificateDetails
     */
    public function __construct(
        public ?string $location,
        public ?string $id,
        public ?string $fieldName,
        public array $certificateDetails,
        public ?string $signatureDate,
        public ?int $certificateLevelCode,
        public ?bool $integrityValid,
        public ?string $signatureFormat,
        public ?bool $lastSignature,
        public ?bool $longTermValidation,
        public ?TimestampInformationData $timestampInformation,
        public ?bool $certificateTrusted,
        public ?string $digestAlgorithm,
        public ?string $signatureAlgorithm,
        public ?string $signerName,
        public ?string $reason,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'location' => $this->location,
            'id' => $this->id,
            'field_name' => $this->fieldName,
            'certificate_details' => array_map(
                static fn (CertificateDetailData $certificate): array => $certificate->toArray(),
                $this->certificateDetails,
            ),
            'signature_date' => $this->signatureDate,
            'certificate_level_code' => $this->certificateLevelCode,
            'integrity_valid' => $this->integrityValid,
            'signature_format' => $this->signatureFormat,
            'last_signature' => $this->lastSignature,
            'long_term_validation' => $this->longTermValidation,
            'timestamp_information' => $this->timestampInformation?->toArray(),
            'certificate_trusted' => $this->certificateTrusted,
            'digest_algorithm' => $this->digestAlgorithm,
            'signature_algorithm' => $this->signatureAlgorithm,
            'signer_name' => $this->signerName,
            'reason' => $this->reason,
        ];
    }
}
