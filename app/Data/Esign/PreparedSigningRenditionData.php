<?php

namespace App\Data\Esign;

use App\Exceptions\Esign\EsignInvariantViolationException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class PreparedSigningRenditionData
{
    /**
     * @param  list<array<string, bool|float|int|string>>  $signatureOperations
     * @param  array<string, mixed>|null  $footer
     */
    public function __construct(
        public string $sessionId,
        public string $revision,
        public int $actorUserId,
        public int $sourceArtifactId,
        public string $sourceArtifactSha256,
        public string $storageDisk,
        public string $filePath,
        public int $sizeBytes,
        public string $sha256,
        public string $requestFingerprint,
        public string $rendererVersion,
        public array $signatureOperations,
        public ?array $footer,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $expiresAt,
    ) {
        if (! Str::isUuid($sessionId)
            || ! Str::isUuid($revision)
            || $actorUserId < 1
            || $sourceArtifactId < 1
            || $storageDisk === ''
            || $filePath === ''
            || ! self::validRelativePath($filePath)
            || $sizeBytes < 1
            || $rendererVersion === ''
            || $signatureOperations === []
            || $createdAt->isAfter($expiresAt)
            || $expiresAt->isPast()
            || preg_match('/\A[a-f0-9]{64}\z/i', $sourceArtifactSha256) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/i', $sha256) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/i', $requestFingerprint) !== 1
            || ! self::validOperations($signatureOperations)) {
            throw new EsignInvariantViolationException('prepared_rendition_payload_invalid');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'revision' => $this->revision,
            'actor_user_id' => $this->actorUserId,
            'source_artifact_id' => $this->sourceArtifactId,
            'source_artifact_sha256' => $this->sourceArtifactSha256,
            'storage_disk' => $this->storageDisk,
            'file_path' => $this->filePath,
            'size_bytes' => $this->sizeBytes,
            'sha256' => $this->sha256,
            'request_fingerprint' => $this->requestFingerprint,
            'renderer_version' => $this->rendererVersion,
            'signature_operations' => $this->signatureOperations,
            'footer' => $this->footer,
            'created_at' => $this->createdAt->toIso8601String(),
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function toClientArray(): array
    {
        return [
            'revision' => $this->revision,
            'sha256' => $this->sha256,
            'request_fingerprint' => $this->requestFingerprint,
            'renderer_version' => $this->rendererVersion,
            'signature_count' => count($this->signatureOperations),
            'signature_operations' => array_map(
                static fn (array $operation): array => [
                    'client_id' => $operation['client_id'],
                    'operation_index' => $operation['operation_index'],
                    'page' => $operation['page'],
                    'page_width' => $operation['page_width'],
                    'page_height' => $operation['page_height'],
                    'page_rotation' => $operation['page_rotation'],
                    'origin_x' => $operation['origin_x'],
                    'origin_y' => $operation['origin_y'],
                    'width' => $operation['width'],
                    'height' => $operation['height'],
                    'verification_public_id' => $operation['verification_public_id'],
                    'verification_url' => $operation['verification_url'],
                    'qr_sha256' => $operation['qr_sha256'],
                    'qr_profile_version' => $operation['qr_profile_version'] ?? 'legacy-unbranded-v1',
                ],
                $this->signatureOperations,
            ),
            'footer' => $this->footer,
            'expires_at' => $this->expiresAt->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        try {
            $operations = $value['signature_operations'] ?? null;
            $footer = $value['footer'] ?? null;

            if (! is_array($operations) || ($footer !== null && ! is_array($footer))) {
                throw new EsignInvariantViolationException('prepared_rendition_payload_invalid');
            }

            return new self(
                sessionId: self::string($value, 'session_id'),
                revision: self::string($value, 'revision'),
                actorUserId: self::integer($value, 'actor_user_id'),
                sourceArtifactId: self::integer($value, 'source_artifact_id'),
                sourceArtifactSha256: self::string($value, 'source_artifact_sha256'),
                storageDisk: self::string($value, 'storage_disk'),
                filePath: self::string($value, 'file_path'),
                sizeBytes: self::integer($value, 'size_bytes'),
                sha256: self::string($value, 'sha256'),
                requestFingerprint: self::string($value, 'request_fingerprint'),
                rendererVersion: self::string($value, 'renderer_version'),
                signatureOperations: array_values($operations),
                footer: $footer,
                createdAt: CarbonImmutable::parse(self::string($value, 'created_at')),
                expiresAt: CarbonImmutable::parse(self::string($value, 'expires_at')),
            );
        } catch (\Throwable $exception) {
            throw new EsignInvariantViolationException('prepared_rendition_payload_invalid');
        }
    }

    /** @param array<string, mixed> $value */
    private static function string(array $value, string $key): string
    {
        if (! is_string($value[$key] ?? null) || $value[$key] === '') {
            throw new EsignInvariantViolationException('prepared_rendition_payload_invalid');
        }

        return $value[$key];
    }

    /** @param array<string, mixed> $value */
    private static function integer(array $value, string $key): int
    {
        if (! is_int($value[$key] ?? null)) {
            throw new EsignInvariantViolationException('prepared_rendition_payload_invalid');
        }

        return $value[$key];
    }

    /** @param array<mixed> $operations */
    private static function validOperations(array $operations): bool
    {
        foreach (array_values($operations) as $index => $operation) {
            if (! is_array($operation)
                || ($operation['operation_index'] ?? null) !== $index
                || ! is_string($operation['client_id'] ?? null)
                || ! Str::isUuid($operation['client_id'])
                || ! is_int($operation['page'] ?? null)
                || $operation['page'] < 1
                || ! is_numeric($operation['origin_x'] ?? null)
                || ! is_numeric($operation['origin_y'] ?? null)
                || ! is_numeric($operation['width'] ?? null)
                || ! is_numeric($operation['height'] ?? null)
                || ! is_numeric($operation['page_width'] ?? null)
                || ! is_numeric($operation['page_height'] ?? null)
                || ! is_finite((float) $operation['origin_x'])
                || ! is_finite((float) $operation['origin_y'])
                || ! is_finite((float) $operation['width'])
                || ! is_finite((float) $operation['height'])
                || ! is_finite((float) $operation['page_width'])
                || ! is_finite((float) $operation['page_height'])
                || (float) $operation['origin_x'] < 0
                || (float) $operation['origin_y'] < 0
                || (float) $operation['width'] <= 0
                || (float) $operation['height'] <= 0
                || (float) $operation['page_width'] <= 0
                || (float) $operation['page_height'] <= 0
                || ! is_int($operation['page_rotation'] ?? null)
                || ! in_array($operation['page_rotation'], [0, 90, 180, 270], true)
                || ! is_string($operation['verification_public_id'] ?? null)
                || ! Str::isUuid($operation['verification_public_id'])
                || ! is_string($operation['verification_url'] ?? null)
                || ! str_starts_with($operation['verification_url'], 'https://')
                || ! is_string($operation['visual_storage_disk'] ?? null)
                || $operation['visual_storage_disk'] === ''
                || ! is_string($operation['visual_file_path'] ?? null)
                || ! self::validRelativePath($operation['visual_file_path'])
                || ! is_int($operation['qr_size_bytes'] ?? null)
                || $operation['qr_size_bytes'] < 1
                || ! is_string($operation['qr_sha256'] ?? null)
                || preg_match('/\A[a-f0-9]{64}\z/i', $operation['qr_sha256']) !== 1
                || ! self::validQrProfile($operation)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $operation */
    private static function validQrProfile(array $operation): bool
    {
        $hasVersion = array_key_exists('qr_profile_version', $operation);
        $hasLogoSha256 = array_key_exists('qr_logo_sha256', $operation);

        if (! $hasVersion && ! $hasLogoSha256) {
            return true;
        }

        return $hasVersion
            && $hasLogoSha256
            && is_string($operation['qr_profile_version'])
            && preg_match('/\A[a-z0-9][a-z0-9._-]{2,49}\z/', $operation['qr_profile_version']) === 1
            && is_string($operation['qr_logo_sha256'])
            && preg_match('/\A[a-f0-9]{64}\z/i', $operation['qr_logo_sha256']) === 1;
    }

    private static function validRelativePath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '\\')
            && ! str_contains($path, '..')
            && ! str_contains($path, ':')
            && preg_match('/\A[a-zA-Z0-9._-]+(?:\/[a-zA-Z0-9._-]+)*\z/', $path) === 1;
    }
}
