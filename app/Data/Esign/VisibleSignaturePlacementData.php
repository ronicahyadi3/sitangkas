<?php

namespace App\Data\Esign;

use InvalidArgumentException;

final readonly class VisibleSignaturePlacementData
{
    public function __construct(
        public int $pageNumber,
        public float $originX,
        public float $originY,
        public float $width,
        public float $height,
    ) {
        if ($pageNumber < 1
            || ! is_finite($originX)
            || ! is_finite($originY)
            || ! is_finite($width)
            || ! is_finite($height)
            || $originX < 0
            || $originY < 0
            || $width <= 0
            || $height <= 0) {
            throw new InvalidArgumentException('Visible signature placement is invalid.');
        }
    }

    /** @return array{page: int, origin_x: float, origin_y: float, width: float, height: float} */
    public function toArray(): array
    {
        return [
            'page' => $this->pageNumber,
            'origin_x' => $this->originX,
            'origin_y' => $this->originY,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
