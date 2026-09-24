<?php

namespace App\Console\Commands\Esign;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;

#[Signature('esign:cleanup-prepared-renditions {--delete : Delete expired prepared rendition files}')]
#[Description('Inspect or delete expired private eSign prepared rendition files')]
final class CleanupPreparedRenditions extends Command
{
    public function handle(FilesystemManager $filesystems): int
    {
        $diskName = config('esign.visible_editor.prepared_disk');
        $root = config('esign.visible_editor.prepared_root');
        $retentionMinutes = config('esign.visible_editor.prepared_retention_minutes');

        if (! is_string($diskName)
            || $diskName === ''
            || ! is_string($root)
            || preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*\z/', trim($root, '/')) !== 1
            || ! is_int($retentionMinutes)
            || $retentionMinutes < 60
            || $retentionMinutes > 10080) {
            $this->error('Konfigurasi retention prepared rendition tidak valid.');

            return self::FAILURE;
        }

        $disk = $filesystems->disk($diskName);
        $cutoff = now()->subMinutes($retentionMinutes)->getTimestamp();
        $candidates = [];

        foreach ($disk->allFiles(trim($root, '/')) as $path) {
            if (! str_ends_with(strtolower($path), '.pdf')
                && ! str_ends_with(strtolower($path), '.png')) {
                continue;
            }

            try {
                if ($disk->lastModified($path) <= $cutoff) {
                    $candidates[] = $path;
                }
            } catch (\Throwable $exception) {
                $this->warn("Tidak dapat memeriksa: {$path}");
            }
        }

        if (! (bool) $this->option('delete')) {
            $this->info(count($candidates).' file prepared rendition telah kedaluwarsa (dry-run).');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($candidates as $path) {
            if ($disk->delete($path)) {
                $deleted++;
            } else {
                $this->warn("Gagal menghapus: {$path}");
            }
        }

        $this->info("{$deleted} file prepared rendition kedaluwarsa dihapus.");

        return $deleted === count($candidates) ? self::SUCCESS : self::FAILURE;
    }
}
