<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Data\Document\LegacyPdfSourceDefinition;
use App\Data\Document\ResolvedPdfDeliverySource;
use App\Enums\Document\DocumentDetailSourceState;
use App\Models\Document;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;

final class LegacyFilesystemPdfDeliverySource
{
    /** @var array<string, ResolvedPdfDeliverySource|null> */
    private array $resolvedSources = [];

    public function __construct(
        private readonly FilesystemManager $filesystems,
        private readonly LegacyDocumentSourceRegistry $registry,
        private readonly ConfigRepository $config,
    ) {}

    public function resolve(
        Document $document,
        string $resourceKey,
        string $storageDisk,
        DocumentDetailSourceState $sourceState,
    ): ?ResolvedPdfDeliverySource {
        if (! in_array($sourceState, [
            DocumentDetailSourceState::LegacyPrivatePending,
            DocumentDetailSourceState::LegacyPublicPending,
        ], true)) {
            return null;
        }

        $definition = $this->definition($document, $resourceKey);
        if (! $definition instanceof LegacyPdfSourceDefinition) {
            return null;
        }

        $filename = trim((string) $document->getAttribute($definition->filenameAttribute));
        $signed = $resourceKey === PdfDeliverySource::RESOURCE_DOCUMENT
            && $document->status !== null
            && $definition->signedDirectory !== null;
        $cacheKey = implode(':', [
            $storageDisk,
            $sourceState->value,
            (string) $document->getKey(),
            $resourceKey,
            $signed ? 'signed' : 'source',
            hash('sha256', $filename),
        ]);

        if (array_key_exists($cacheKey, $this->resolvedSources)) {
            return $this->resolvedSources[$cacheKey];
        }

        if (! $this->isSafePdfFilename($filename)) {
            return $this->resolvedSources[$cacheKey] = null;
        }

        $relativePath = $this->relativePath($definition, $filename, $signed);

        try {
            $disk = $this->filesystems->disk($storageDisk);
            if (! $disk->exists($relativePath)
                || ! $this->isReadableFileWithinRoot(
                    $storageDisk,
                    $disk->path($relativePath),
                )) {
                return $this->resolvedSources[$cacheKey] = null;
            }

            $sizeBytes = $disk->size($relativePath);
            $stream = $disk->readStream($relativePath);
        } catch (FilesystemException) {
            return $this->resolvedSources[$cacheKey] = null;
        }

        if (! is_resource($stream) || $sizeBytes < 5) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            return $this->resolvedSources[$cacheKey] = null;
        }

        try {
            $header = fread($stream, 5);
            if ($header !== '%PDF-' || rewind($stream) === false) {
                return $this->resolvedSources[$cacheKey] = null;
            }

            $hashContext = hash_init('sha256');
            if (hash_update_stream($hashContext, $stream) !== $sizeBytes) {
                return $this->resolvedSources[$cacheKey] = null;
            }

            $sha256 = hash_final($hashContext);
        } finally {
            fclose($stream);
        }

        return $this->resolvedSources[$cacheKey] = new ResolvedPdfDeliverySource(
            resourceType: 'legacy_file',
            resourceKey: $resourceKey,
            documentId: (int) $document->getKey(),
            authorizationSubject: $document,
            sourceState: $sourceState,
            documentArtifactId: null,
            artifactPublicId: null,
            storageDisk: $storageDisk,
            filePath: $relativePath,
            safeFilename: $this->safeFilename($filename),
            mimeType: 'application/pdf',
            sha256: $sha256,
            sizeBytes: $sizeBytes,
            documentYear: $this->documentYear($document),
            metadata: [
                'document_type' => Str::upper(trim((string) $document->src_type)),
                'payment_type' => Str::upper(trim((string) $document->payment_type)),
                'signed_copy' => $signed,
                'storage_class' => $sourceState->value,
            ],
        );
    }

    private function definition(
        Document $document,
        string $resourceKey,
    ): ?LegacyPdfSourceDefinition {
        if ($resourceKey === PdfDeliverySource::RESOURCE_DOCUMENT) {
            return $this->registry->forDocumentType((string) $document->src_type);
        }

        return $this->registry->forAttachment($resourceKey);
    }

    private function isSafePdfFilename(string $filename): bool
    {
        return $filename !== ''
            && basename(str_replace('\\', '/', $filename)) === $filename
            && preg_match('/\.pdf\z/i', $filename) === 1;
    }

    private function relativePath(
        LegacyPdfSourceDefinition $definition,
        string $filename,
        bool $signed,
    ): string {
        $segments = [$definition->relativeDirectory];
        if ($signed && $definition->signedDirectory !== null) {
            $segments[] = $definition->signedDirectory;
        }
        $segments[] = $filename;

        return implode('/', $segments);
    }

    private function safeFilename(string $filename): string
    {
        $stem = (string) Str::of(pathinfo($filename, PATHINFO_FILENAME))
            ->squish()
            ->replaceMatches('/[^\pL\pN._ -]+/u', '-')
            ->trim(' .-_')
            ->limit(180, '');

        return ($stem !== '' ? $stem : 'dokumen').'.pdf';
    }

    private function isReadableFileWithinRoot(string $storageDisk, string $absolutePath): bool
    {
        $configuredRoot = $this->config->get("filesystems.disks.{$storageDisk}.root");
        if (! is_string($configuredRoot)) {
            return false;
        }

        $resolvedRoot = realpath($configuredRoot);
        $resolvedPath = realpath($absolutePath);
        if ($resolvedRoot === false
            || $resolvedPath === false
            || ! is_file($resolvedPath)
            || ! is_readable($resolvedPath)) {
            return false;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/').'/';
        $normalizedPath = str_replace('\\', '/', $resolvedPath);

        return Str::startsWith($normalizedPath, $normalizedRoot);
    }

    private function documentYear(Document $document): int
    {
        $createdAt = $document->created_at;

        return $createdAt instanceof DateTimeInterface
            ? (int) $createdAt->format('Y')
            : (int) now()->format('Y');
    }
}
