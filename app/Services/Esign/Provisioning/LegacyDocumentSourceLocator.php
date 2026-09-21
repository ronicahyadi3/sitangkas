<?php

declare(strict_types=1);

namespace App\Services\Esign\Provisioning;

use App\Models\Document;
use RuntimeException;

final class LegacyDocumentSourceLocator
{
    public function locate(Document $document): string
    {
        $documentType = trim((string) $document->src_type);
        $sourceName = trim((string) $document->src_name);

        if (preg_match('/\A[A-Za-z0-9_]+\z/', $documentType) !== 1
            || $sourceName === ''
            || basename(str_replace('\\', '/', $sourceName)) !== $sourceName
            || preg_match('/\.pdf\z/i', $sourceName) !== 1) {
            throw new RuntimeException('legacy_document_source_identity_invalid');
        }

        $roots = [
            public_path(),
            storage_path('app/private/documents'),
        ];

        foreach ($roots as $root) {
            $path = $root.DIRECTORY_SEPARATOR.'File_'.$documentType.DIRECTORY_SEPARATOR.$sourceName;

            if ($this->isReadableFileWithinRoot($path, $root)) {
                return (string) realpath($path);
            }
        }

        throw new RuntimeException('legacy_document_source_not_found');
    }

    private function isReadableFileWithinRoot(string $path, string $root): bool
    {
        $resolvedPath = realpath($path);
        $resolvedRoot = realpath($root);

        if ($resolvedPath === false || $resolvedRoot === false || ! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
            return false;
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $resolvedRoot), '/').'/';
        $normalizedPath = str_replace('\\', '/', $resolvedPath);

        return str_starts_with($normalizedPath, $normalizedRoot);
    }
}
