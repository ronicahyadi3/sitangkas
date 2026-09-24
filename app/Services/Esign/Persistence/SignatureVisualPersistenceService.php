<?php

namespace App\Services\Esign\Persistence;

use App\Data\Esign\PreparedSigningRenditionData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Esign\EsignAttemptSignatureProperty;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;

final class SignatureVisualPersistenceService
{
    public function __construct(
        private FilesystemManager $filesystems,
        private ConfigRepository $config,
    ) {}

    /**
     * @param  array<int, string>  $qrContents
     * @return array<int, array{storage_disk: string, file_path: string, size_bytes: int, sha256: string}>
     */
    public function materialize(
        PreparedSigningRenditionData $rendition,
        array $qrContents,
    ): array {
        $diskName = $this->diskName();
        $disk = $this->filesystems->disk($diskName);
        $assets = [];
        $writtenAssets = [];

        try {
            foreach ($rendition->signatureOperations as $operation) {
                $operationIndex = $operation['operation_index'];
                $publicId = $operation['verification_public_id'];
                $contents = $qrContents[$operationIndex] ?? null;
                $expectedSize = $operation['qr_size_bytes'];
                $expectedSha256 = Str::lower($operation['qr_sha256']);

                if (! is_string($contents)
                    || ! Str::isUuid($publicId)
                    || ! str_starts_with($contents, "\x89PNG\r\n\x1a\n")
                    || strlen($contents) !== $expectedSize
                    || ! hash_equals($expectedSha256, hash('sha256', $contents))) {
                    throw new EsignInvariantViolationException('signature_visual_integrity_invalid');
                }

                $path = $this->path($rendition, $publicId);

                if ($disk->exists($path)) {
                    $storedContents = $disk->get($path);

                    if (! is_string($storedContents)
                        || strlen($storedContents) !== $expectedSize
                        || ! hash_equals($expectedSha256, hash('sha256', $storedContents))) {
                        throw new EsignInvariantViolationException('signature_visual_path_conflict');
                    }
                } elseif (! $disk->put($path, $contents, ['visibility' => 'private'])) {
                    throw new EsignInvariantViolationException('signature_visual_storage_write_failed');
                } else {
                    $writtenAssets[$operationIndex] = [
                        'storage_disk' => $diskName,
                        'file_path' => $path,
                        'size_bytes' => $expectedSize,
                        'sha256' => $expectedSha256,
                    ];
                }

                $assets[$operationIndex] = [
                    'storage_disk' => $diskName,
                    'file_path' => $path,
                    'size_bytes' => $expectedSize,
                    'sha256' => $expectedSha256,
                ];
            }
        } catch (\Throwable $exception) {
            $this->discardKnownUnreferenced($writtenAssets);

            throw $exception;
        }

        ksort($assets);

        if (array_keys($assets) !== range(0, count($assets) - 1)) {
            $this->discardKnownUnreferenced($writtenAssets);

            throw new EsignInvariantViolationException('signature_visual_order_invalid');
        }

        return $assets;
    }

    /**
     * @param  array<int, array{storage_disk: string, file_path: string, size_bytes: int, sha256: string}>  $assets
     */
    public function discardUnpersisted(array $assets): void
    {
        foreach ($assets as $asset) {
            if (EsignAttemptSignatureProperty::query()
                ->where('visual_storage_disk', $asset['storage_disk'])
                ->where('visual_file_path', $asset['file_path'])
                ->exists()) {
                continue;
            }

            $disk = $this->filesystems->disk($asset['storage_disk']);

            if (! $disk->exists($asset['file_path'])) {
                continue;
            }

            $contents = $disk->get($asset['file_path']);
            if (is_string($contents)
                && strlen($contents) === $asset['size_bytes']
                && hash_equals($asset['sha256'], hash('sha256', $contents))) {
                $disk->delete($asset['file_path']);
            }
        }
    }

    public function readVerifiedContents(EsignAttemptSignatureProperty $property): string
    {
        $safeProperties = $property->safe_provider_properties;
        $expectedSize = is_array($safeProperties)
            ? ($safeProperties['visual_size_bytes'] ?? null)
            : null;
        $diskName = $property->visual_storage_disk;
        $path = $property->visual_file_path;
        $expectedSha256 = $property->visual_sha256;

        if (! is_int($expectedSize)
            || $expectedSize < 1
            || ! is_string($diskName)
            || ! hash_equals($this->diskName(), $diskName)
            || ! is_string($path)
            || ! str_starts_with($path, $this->root().'/')
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || str_contains($path, ':')
            || ! is_string($expectedSha256)
            || preg_match('/\A[a-f0-9]{64}\z/i', $expectedSha256) !== 1) {
            throw new EsignInvariantViolationException('signature_visual_metadata_invalid');
        }

        try {
            $contents = $this->filesystems->disk($diskName)->get($path);
        } catch (\Throwable) {
            throw new EsignInvariantViolationException('signature_visual_file_missing');
        }

        if (! is_string($contents)
            || ! str_starts_with($contents, "\x89PNG\r\n\x1a\n")
            || strlen($contents) !== $expectedSize
            || ! hash_equals(Str::lower($expectedSha256), hash('sha256', $contents))) {
            throw new EsignInvariantViolationException('signature_visual_integrity_mismatch');
        }

        return $contents;
    }

    private function diskName(): string
    {
        $disk = $this->config->get('esign.artifacts.disk');

        if (! is_string($disk) || $disk === '') {
            throw new EsignInvariantViolationException('signature_visual_disk_invalid');
        }

        return $disk;
    }

    private function root(): string
    {
        $root = $this->config->get('esign.artifacts.root');

        if (! is_string($root)
            || preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/', trim($root, '/')) !== 1) {
            throw new EsignInvariantViolationException('signature_visual_root_invalid');
        }

        return trim($root, '/');
    }

    /**
     * @param  array<int, array{storage_disk: string, file_path: string, size_bytes: int, sha256: string}>  $assets
     */
    private function discardKnownUnreferenced(array $assets): void
    {
        foreach ($assets as $asset) {
            $disk = $this->filesystems->disk($asset['storage_disk']);

            if ($disk->exists($asset['file_path'])) {
                $disk->delete($asset['file_path']);
            }
        }
    }

    private function path(PreparedSigningRenditionData $rendition, string $publicId): string
    {
        return sprintf(
            '%s/%s/%s/signature-visuals/%s/%s.png',
            $this->root(),
            $rendition->createdAt->format('Y'),
            $rendition->createdAt->format('m'),
            $rendition->revision,
            Str::lower($publicId),
        );
    }
}
