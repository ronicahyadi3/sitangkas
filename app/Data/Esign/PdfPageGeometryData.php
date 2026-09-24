<?php

namespace App\Data\Esign;

use App\Exceptions\Esign\EsignInvariantViolationException;

final readonly class PdfPageGeometryData
{
    public function __construct(
        public int $pageNumber,
        public float $width,
        public float $height,
        public int $rotation,
    ) {
        if ($pageNumber < 1
            || ! is_finite($width)
            || ! is_finite($height)
            || $width <= 0
            || $height <= 0
            || ! in_array($rotation, [0, 90, 180, 270], true)) {
            throw new EsignInvariantViolationException('pdf_page_geometry_invalid');
        }
    }

    /** @return array{page: int, width: float, height: float, rotation: int} */
    public function toArray(): array
    {
        return [
            'page' => $this->pageNumber,
            'width' => $this->width,
            'height' => $this->height,
            'rotation' => $this->rotation,
        ];
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        if (! is_int($value['page'] ?? null)
            || ! is_numeric($value['width'] ?? null)
            || ! is_numeric($value['height'] ?? null)
            || ! is_int($value['rotation'] ?? null)) {
            throw new EsignInvariantViolationException('pdf_page_geometry_invalid');
        }

        return new self(
            pageNumber: $value['page'],
            width: (float) $value['width'],
            height: (float) $value['height'],
            rotation: $value['rotation'],
        );
    }
}
