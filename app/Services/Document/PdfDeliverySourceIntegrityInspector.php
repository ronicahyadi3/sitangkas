<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Data\Document\ResolvedPdfDeliverySource;
use App\Exceptions\Esign\EsignArtifactStorageException;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

final class PdfDeliverySourceIntegrityInspector
{
    public function __construct(
        private readonly FilesystemManager $filesystems,
    ) {}

    public function assertMatches(ResolvedPdfDeliverySource $source): void
    {
        $stream = null;

        try {
            $disk = $this->filesystems->disk($source->storageDisk);

            if (! $disk->exists($source->filePath)
                || $disk->size($source->filePath) !== $source->sizeBytes) {
                throw new EsignArtifactStorageException('pdf_delivery_pilot_source_missing');
            }

            $stream = $disk->readStream($source->filePath);
            if (! is_resource($stream) || fread($stream, 5) !== '%PDF-' || rewind($stream) === false) {
                throw new EsignArtifactStorageException('pdf_delivery_pilot_source_invalid');
            }

            $hashContext = hash_init('sha256');
            $hashedBytes = hash_update_stream($hashContext, $stream);
            $actualSha256 = hash_final($hashContext);

            if ($hashedBytes !== $source->sizeBytes
                || ! hash_equals($source->sha256, $actualSha256)) {
                throw new EsignArtifactStorageException('pdf_delivery_pilot_integrity_mismatch');
            }
        } catch (EsignArtifactStorageException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new EsignArtifactStorageException('pdf_delivery_pilot_source_unreadable');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
