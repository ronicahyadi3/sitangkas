<?php

declare(strict_types=1);

namespace App\Support\Esign;

final class DocumentArtifactStoragePath
{
    private function __construct() {}

    public static function checksum(string $storageDisk, string $filePath): string
    {
        return hash('sha256', $storageDisk.':'.$filePath);
    }
}
