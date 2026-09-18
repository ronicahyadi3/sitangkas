<?php

namespace App\Data\Esign;

final readonly class TimestampInformationData
{
    public function __construct(
        public ?string $id,
        public ?string $timestampDate,
        public ?string $signerName,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp_date' => $this->timestampDate,
            'signer_name' => $this->signerName,
        ];
    }
}
