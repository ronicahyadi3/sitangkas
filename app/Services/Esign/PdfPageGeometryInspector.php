<?php

namespace App\Services\Esign;

use App\Data\Esign\PdfPageGeometryData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class PdfPageGeometryInspector
{
    public function __construct(private ConfigRepository $config) {}

    /** @return list<PdfPageGeometryData> */
    public function inspect(string $pdfContents): array
    {
        if (! str_starts_with($pdfContents, '%PDF-')) {
            throw new EsignInvariantViolationException('pdf_geometry_source_invalid');
        }

        $temporaryPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sitangkas-pdfinfo-'.Str::uuid().'.pdf';

        try {
            if (file_put_contents($temporaryPath, $pdfContents, LOCK_EX) !== strlen($pdfContents)) {
                throw new EsignInvariantViolationException('pdf_geometry_temporary_write_failed');
            }

            $process = new Process([
                $this->binary(),
                '-box',
                '-f',
                '1',
                '-l',
                (string) $this->maximumPages(),
                $temporaryPath,
            ]);
            $process->setTimeout($this->timeoutSeconds());
            $process->run();

            if (! $process->isSuccessful()) {
                throw new EsignInvariantViolationException('pdf_geometry_inspection_failed');
            }

            return $this->parse($process->getOutput());
        } catch (EsignInvariantViolationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new EsignInvariantViolationException('pdf_geometry_inspection_failed');
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /** @return list<PdfPageGeometryData> */
    private function parse(string $output): array
    {
        preg_match('/^Pages:\s+(\d+)\s*$/mi', $output, $pageCountMatch);
        $pageCount = isset($pageCountMatch[1]) ? (int) $pageCountMatch[1] : 0;

        if ($pageCount < 1 || $pageCount > $this->maximumPages()) {
            throw new EsignInvariantViolationException('pdf_page_count_invalid');
        }

        preg_match_all(
            '/^Page\s+(\d+)\s+size:\s+([0-9.]+)\s+x\s+([0-9.]+)\s+pts.*$/mi',
            $output,
            $sizes,
            PREG_SET_ORDER,
        );
        preg_match_all(
            '/^Page\s+(\d+)\s+rot:\s+(-?\d+)\s*$/mi',
            $output,
            $rotations,
            PREG_SET_ORDER,
        );

        $rotationByPage = [];
        foreach ($rotations as $rotation) {
            $rotationByPage[(int) $rotation[1]] = (($rotation[2] % 360) + 360) % 360;
        }

        $pages = [];
        foreach ($sizes as $size) {
            $pageNumber = (int) $size[1];
            $pages[$pageNumber] = new PdfPageGeometryData(
                pageNumber: $pageNumber,
                width: (float) $size[2],
                height: (float) $size[3],
                rotation: $rotationByPage[$pageNumber] ?? 0,
            );
        }

        ksort($pages);

        if (count($pages) !== $pageCount || array_keys($pages) !== range(1, $pageCount)) {
            throw new EsignInvariantViolationException('pdf_page_geometry_incomplete');
        }

        return array_values($pages);
    }

    private function binary(): string
    {
        $binary = $this->config->get('esign.visible_editor.pdfinfo_binary');

        if (! is_string($binary) || trim($binary) === '') {
            throw new EsignInvariantViolationException('pdfinfo_binary_invalid');
        }

        return $binary;
    }

    private function maximumPages(): int
    {
        $maximum = $this->config->get('esign.visible_editor.max_pages', 500);

        if (! is_int($maximum) || $maximum < 1 || $maximum > 2000) {
            throw new EsignInvariantViolationException('pdf_editor_page_limit_invalid');
        }

        return $maximum;
    }

    private function timeoutSeconds(): int
    {
        $timeout = $this->config->get('esign.visible_editor.process_timeout_seconds', 120);

        return is_int($timeout) && $timeout >= 1 && $timeout <= 600 ? $timeout : 120;
    }
}
