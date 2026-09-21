<?php

namespace App\Services\Esign;

use App\Data\Esign\DocumentArtifactIntegrityData;
use App\Exceptions\Esign\EsignArtifactStorageException;
use App\Models\Esign\DocumentArtifact;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentArtifactIntegrityService
{
    public function __construct(
        private FilesystemManager $filesystems,
        private ConfigRepository $config,
    ) {}

    public function assertReadablePdf(DocumentArtifact $artifact): DocumentArtifactIntegrityData
    {
        return $this->readAndInspect($artifact, false)['inspection'];
    }

    public function readVerifiedPdfContents(DocumentArtifact $artifact): string
    {
        return $this->readAndInspect($artifact, true)['contents'];
    }

    public function inlineResponse(DocumentArtifact $artifact): StreamedResponse
    {
        $this->assertReadablePdf($artifact);
        $filename = $artifact->original_name ?: $artifact->stored_name ?: "{$artifact->public_id}.pdf";

        return $this->filesystems
            ->disk((string) $artifact->storage_disk)
            ->response(
                (string) $artifact->file_path,
                basename((string) $filename),
                [
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'Content-Type' => 'application/pdf',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                ],
                'inline',
            );
    }

    /** @return array{inspection: DocumentArtifactIntegrityData, contents: string} */
    private function readAndInspect(DocumentArtifact $artifact, bool $captureContents): array
    {
        $publicId = (string) $artifact->public_id;

        if ($artifact->file_path === null
            || $artifact->storage_disk === null
            || (int) $artifact->size_bytes <= 0
            || (int) $artifact->size_bytes > $this->maximumFileSizeBytes()
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
                $sha256Hash = hash_init('sha256');
                $md5Hash = hash_init('md5');
                $sizeBytes = 0;
                $header = '';
                $contents = '';

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
                    hash_update($sha256Hash, $chunk);
                    hash_update($md5Hash, $chunk);

                    if ($captureContents) {
                        $contents .= $chunk;
                    }
                }

                $sha256 = hash_final($sha256Hash);
                $md5 = hash_final($md5Hash);
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

        return [
            'inspection' => new DocumentArtifactIntegrityData(
                sizeBytes: $sizeBytes,
                sha256: $sha256,
                md5: $md5,
            ),
            'contents' => $contents,
        ];
    }

    private function maximumFileSizeBytes(): int
    {
        $megabytes = $this->config->get('esign.processing.max_file_size_mb', 50);

        if (! is_int($megabytes) || $megabytes < 1 || $megabytes > 200) {
            throw new EsignArtifactStorageException('artifact_size_limit_invalid');
        }

        return $megabytes * 1024 * 1024;
    }
}
