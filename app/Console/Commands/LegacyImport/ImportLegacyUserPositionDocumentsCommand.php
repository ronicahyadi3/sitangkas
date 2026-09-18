<?php

namespace App\Console\Commands\LegacyImport;

use App\Actions\LegacyImport\AnalyzeLegacyUserPositionDocuments;
use App\Data\LegacyImport\LegacyUserPositionDocumentAnalysis;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

#[Signature('legacy:import-user-position-documents {--dry-run : Analyze references and physical SK files without writing target data} {--chunk= : Source rows read per chunk}')]
#[Description('Analyze legacy user position SK documents without changing source or target data.')]
final class ImportLegacyUserPositionDocumentsCommand extends Command
{
    public function handle(
        AnalyzeLegacyUserPositionDocuments $analyzer,
        FilesystemManager $filesystems,
    ): int {
        if (! $this->option('dry-run')) {
            $this->error('Saat ini command hanya mendukung --dry-run. Tidak ada dokumen yang diimpor.');

            return self::INVALID;
        }

        $chunkSize = $this->chunkSize();

        if ($chunkSize === null) {
            return self::INVALID;
        }

        $this->info('Menganalisis referensi dan file fisik SK legacy dalam mode read-only...');

        try {
            $analysis = $analyzer->handle($chunkSize);
            $reportPath = $this->writeReport($analysis, $filesystems);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Dry-run dokumen SK gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->displaySummary($analysis, $reportPath);

        if ($analysis->hasBlockers()) {
            $this->newLine();
            $this->error('Dry-run dokumen SK selesai dengan blocker. Import dokumen tidak boleh dijalankan.');

            foreach ($analysis->blockers as $blocker) {
                $this->line(' - '.$blocker, 'error');
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Dry-run dokumen SK lulus. Database dan direktori sumber tidak diubah.');

        return self::SUCCESS;
    }

    private function chunkSize(): ?int
    {
        $value = $this->option('chunk');
        $chunkSize = $value === null || $value === ''
            ? (int) config('legacy_import.users.chunk_size', 500)
            : filter_var($value, FILTER_VALIDATE_INT);

        if (! is_int($chunkSize) || $chunkSize < 50 || $chunkSize > 5000) {
            $this->error('--chunk harus berupa bilangan bulat antara 50 dan 5000.');

            return null;
        }

        return $chunkSize;
    }

    /** @throws JsonException */
    private function writeReport(
        LegacyUserPositionDocumentAnalysis $analysis,
        FilesystemManager $filesystems,
    ): string {
        $diskName = (string) config('legacy_import.reports.disk', 'local');
        $directory = trim((string) config(
            'legacy_import.reports.position_documents_directory',
            'legacy-import/user-position-documents',
        ), '/');
        $fingerprint = Str::substr((string) $analysis->physicalFiles['manifest_sha256'], 0, 12);
        $filename = 'dry-run-'.now()->format('Ymd-His').'-'.$fingerprint.'.json';
        $reportPath = $directory.'/'.$filename;
        $contents = json_encode(
            $analysis->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL;

        if (! $filesystems->disk($diskName)->put($reportPath, $contents)) {
            throw new RuntimeException('Laporan dry-run dokumen SK tidak dapat disimpan ke disk private.');
        }

        return $filesystems->disk($diskName)->path($reportPath);
    }

    private function displaySummary(
        LegacyUserPositionDocumentAnalysis $analysis,
        string $reportPath,
    ): void {
        $this->table(
            ['Item', 'Hasil'],
            [
                ['Legacy rows', (string) $analysis->source['row_count']],
                ['Referensi file_sk terisi', (string) $analysis->source['populated_reference_count']],
                ['Referensi file_sk unik', (string) $analysis->source['unique_reference_count']],
                ['File fisik', (string) $analysis->physicalFiles['file_count']],
                ['PDF fisik', (string) $analysis->physicalFiles['pdf_count']],
                ['PDF valid ketat', (string) $analysis->physicalFiles['strict_valid_pdf_count']],
                ['File incomplete', (string) $analysis->physicalFiles['incomplete_file_count']],
                ['Referensi cocok fisik', (string) $analysis->documents['matched_reference_count']],
                ['Siap diimpor', (string) $analysis->documents['available_valid_count']],
                ['Tanpa file fisik', (string) $analysis->documents['missing_reference_count']],
                ['PDF terreferensi tidak valid', (string) $analysis->documents['invalid_pdf_count']],
                ['File fisik orphan', (string) $analysis->physicalFiles['orphan_physical_file_count']],
                ['Duplikat konten terreferensi', $analysis->documents['referenced_duplicate_content_group_count'].' grup / '.$analysis->documents['referenced_duplicate_content_file_count'].' file'],
                ['Checksum referensi', (string) $analysis->source['reference_sha256']],
                ['Checksum manifest', (string) $analysis->physicalFiles['manifest_sha256']],
                ['Validator', $analysis->hasBlockers() ? 'GAGAL' : 'LULUS'],
                ['Blocker', (string) count($analysis->blockers)],
                ['Warning', (string) count($analysis->warnings)],
                ['Laporan private', $reportPath],
            ],
        );

        foreach ($analysis->warnings as $warning) {
            $this->warn($warning);
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Legacy row ID dokumen SK</>');
        $this->displayIds('Siap diimpor', $analysis->documents['available_valid_row_ids']);
        $this->displayIds('Tanpa file fisik', $analysis->documents['missing_reference_row_ids']);
        $this->displayIds('PDF tidak valid', $analysis->documents['invalid_pdf_row_ids']);
        $this->displayIds('File incomplete', $analysis->documents['incomplete_reference_row_ids']);
        $this->displayIds('Referensi ambigu', $analysis->documents['ambiguous_reference_row_ids']);
    }

    /** @param list<int> $ids */
    private function displayIds(string $label, array $ids): void
    {
        $this->line("<fg=yellow>{$label}</> (".count($ids).')');
        $this->line($ids === [] ? '  -' : '  '.implode(', ', $ids));
    }
}
