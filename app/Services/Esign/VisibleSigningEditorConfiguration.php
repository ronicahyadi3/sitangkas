<?php

namespace App\Services\Esign;

use App\Data\Esign\PdfPageGeometryData;
use App\Data\Esign\SigningSessionData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class VisibleSigningEditorConfiguration
{
    public function __construct(private ConfigRepository $config) {}

    /** @return array<string, mixed> */
    public function forClient(SigningSessionData $session): array
    {
        $footerAllowed = $session->signatureState === 'unsigned' && ! $session->footerApplied;

        return [
            'coordinate_origin' => 'top_left',
            'measurement_unit' => 'pt',
            'maximum_signature_count' => $this->maximumOperations(),
            'qr' => [
                'minimum_size_pt' => $this->float('qr_minimum_size_pt'),
                'maximum_size_pt' => $this->float('qr_maximum_size_pt'),
            ],
            'safe_margin_pt' => $this->float('safe_margin_pt'),
            'minimum_gap_pt' => $this->float('minimum_gap_pt'),
            'rotated_pages_supported' => false,
            'footer' => [
                'allowed' => $footerAllowed,
                'required' => $footerAllowed,
                'text' => $footerAllowed ? $this->footerText() : null,
                'font_key' => $footerAllowed ? $this->defaultFontKey() : null,
                'font_size_pt' => $footerAllowed ? $this->defaultFontSize() : null,
                'is_bold' => false,
                'is_italic' => false,
                'is_underline' => false,
                'font_size_min_pt' => $this->float('footer_font_size_min_pt'),
                'font_size_max_pt' => $this->float('footer_font_size_max_pt'),
                'allowed_fonts' => $this->allowedFonts(),
                'placements' => $footerAllowed ? $this->defaultFooterPlacements($session) : [],
            ],
        ];
    }

    /** @return array<string, string> */
    public function allowedFonts(): array
    {
        $fonts = $this->config->get('esign.visible_editor.allowed_fonts');

        if (! is_array($fonts) || $fonts === []) {
            throw new EsignInvariantViolationException('pdf_editor_fonts_invalid');
        }

        foreach ($fonts as $key => $label) {
            if (! is_string($key) || $key === '' || ! is_string($label) || $label === '') {
                throw new EsignInvariantViolationException('pdf_editor_fonts_invalid');
            }
        }

        return $fonts;
    }

    public function maximumOperations(): int
    {
        $maximum = $this->config->get('esign.visible_editor.max_operations', 5);

        if (! is_int($maximum) || $maximum < 1 || $maximum > 20) {
            throw new EsignInvariantViolationException('pdf_editor_operation_limit_invalid');
        }

        return $maximum;
    }

    public function rendererVersion(): string
    {
        $version = $this->config->get('esign.visible_editor.renderer_version');

        if (! is_string($version) || $version === '') {
            throw new EsignInvariantViolationException('pdf_editor_renderer_version_invalid');
        }

        return $version;
    }

    public function float(string $key): float
    {
        $value = $this->config->get("esign.visible_editor.{$key}");

        if (! is_float($value) && ! is_int($value)) {
            throw new EsignInvariantViolationException("pdf_editor_{$key}_invalid");
        }

        return (float) $value;
    }

    public function footerText(): string
    {
        $text = $this->config->get('esign.visible_editor.footer_default_text');

        if (! is_string($text) || trim($text) === '') {
            throw new EsignInvariantViolationException('pdf_editor_footer_text_invalid');
        }

        return trim($text);
    }

    public function defaultFontKey(): string
    {
        $font = $this->config->get('esign.visible_editor.footer_default_font');

        if (! is_string($font) || ! array_key_exists($font, $this->allowedFonts())) {
            throw new EsignInvariantViolationException('pdf_editor_default_font_invalid');
        }

        return $font;
    }

    public function defaultFontSize(): float
    {
        return $this->float('footer_default_font_size_pt');
    }

    /** @return list<array<string, float|int>> */
    private function defaultFooterPlacements(SigningSessionData $session): array
    {
        $margin = $this->float('safe_margin_pt');
        $height = $this->float('footer_height_pt');

        return array_map(function (array $page) use ($margin, $height): array {
            $geometry = PdfPageGeometryData::fromArray($page);

            return [
                'page' => $geometry->pageNumber,
                'page_width' => $geometry->width,
                'page_height' => $geometry->height,
                'page_rotation' => $geometry->rotation,
                'origin_x' => $margin,
                'origin_y' => $geometry->height - $margin - $height,
                'width' => $geometry->width - ($margin * 2),
                'height' => $height,
            ];
        }, $session->pageGeometries);
    }
}
