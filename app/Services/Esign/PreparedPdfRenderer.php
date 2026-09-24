<?php

namespace App\Services\Esign;

use App\Data\Esign\PdfPageGeometryData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class PreparedPdfRenderer
{
    public function __construct(
        private ConfigRepository $config,
        private VisibleSigningEditorConfiguration $editorConfiguration,
    ) {}

    /**
     * @param  list<array{page: int, width: float, height: float, rotation: int}>  $pageGeometries
     * @param  array<string, mixed>|null  $footer
     */
    public function render(string $sourcePdf, array $pageGeometries, ?array $footer): string
    {
        if (! str_starts_with($sourcePdf, '%PDF-')) {
            throw new EsignInvariantViolationException('prepared_rendition_source_invalid');
        }

        if ($footer === null) {
            return $sourcePdf;
        }

        $temporaryDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sitangkas-render-'.Str::uuid();
        $sourcePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'source.pdf';
        $overlayPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'overlay.pdf';
        $outputPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'prepared.pdf';

        try {
            if (! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
                throw new EsignInvariantViolationException('prepared_rendition_temporary_directory_failed');
            }

            $overlayPdf = $this->buildFooterOverlay($pageGeometries, $footer);

            if (file_put_contents($sourcePath, $sourcePdf, LOCK_EX) !== strlen($sourcePdf)
                || file_put_contents($overlayPath, $overlayPdf, LOCK_EX) !== strlen($overlayPdf)) {
                throw new EsignInvariantViolationException('prepared_rendition_temporary_write_failed');
            }

            $process = new Process([
                $this->qpdfBinary(),
                $sourcePath,
                '--overlay',
                $overlayPath,
                '--',
                $outputPath,
            ]);
            $process->setTimeout($this->timeoutSeconds());
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                throw new EsignInvariantViolationException('prepared_rendition_render_failed');
            }

            $contents = file_get_contents($outputPath);
            if (! is_string($contents) || ! str_starts_with($contents, '%PDF-')) {
                throw new EsignInvariantViolationException('prepared_rendition_output_invalid');
            }

            $this->assertQpdfValid($outputPath);

            return $contents;
        } catch (EsignInvariantViolationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new EsignInvariantViolationException('prepared_rendition_render_failed');
        } finally {
            foreach ([$outputPath, $overlayPath, $sourcePath] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            if (is_dir($temporaryDirectory)) {
                @rmdir($temporaryDirectory);
            }
        }
    }

    /**
     * @param  list<array{page: int, width: float, height: float, rotation: int}>  $pageGeometries
     * @param  array<string, mixed>  $footer
     */
    private function buildFooterOverlay(array $pageGeometries, array $footer): string
    {
        $placements = [];
        foreach ($footer['placements'] as $placement) {
            $placements[(int) $placement['page']] = $placement;
        }

        $fontName = $this->baseFontName(
            (string) $footer['font_key'],
            (bool) $footer['is_bold'],
            (bool) $footer['is_italic'],
        );
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => "<< /Type /Font /Subtype /Type1 /BaseFont /{$fontName} /Encoding /WinAnsiEncoding >>",
        ];
        $pageObjectNumbers = [];
        $nextObject = 4;

        foreach ($pageGeometries as $pageValue) {
            $page = PdfPageGeometryData::fromArray($pageValue);
            $placement = $placements[$page->pageNumber] ?? null;

            if (! is_array($placement)) {
                throw new EsignInvariantViolationException('prepared_rendition_footer_page_missing');
            }

            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $pageObjectNumbers[] = $pageObject;
            $content = $this->footerContent($page, $placement, $footer);
            $objects[$pageObject] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.4F %.4F] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                $page->width,
                $page->height,
                $contentObject,
            );
            $objects[$contentObject] = '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream";
        }

        $kids = implode(' ', array_map(static fn (int $object): string => "{$object} 0 R", $pageObjectNumbers));
        $objects[2] = '<< /Type /Pages /Count '.count($pageObjectNumbers)." /Kids [{$kids}] >>";
        ksort($objects);

        return $this->serializePdf($objects);
    }

    /** @param array<string, mixed> $placement @param array<string, mixed> $footer */
    private function footerContent(PdfPageGeometryData $page, array $placement, array $footer): string
    {
        $fontSize = (float) $footer['font_size_pt'];
        $lineHeight = $fontSize * 1.2;
        $lines = $this->wrapText((string) $footer['text'], (float) $placement['width'], $fontSize);
        $requiredHeight = count($lines) * $lineHeight;

        if ($requiredHeight > (float) $placement['height'] + 0.01) {
            throw new EsignInvariantViolationException('prepared_rendition_footer_text_overflow');
        }

        $x = (float) $placement['origin_x'];
        $boxBottom = $page->height - (float) $placement['origin_y'] - (float) $placement['height'];
        $baseline = $page->height - (float) $placement['origin_y'] - $fontSize;
        $commands = [
            'q',
            sprintf('%.4F %.4F %.4F %.4F re W n', $x, $boxBottom, (float) $placement['width'], (float) $placement['height']),
            '0 0 0 rg',
            'BT',
            sprintf('/F1 %.3F Tf', $fontSize),
        ];

        foreach ($lines as $index => $line) {
            $lineBaseline = $baseline - ($index * $lineHeight);
            $commands[] = sprintf('1 0 0 1 %.4F %.4F Tm (%s) Tj', $x, $lineBaseline, $this->escapeText($line));
        }

        $commands[] = 'ET';

        if ((bool) $footer['is_underline']) {
            $commands[] = sprintf('%.3F w', max(0.4, $fontSize / 16));
            foreach ($lines as $index => $line) {
                $lineBaseline = $baseline - ($index * $lineHeight);
                $lineWidth = min((float) $placement['width'], $this->estimatedWidth($line, $fontSize));
                $commands[] = sprintf('%.4F %.4F m %.4F %.4F l S', $x, $lineBaseline - 1.2, $x + $lineWidth, $lineBaseline - 1.2);
            }
        }

        $commands[] = 'Q';

        return implode("\n", $commands);
    }

    /** @return list<string> */
    private function wrapText(string $text, float $maximumWidth, float $fontSize): array
    {
        $lines = [];
        $paragraphs = preg_split('/\R/u', trim($text)) ?: [];

        foreach ($paragraphs as $paragraph) {
            $words = preg_split('/\s+/u', trim($paragraph)) ?: [];
            $line = '';

            foreach ($words as $word) {
                $candidate = $line === '' ? $word : "{$line} {$word}";

                if ($line !== '' && $this->estimatedWidth($candidate, $fontSize) > $maximumWidth) {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $candidate;
                }
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    private function estimatedWidth(string $text, float $fontSize): float
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return strlen(is_string($encoded) ? $encoded : $text) * $fontSize * 0.55;
    }

    private function escapeText(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        $safe = is_string($encoded) ? $encoded : $text;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $safe);
    }

    private function baseFontName(string $fontKey, bool $bold, bool $italic): string
    {
        if (! array_key_exists($fontKey, $this->editorConfiguration->allowedFonts())) {
            throw new EsignInvariantViolationException('prepared_rendition_font_invalid');
        }

        return match ($fontKey) {
            'helvetica' => match (true) {
                $bold && $italic => 'Helvetica-BoldOblique',
                $bold => 'Helvetica-Bold',
                $italic => 'Helvetica-Oblique',
                default => 'Helvetica',
            },
            'times' => match (true) {
                $bold && $italic => 'Times-BoldItalic',
                $bold => 'Times-Bold',
                $italic => 'Times-Italic',
                default => 'Times-Roman',
            },
            'courier' => match (true) {
                $bold && $italic => 'Courier-BoldOblique',
                $bold => 'Courier-Bold',
                $italic => 'Courier-Oblique',
                default => 'Courier',
            },
            default => throw new EsignInvariantViolationException('prepared_rendition_font_invalid'),
        };
    }

    /** @param array<int, string> $objects */
    private function serializePdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number < $size; $number++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$number])."\n";
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function assertQpdfValid(string $path): void
    {
        $process = new Process([$this->qpdfBinary(), '--check', $path]);
        $process->setTimeout($this->timeoutSeconds());
        $process->run();

        if (! $process->isSuccessful()) {
            throw new EsignInvariantViolationException('prepared_rendition_qpdf_check_failed');
        }
    }

    private function qpdfBinary(): string
    {
        $binary = $this->config->get('esign.visible_editor.qpdf_binary');

        if (! is_string($binary) || trim($binary) === '') {
            throw new EsignInvariantViolationException('qpdf_binary_invalid');
        }

        return $binary;
    }

    private function timeoutSeconds(): int
    {
        $timeout = $this->config->get('esign.visible_editor.process_timeout_seconds', 120);

        return is_int($timeout) && $timeout >= 1 && $timeout <= 600 ? $timeout : 120;
    }
}
