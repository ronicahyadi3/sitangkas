<?php

namespace App\Services\Esign;

use App\Data\Esign\PdfPageGeometryData;
use App\Data\Esign\SigningSessionData;
use Illuminate\Validation\ValidationException;

final class VisibleSigningPlanValidator
{
    public function __construct(private VisibleSigningEditorConfiguration $configuration) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{placements: list<array<string, float|int|string>>, footer: array<string, mixed>|null}
     */
    public function validate(SigningSessionData $session, array $input): array
    {
        $pages = $this->pages($session);

        foreach ($pages as $page) {
            if ($page->rotation !== 0) {
                $this->invalid('placements', 'esign.rotated_pdf_not_supported');
            }
        }

        $placements = $this->normalizeSignaturePlacements($input['placements'] ?? [], $pages);
        $footer = $this->normalizeFooter($session, $input['footer'] ?? null, $pages);
        $this->assertNoCollisions($placements, $footer);

        return ['placements' => $placements, 'footer' => $footer];
    }

    /**
     * @param  array<int, PdfPageGeometryData>  $pages
     * @return list<array<string, float|int|string>>
     */
    private function normalizeSignaturePlacements(mixed $input, array $pages): array
    {
        if (! is_array($input)
            || $input === []
            || count($input) > $this->configuration->maximumOperations()) {
            $this->invalid('placements', 'esign.signature_count_invalid');
        }

        $placements = [];
        foreach (array_values($input) as $placement) {
            if (! is_array($placement)) {
                $this->invalid('placements', 'esign.signature_placement_invalid');
            }

            $pageNumber = (int) ($placement['page'] ?? 0);
            $page = $pages[$pageNumber] ?? null;

            if (! $page instanceof PdfPageGeometryData) {
                $this->invalid('placements', 'esign.signature_page_invalid');
            }

            $normalized = $this->rectangle($placement, $page, 'placements');
            $minimum = $this->configuration->float('qr_minimum_size_pt');
            $maximum = $this->configuration->float('qr_maximum_size_pt');

            if ($normalized['width'] < $minimum
                || $normalized['height'] < $minimum
                || $normalized['width'] > $maximum
                || $normalized['height'] > $maximum) {
                $this->invalid('placements', 'esign.signature_size_invalid');
            }

            $placements[] = [
                'client_id' => (string) ($placement['client_id'] ?? ''),
                'operation_index' => (int) ($placement['operation_index'] ?? -1),
                ...$normalized,
            ];
        }

        usort(
            $placements,
            static fn (array $left, array $right): int => $left['operation_index'] <=> $right['operation_index'],
        );

        if (array_column($placements, 'operation_index') !== range(0, count($placements) - 1)) {
            $this->invalid('placements', 'esign.signature_operation_order_invalid');
        }

        return $placements;
    }

