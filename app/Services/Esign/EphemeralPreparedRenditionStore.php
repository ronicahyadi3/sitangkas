<?php

namespace App\Services\Esign;

use App\Data\Esign\PreparedSigningRenditionData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EphemeralPreparedRenditionStore
{
    private const KEY_PREFIX = 'esign:prepared-rendition:';

    public function __construct(
        private CacheFactory $cache,
        private ConfigRepository $config,
        private Encrypter $encrypter,
        private FilesystemManager $filesystems,
    ) {}

    /** @param array<string, string> $operationImages */
    public function put(
        PreparedSigningRenditionData $rendition,
        string $pdfContents,
        array $operationImages,
    ): void {
        $this->assertConfiguredStorage($rendition);

        if (! str_starts_with($pdfContents, '%PDF-')
            || strlen($pdfContents) !== $rendition->sizeBytes
            || ! hash_equals($rendition->sha256, hash('sha256', $pdfContents))) {
            throw new EsignInvariantViolationException('prepared_rendition_integrity_invalid');
        }

        if (count($operationImages) !== count($rendition->signatureOperations)) {
            throw new EsignInvariantViolationException('prepared_rendition_qr_count_invalid');
        }

        foreach ($rendition->signatureOperations as $operation) {
            $path = $operation['visual_file_path'] ?? null;
            $image = is_string($path) ? ($operationImages[$path] ?? null) : null;

            if (! is_string($path)
                || ! is_string($image)
                || ($operation['visual_storage_disk'] ?? null) !== $rendition->storageDisk
                || ! str_starts_with($image, "\x89PNG\r\n\x1a\n")
                || strlen($image) !== ($operation['qr_size_bytes'] ?? null)
                || ! hash_equals((string) ($operation['qr_sha256'] ?? ''), hash('sha256', $image))) {
                throw new EsignInvariantViolationException('prepared_rendition_qr_integrity_invalid');
            }
        }

        $previous = $this->getCurrent($rendition->sessionId, $rendition->actorUserId);
        $disk = $this->filesystems->disk($rendition->storageDisk);
        $writtenPaths = [];

        try {
            if (! $disk->put($rendition->filePath, $pdfContents, ['visibility' => 'private'])) {
                throw new EsignInvariantViolationException('prepared_rendition_storage_write_failed');
            }
            $writtenPaths[] = $rendition->filePath;

            foreach ($operationImages as $path => $image) {
                if (! $disk->put($path, $image, ['visibility' => 'private'])) {
                    throw new EsignInvariantViolationException('prepared_rendition_qr_storage_write_failed');
                }

                $writtenPaths[] = $path;
            }

            $encryptedPayload = $this->encrypter->encrypt(
                json_encode($rendition->toArray(), JSON_THROW_ON_ERROR),
                false,
            );

            if (! $this->store()->put(
                $this->key($rendition->sessionId),
                $encryptedPayload,
                $rendition->expiresAt,
            )) {
                throw new EsignInvariantViolationException('prepared_rendition_cache_write_failed');
            }
        } catch (\Throwable $exception) {
            $disk->delete($writtenPaths);
            throw $exception;
        }

        if ($previous instanceof PreparedSigningRenditionData) {
            $this->filesystems->disk($previous->storageDisk)->delete($this->paths($previous));
        }
    }

    public function get(string $sessionId, string $revision, int $actorUserId): ?PreparedSigningRenditionData
    {
        $rendition = $this->getCurrent($sessionId, $actorUserId);

        if (! $rendition instanceof PreparedSigningRenditionData
            || ! hash_equals($rendition->revision, $revision)) {
            return null;
        }

        return $rendition;
    }

    public function getCurrent(string $sessionId, int $actorUserId): ?PreparedSigningRenditionData
    {
        if (! Str::isUuid($sessionId)) {
            return null;
        }

        $encryptedPayload = $this->store()->get($this->key($sessionId));
        if (! is_string($encryptedPayload) || $encryptedPayload === '') {
            return null;
        }

        try {
            $payload = json_decode(
                $this->encrypter->decrypt($encryptedPayload, false),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            if (! is_array($payload)) {
                throw new EsignInvariantViolationException('prepared_rendition_payload_invalid');
            }

            $rendition = PreparedSigningRenditionData::fromArray($payload);
        } catch (\Throwable $exception) {
            $this->store()->forget($this->key($sessionId));

            throw new EsignInvariantViolationException('prepared_rendition_payload_invalid');
        }

        if (! hash_equals($sessionId, $rendition->sessionId)
            || $rendition->actorUserId !== $actorUserId) {
            return null;
        }

        $this->assertConfiguredStorage($rendition);

        return $rendition;
    }

    public function inlineResponse(PreparedSigningRenditionData $rendition): StreamedResponse
    {
        $this->assertStoredIntegrity($rendition);

        return $this->filesystems->disk($rendition->storageDisk)->response(
            $rendition->filePath,
            "prepared-{$rendition->revision}.pdf",
            [
                'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
                'Content-Type' => 'application/pdf',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function readVerifiedPdfContents(PreparedSigningRenditionData $rendition): string
    {
        return $this->verifiedPdfContents($rendition);
    }

    /** @return array<int, string> */
    public function readVerifiedQrContents(PreparedSigningRenditionData $rendition): array
    {
        $this->assertConfiguredStorage($rendition);
        $contentsByOperation = [];

        foreach ($rendition->signatureOperations as $operation) {
            $operationIndex = $operation['operation_index'] ?? null;
            $path = $operation['visual_file_path'] ?? null;
            $expectedSize = $operation['qr_size_bytes'] ?? null;
            $expectedSha256 = $operation['qr_sha256'] ?? null;

            if (! is_int($operationIndex)
                || ! is_string($path)
                || ! is_int($expectedSize)
                || ! is_string($expectedSha256)) {
                throw new EsignInvariantViolationException('prepared_rendition_qr_metadata_invalid');
            }

            try {
                $contents = $this->filesystems->disk($rendition->storageDisk)->get($path);
            } catch (\Throwable) {
                throw new EsignInvariantViolationException('prepared_rendition_qr_missing');
            }

            if (! is_string($contents)
                || ! str_starts_with($contents, "\x89PNG\r\n\x1a\n")
                || strlen($contents) !== $expectedSize
                || ! hash_equals($expectedSha256, hash('sha256', $contents))) {
                throw new EsignInvariantViolationException('prepared_rendition_qr_integrity_mismatch');
            }

            $contentsByOperation[$operationIndex] = $contents;
        }

        ksort($contentsByOperation);

        if (array_keys($contentsByOperation) !== range(0, count($contentsByOperation) - 1)) {
            throw new EsignInvariantViolationException('prepared_rendition_qr_order_invalid');
        }

        return $contentsByOperation;
    }

    public function qrResponse(
        PreparedSigningRenditionData $rendition,
        int $operationIndex,
    ): StreamedResponse {
        $operation = collect($rendition->signatureOperations)
            ->firstWhere('operation_index', $operationIndex);

        if (! is_array($operation)
            || ! is_string($operation['visual_file_path'] ?? null)
            || ! is_string($operation['qr_sha256'] ?? null)
            || ! is_int($operation['qr_size_bytes'] ?? null)) {
            throw new EsignInvariantViolationException('prepared_rendition_qr_metadata_invalid');
        }

        $path = $operation['visual_file_path'];
        $disk = $this->filesystems->disk($rendition->storageDisk);

        try {
            $contents = $disk->get($path);
        } catch (\Throwable $exception) {
            throw new EsignInvariantViolationException('prepared_rendition_qr_missing');
        }

        if (! is_string($contents)
            || ! str_starts_with($contents, "\x89PNG\r\n\x1a\n")
            || strlen($contents) !== $operation['qr_size_bytes']
            || ! hash_equals($operation['qr_sha256'], hash('sha256', $contents))) {
            throw new EsignInvariantViolationException('prepared_rendition_qr_integrity_mismatch');
        }

        return $disk->response(
            $path,
            sprintf('signature-qr-%02d.png', $operationIndex),
            [
                'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
                'Content-Type' => 'image/png',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function forget(string $sessionId, int $actorUserId): void
    {
        try {
            $rendition = $this->getCurrent($sessionId, $actorUserId);
        } catch (EsignInvariantViolationException $exception) {
            return;
        }

        if (! $rendition instanceof PreparedSigningRenditionData) {
            return;
        }

        $this->store()->forget($this->key($sessionId));
        $this->filesystems->disk($rendition->storageDisk)->delete($this->paths($rendition));
    }

    public function lock(string $sessionId): Lock
    {
        return $this->store()->lock(self::KEY_PREFIX.'lock:'.$sessionId, 30);
    }

    private function assertStoredIntegrity(PreparedSigningRenditionData $rendition): void
    {
        $this->verifiedPdfContents($rendition);
    }

    private function verifiedPdfContents(PreparedSigningRenditionData $rendition): string
    {
        $this->assertConfiguredStorage($rendition);

        try {
            $contents = $this->filesystems->disk($rendition->storageDisk)->get($rendition->filePath);
        } catch (\Throwable $exception) {
            throw new EsignInvariantViolationException('prepared_rendition_file_missing');
        }

        if (! is_string($contents)
            || ! str_starts_with($contents, '%PDF-')
            || strlen($contents) !== $rendition->sizeBytes
            || ! hash_equals($rendition->sha256, hash('sha256', $contents))) {
            throw new EsignInvariantViolationException('prepared_rendition_integrity_mismatch');
        }

        return $contents;
    }

    private function assertConfiguredStorage(PreparedSigningRenditionData $rendition): void
    {
        $configuredDisk = $this->config->get('esign.visible_editor.prepared_disk');
        $configuredRoot = $this->config->get('esign.visible_editor.prepared_root');

        if (! is_string($configuredDisk)
            || $configuredDisk === ''
            || ! is_string($configuredRoot)
            || $configuredRoot === ''
            || ! hash_equals($configuredDisk, $rendition->storageDisk)) {
            throw new EsignInvariantViolationException('prepared_rendition_storage_config_mismatch');
        }

        $prefix = trim($configuredRoot, '/').'/';
        foreach ($this->paths($rendition) as $path) {
            if (! str_starts_with($path, $prefix)
                || str_contains($path, '..')
                || str_contains($path, '\\')
                || str_contains($path, ':')) {
                throw new EsignInvariantViolationException('prepared_rendition_storage_path_invalid');
            }
        }
    }

    private function store(): CacheRepository
    {
        $store = $this->config->get('esign.signing_session.cache_store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }

    private function key(string $sessionId): string
    {
        return self::KEY_PREFIX.$sessionId;
    }

    /** @return list<string> */
    private function paths(PreparedSigningRenditionData $rendition): array
    {
        $paths = [$rendition->filePath];

        foreach ($rendition->signatureOperations as $operation) {
            if (is_string($operation['visual_file_path'] ?? null)) {
                $paths[] = $operation['visual_file_path'];
            }
        }

        return array_values(array_unique($paths));
    }
}
