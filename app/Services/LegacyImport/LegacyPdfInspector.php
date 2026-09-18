<?php

namespace App\Services\LegacyImport;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

final class LegacyPdfInspector
{
    private ?string $resolvedBinary = null;

    public function __construct(
        private Filesystem $filesystem,
    ) {}

    public function binary(): string
    {
        if ($this->resolvedBinary !== null) {
            return $this->resolvedBinary;
        }

        $configuredBinary = trim((string) config(
            'legacy_import.position_documents.pdfinfo_binary',
            'pdfinfo',
        ));
        $binary = (new ExecutableFinder)->find($configuredBinary);

        if ($binary === null) {
            throw new RuntimeException(
                'Executable pdfinfo tidak ditemukan. Atur LEGACY_IMPORT_PDFINFO_BINARY ke lokasi Poppler pdfinfo.',
            );
        }

        return $this->resolvedBinary = $binary;
    }

    /**
     * @return array{is_valid: bool, mime_type: ?string, has_pdf_header: bool, parser_error: ?string}
     */
    public function inspect(string $path): array
    {
        $mimeType = $this->filesystem->mimeType($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [
                'is_valid' => false,
                'mime_type' => is_string($mimeType) ? $mimeType : null,
                'has_pdf_header' => false,
                'parser_error' => 'File tidak dapat dibaca.',
            ];
        }

        try {
            $hasPdfHeader = fread($handle, 5) === '%PDF-';
        } finally {
            fclose($handle);
        }

        $process = new Process([$this->binary(), $path]);
        $process->setTimeout((float) config(
            'legacy_import.position_documents.pdfinfo_timeout_seconds',
            15,
        ));
        try {
            $process->run();
            $parserSuccessful = $process->isSuccessful();
            $parserError = $parserSuccessful
                ? null
                : $this->sanitizedParserError($process->getErrorOutput(), $path);
        } catch (Throwable $exception) {
            $parserSuccessful = false;
            $parserError = $this->sanitizedParserError($exception->getMessage(), $path);
        }

        return [
            'is_valid' => $mimeType === 'application/pdf'
                && $hasPdfHeader
                && $parserSuccessful,
            'mime_type' => is_string($mimeType) ? $mimeType : null,
            'has_pdf_header' => $hasPdfHeader,
            'parser_error' => $parserError,
        ];
    }

    private function sanitizedParserError(string $error, string $path): string
    {
        $error = str_replace([
            $path,
            str_replace('\\', '/', $path),
        ], '[file]', $error);
        $error = preg_replace('/\s+/', ' ', trim($error)) ?? '';

        if ($error === '') {
            return 'pdfinfo gagal tanpa detail error.';
        }

        return mb_substr($error, 0, 500);
    }
}