    /**
     * @param  array<int, PdfPageGeometryData>  $pages
     * @return array<string, mixed>|null
     */
    private function normalizeFooter(SigningSessionData $session, mixed $input, array $pages): ?array
    {
        if ($session->signatureState === 'signed') {
            if ($input !== null) {
                $this->invalid('footer', 'esign.footer_for_signed_pdf_forbidden');
            }

            return null;
        }

        if ($session->footerApplied) {
            if ($input !== null) {
                $this->invalid('footer', 'esign.footer_already_applied');
            }

            return null;
        }

        if (! is_array($input)) {
            $this->invalid('footer', 'esign.footer_required_for_unsigned_pdf');
        }

        $text = trim((string) ($input['text'] ?? ''));
        $fontKey = (string) ($input['font_key'] ?? '');
        $fontSize = $this->number($input['font_size_pt'] ?? null, 'footer');

        if ($text === '' || ! array_key_exists($fontKey, $this->configuration->allowedFonts())) {
            $this->invalid('footer', 'esign.footer_style_invalid');
        }

        if ($fontSize < $this->configuration->float('footer_font_size_min_pt')
            || $fontSize > $this->configuration->float('footer_font_size_max_pt')) {
            $this->invalid('footer', 'esign.footer_font_size_invalid');
        }

        $rawPlacements = $input['placements'] ?? null;
        if (! is_array($rawPlacements) || count($rawPlacements) !== count($pages)) {
            $this->invalid('footer.placements', 'esign.footer_all_pages_required');
        }

        $placements = [];
        foreach ($rawPlacements as $placement) {
            if (! is_array($placement)) {
                $this->invalid('footer.placements', 'esign.footer_placement_invalid');
            }

            $pageNumber = (int) ($placement['page'] ?? 0);
            $page = $pages[$pageNumber] ?? null;

            if (! $page instanceof PdfPageGeometryData || isset($placements[$pageNumber])) {
                $this->invalid('footer.placements', 'esign.footer_page_invalid');
            }

            $rectangle = $this->rectangle($placement, $page, 'footer.placements');
            $lineCount = $this->wrappedLineCount($text, $rectangle['width'], $fontSize);
            $minimumHeight = $lineCount * $fontSize * 1.2;

            if ($lineCount < 1
                || $minimumHeight > $rectangle['height'] + 0.01
                || $rectangle['width'] < ($fontSize * 4)) {
                $this->invalid('footer.placements', 'esign.footer_box_too_small');
            }

            $placements[$pageNumber] = $rectangle;
        }

        ksort($placements);
        if (array_keys($placements) !== array_keys($pages)) {
            $this->invalid('footer.placements', 'esign.footer_all_pages_required');
        }

        return [
            'text' => $text,
            'font_key' => $fontKey,
            'font_size_pt' => round($fontSize, 3),
            'is_bold' => (bool) ($input['is_bold'] ?? false),
            'is_italic' => (bool) ($input['is_italic'] ?? false),
            'is_underline' => (bool) ($input['is_underline'] ?? false),
            'placements' => array_values($placements),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, float|int>
     */
    private function rectangle(array $input, PdfPageGeometryData $page, string $field): array
    {
        $pageWidth = $this->number($input['page_width'] ?? null, $field);
        $pageHeight = $this->number($input['page_height'] ?? null, $field);
        $pageRotation = (int) ($input['page_rotation'] ?? -1);
        $originX = $this->number($input['origin_x'] ?? null, $field);
        $originY = $this->number($input['origin_y'] ?? null, $field);
        $width = $this->number($input['width'] ?? null, $field);
        $height = $this->number($input['height'] ?? null, $field);
        $margin = $this->configuration->float('safe_margin_pt');

        if (abs($pageWidth - $page->width) > 0.05
            || abs($pageHeight - $page->height) > 0.05
            || $pageRotation !== $page->rotation) {
            $this->invalid($field, 'esign.page_geometry_mismatch');
        }

        if ($originX < $margin
            || $originY < $margin
            || $width <= 0
            || $height <= 0
            || $originX + $width > $page->width - $margin
            || $originY + $height > $page->height - $margin) {
            $this->invalid($field, 'esign.placement_outside_safe_area');
        }

        return [
            'page' => $page->pageNumber,
            'page_width' => round($page->width, 4),
            'page_height' => round($page->height, 4),
            'page_rotation' => $page->rotation,
            'origin_x' => round($originX, 4),
            'origin_y' => round($originY, 4),
            'width' => round($width, 4),
            'height' => round($height, 4),
        ];
    }

    /**
     * @param  list<array<string, float|int|string>>  $placements
     * @param  array<string, mixed>|null  $footer
     */
    private function assertNoCollisions(array $placements, ?array $footer): void
    {
        $rectangles = [];
        foreach ($placements as $placement) {
            $rectangles[(int) $placement['page']][] = ['type' => 'signature', ...$placement];
        }

        foreach ($footer['placements'] ?? [] as $placement) {
            $rectangles[(int) $placement['page']][] = ['type' => 'footer', ...$placement];
        }

        $gap = $this->configuration->float('minimum_gap_pt');
        foreach ($rectangles as $pageRectangles) {
            $count = count($pageRectangles);
            for ($leftIndex = 0; $leftIndex < $count; $leftIndex++) {
                for ($rightIndex = $leftIndex + 1; $rightIndex < $count; $rightIndex++) {
                    if ($this->overlaps($pageRectangles[$leftIndex], $pageRectangles[$rightIndex], $gap)) {
                        $this->invalid('placements', 'esign.placement_collision');
                    }
                }
            }
        }
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function overlaps(array $left, array $right, float $gap): bool
    {
        return (float) $left['origin_x'] < (float) $right['origin_x'] + (float) $right['width'] + $gap
            && (float) $left['origin_x'] + (float) $left['width'] + $gap > (float) $right['origin_x']
            && (float) $left['origin_y'] < (float) $right['origin_y'] + (float) $right['height'] + $gap
            && (float) $left['origin_y'] + (float) $left['height'] + $gap > (float) $right['origin_y'];
    }

    /** @return array<int, PdfPageGeometryData> */
    private function pages(SigningSessionData $session): array
    {
        $pages = [];
        foreach ($session->pageGeometries as $page) {
            $geometry = PdfPageGeometryData::fromArray($page);
            $pages[$geometry->pageNumber] = $geometry;
        }

        ksort($pages);

        return $pages;
    }

    private function number(mixed $value, string $field): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            $this->invalid($field, 'esign.placement_number_invalid');
        }

        return (float) $value;
    }

    private function wrappedLineCount(string $text, float $maximumWidth, float $fontSize): int
    {
        $lineCount = 0;
        $paragraphs = preg_split('/\R/u', trim($text)) ?: [];

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/u', trim($paragraph)) ?: [];
            $line = '';

            foreach ($words as $word) {
                if ($this->estimatedTextWidth($word, $fontSize) > $maximumWidth) {
                    return 0;
                }

                $candidate = $line === '' ? $word : "{$line} {$word}";
                if ($line !== '' && $this->estimatedTextWidth($candidate, $fontSize) > $maximumWidth) {
                    $lineCount++;
                    $line = $word;
                } else {
                    $line = $candidate;
                }
            }

            if ($line !== '') {
                $lineCount++;
            }
        }

        return $lineCount;
    }

    private function estimatedTextWidth(string $text, float $fontSize): float
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return strlen(is_string($encoded) ? $encoded : $text) * $fontSize * 0.55;
    }

    private function invalid(string $field, string $code): never
    {
        throw ValidationException::withMessages([$field => [$code]]);
    }
}
