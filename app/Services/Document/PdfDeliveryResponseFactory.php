<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailSourceState;
use App\Exceptions\Esign\EsignArtifactStorageException;
use App\Models\Esign\DocumentArtifact;
use App\Services\Esign\DocumentArtifactIntegrityService;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class PdfDeliveryResponseFactory
{
    private const LEGACY_STORAGE_DISKS = [
        'legacy_private_documents',
        'legacy_public_documents',
    ];

    public function __construct(
        private readonly FilesystemManager $filesystems,
        private readonly DocumentArtifactIntegrityService $artifactIntegrity,
    ) {}

    public function inline(ResolvedPdfDeliverySource $source): StreamedResponse
    {
        return $this->response($source, 'inline');
    }

    public function download(ResolvedPdfDeliverySource $source): StreamedResponse
    {
        return $this->response($source, 'attachment');
    }

    private function response(
        ResolvedPdfDeliverySource $source,
        string $disposition,
    ): StreamedResponse {
        if ($source->authorizationSubject instanceof DocumentArtifact) {
            $this->assertCanonicalSourceMatchesArtifact(
                $source,
                $source->authorizationSubject,
            );

            $response = $disposition === 'attachment'
                ? $this->artifactIntegrity->downloadResponse($source->authorizationSubject)
                : $this->artifactIntegrity->inlineResponse($source->authorizationSubject);

            $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

            return $response;
        }

        return $this->legacyResponse($source, $disposition);
    }

    private function assertCanonicalSourceMatchesArtifact(
        ResolvedPdfDeliverySource $source,
        DocumentArtifact $artifact,
    ): void {
        if ($source->sourceState !== DocumentDetailSourceState::Canonical
            || $source->documentArtifactId !== (int) $artifact->getKey()
            || $source->artifactPublicId !== (string) $artifact->public_id
            || $source->documentId !== (int) $artifact->document_id
            || $source->storageDisk !== (string) $artifact->storage_disk
            || $source->filePath !== (string) $artifact->file_path
            || $source->sizeBytes !== (int) $artifact->size_bytes
            || ! hash_equals($source->sha256, (string) $artifact->file_sha256)) {
            throw new EsignArtifactStorageException(
                'pdf_delivery_canonical_source_mismatch',
                $source->artifactPublicId,
            );
        }
    }

    private function legacyResponse(
        ResolvedPdfDeliverySource $source,
        string $disposition,
    ): StreamedResponse {
        if (! in_array($source->sourceState, [
            DocumentDetailSourceState::LegacyPrivatePending,
            DocumentDetailSourceState::LegacyPublicPending,
        ], true)
            || ! in_array($source->storageDisk, self::LEGACY_STORAGE_DISKS, true)) {
            throw new EsignArtifactStorageException('pdf_delivery_legacy_source_invalid');
        }

        try {
            $disk = $this->filesystems->disk($source->storageDisk);
            if (! $disk->exists($source->filePath)
                || $disk->size($source->filePath) !== $source->sizeBytes) {
                throw new EsignArtifactStorageException('pdf_delivery_file_missing');
            }

            $stream = $disk->readStream($source->filePath);
        } catch (EsignArtifactStorageException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new EsignArtifactStorageException('pdf_delivery_stream_open_failed');
        }

        if (! is_resource($stream)) {
            throw new EsignArtifactStorageException('pdf_delivery_stream_open_failed');
        }

        try {
            if (fread($stream, 5) !== '%PDF-' || rewind($stream) === false) {
                throw new EsignArtifactStorageException('pdf_delivery_integrity_mismatch');
            }
        } catch (Throwable $exception) {
            fclose($stream);

            if ($exception instanceof EsignArtifactStorageException) {
                throw $exception;
            }

            throw new EsignArtifactStorageException('pdf_delivery_stream_read_failed');
        }

        $filename = $source->safeFilename;

        return new StreamedResponse(
            callback: static function () use ($stream): void {
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 1024 * 1024);
                        if ($chunk === false) {
                            break;
                        }

                        echo $chunk;
                        flush();
                    }
                } finally {
                    fclose($stream);
                }
            },
            status: 200,
            headers: [
                'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    $disposition,
                    $filename,
                    $this->asciiFilename($filename),
                ),
                'Content-Length' => (string) $source->sizeBytes,
                'Content-Type' => 'application/pdf',
                'Cross-Origin-Resource-Policy' => 'same-origin',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function asciiFilename(string $filename): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii($filename));

        return is_string($fallback) && $fallback !== '' ? $fallback : 'dokumen.pdf';
    }
}
