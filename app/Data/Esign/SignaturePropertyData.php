<?php

namespace App\Data\Esign;

use App\Enums\Esign\SignatureDisplayMode;
use SensitiveParameter;

final readonly class SignaturePropertyData
{
    public int $imageSize;

    public ?string $imageSha256;

    private function __construct(
        public SignatureDisplayMode $displayMode,
        #[SensitiveParameter]
        private ?string $imageContents = null,
        public ?int $pageNumber = null,
        public ?float $originX = null,
        public ?float $originY = null,
        public ?float $width = null,
        public ?float $height = null,
        public ?string $location = null,
        public ?string $reason = null,
        public ?string $contactInfo = null,
    ) {
        $this->imageSize = $imageContents === null ? 0 : strlen($imageContents);
        $this->imageSha256 = $imageContents === null ? null : hash('sha256', $imageContents);
    }

    public static function invisible(): self
    {
        return new self(SignatureDisplayMode::Invisible);
    }

    public static function visible(
        #[SensitiveParameter] string $imageContents,
        int $pageNumber,
        float $originX,
        float $originY,
        float $width,
        float $height,
        ?string $location = null,
        ?string $reason = null,
        ?string $contactInfo = null,
    ): self {
        return new self(
            displayMode: SignatureDisplayMode::Visible,
            imageContents: $imageContents,
            pageNumber: $pageNumber,
            originX: $originX,
            originY: $originY,
            width: $width,
            height: $height,
            location: $location,
            reason: $reason,
            contactInfo: $contactInfo,
        );
    }

    public function imageContents(): ?string
    {
        return $this->imageContents;
    }

    /** @return array<string, bool|float|int|string|null> */
    public function toSafeArray(): array
    {
        return [
            'display_mode' => $this->displayMode->value,
            'image_size' => $this->imageSize,
            'image_sha256' => $this->imageSha256,
            'page_number' => $this->pageNumber,
            'origin_x' => $this->originX,
            'origin_y' => $this->originY,
            'width' => $this->width,
            'height' => $this->height,
            'has_location' => $this->location !== null,
            'has_reason' => $this->reason !== null,
            'has_contact_info' => $this->contactInfo !== null,
        ];
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            ...$this->toSafeArray(),
            'imageContents' => '[REDACTED]',
        ];
    }
}
