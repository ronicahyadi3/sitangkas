<?php

namespace App\Services\Esign;

use App\Exceptions\Esign\EsignArtifactStorageException;
use App\Models\Esign\DocumentArtifact;
use Illuminate\Filesystem\FilesystemManager;

final class DocumentArtifactIntegrityService
{
    public function __construct(private FilesystemManager $filesystems) {}

    public function assertReadablePdf(DocumentArtifact $artifact): void
    {
        $publicId = (string) $artifact->public_id;

        if ($artifact->file_path === null
            || $artifact->storage_disk === null
            || preg_match('/\A[a-f0-9]{64}\z/i', (string) $artifact->file_sha256) !== 1
            || ! hash_equals(
                (string) $artifact->storage_path_sha256,
                hash('sha256', (string) $artifact->file_path),
            )) {
            throw new EsignArtifactStorageException('artifact_metadata_invalid', $publicId);
        }

        try {
            $disk = $this->filesystems->disk((string) $artifact->storage_disk);

            if (! $disk->exists((string) $artifact->file_path)) {
                throw new EsignArtifactStorageException('artifact_file_missing', $publicId);
            }

            $stream = $disk->readStream((string) $artifact->file_path);

            if (! is_resource($stream)) {
                throw new EsignArtifactStorageException('artifact_stream_open_failed', $publicId);
            }

            try {
                $hash = hash_init('sha256');
                $sizeBytes = 0;
                $header = '';

                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);

                    if ($chunk === false) {
                        throw new EsignArtifactStorageException('artifact_stream_read_failed', $publicId);
                    }

                    if ($chunk === '') {
                        continue;
                    }

                    if (strlen($header) < 5) {
                        $header .= substr($chunk, 0, 5 - strlen($header));
                    }

                    $sizeBytes += strlen($chunk);
                    hash_update($hash, $chunk);
                }

                $sha256 = hash_final($hash);
            } finally {
                fclose($stream);
            }
        } catch (EsignArtifactStorageException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new EsignArtifactStorageException('artifact_read_failed', $publicId);
        }

        if ($header !== '%PDF-'
            || $sizeBytes <= 0
            || $sizeBytes !== (int) $artifact->size_bytes
            || ! hash_equals((string) $artifact->file_sha256, $sha256)) {
            throw new EsignArtifactStorageException('artifact_integrity_mismatch', $publicId);
        }
    }
}
