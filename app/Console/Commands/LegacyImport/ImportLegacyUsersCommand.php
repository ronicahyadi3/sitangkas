<?php

namespace App\Console\Commands\LegacyImport;

use App\Actions\LegacyImport\AnalyzeLegacyUsers;
use App\Data\LegacyImport\LegacyUserImportAnalysis;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

#[Signature('legacy:import-users {--dry-run : Analyze source data without writing target tables} {--chunk= : Source rows read per chunk}')]
#[Description('Analyze and eventually import legacy users into users and user_positions.')]
final class ImportLegacyUsersCommand extends Command
{
    public function handle(AnalyzeLegacyUsers $analyzer, FilesystemManager $filesystems): int
    {
        if (! $this->option('dry-run')) {
            $this->error('Saat ini command hanya mendukung --dry-run. Tidak ada data yang diimpor.');

            return self::INVALID;
        }

        $chunkSize = $this->chunkSize();

        if ($chunkSize === null) {
            return self::INVALID;
        }

        $this->info('Menganalisis sitangkas_legacy.users dalam mode read-only...');

        try {
            $analysis = $analyzer->handle($chunkSize);
            $reportPath = $this->writeReport($analysis, $filesystems);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Dry-run gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->displaySummary($analysis, $reportPath);

        if ($analysis->hasBlockers()) {
            $this->newLine();
            $this->error('Dry-run selesai dengan blocker. Import --commit tidak boleh dijalankan.');

            foreach ($analysis->blockers as $blocker) {
                $this->line(' - '.$blocker, 'error');
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Dry-run lulus seluruh invariant otomatis. Database target tidak diubah.');

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
    private function writeReport(LegacyUserImportAnalysis $analysis, FilesystemManager $filesystems): string
    {
        $diskName = (string) config('legacy_import.reports.disk', 'local');
        $directory = trim((string) config('legacy_import.reports.directory', 'legacy-import/users'), '/');
        $fingerprint = Str::substr((string) $analysis->source['used_columns_sha256'], 0, 12);
        $filename = 'dry-run-'.now()->format('Ymd-His').'-'.$fingerprint.'.json';
        $reportPath = $directory.'/'.$filename;
        $contents = json_encode(
            $analysis->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ).PHP_EOL;

        if (! $filesystems->disk($diskName)->put($reportPath, $contents)) {
            throw new RuntimeException('Laporan dry-run tidak dapat disimpan ke disk private.');
        }

        return $filesystems->disk($diskName)->path($reportPath);
    }

    private function displaySummary(LegacyUserImportAnalysis $analysis, string $reportPath): void
    {
        $targetValidation = $analysis->validation['metrics']['target'] ?? [];

        $this->table(
            ['Item', 'Hasil'],
            [
                ['Source rows', (string) $analysis->source['row_count']],
                ['ID range', $analysis->source['minimum_id'].'-'.$analysis->source['maximum_id']],
                ['Distinct NIK / calon user', (string) $analysis->source['distinct_nik_count']],
                ['Akun canonical', (string) $analysis->accounts['account_count']],
                ['Akun dari multi-row NIK', (string) $analysis->accounts['multi_row_account_count']],
                ['Kandidat akun aktif', (string) $analysis->accounts['active_account_candidate_count']],
                ['Kandidat tanpa posisi aktif', (string) $analysis->accounts['without_active_position_candidate_count']],
                ['Akun dengan seluruh row terhapus', (string) $analysis->accounts['all_source_rows_deleted_account_count']],
                ['Checksum ID akun canonical', (string) $analysis->accounts['canonical_account_ids_sha256']],
                ['Posisi canonical', (string) $analysis->positions['canonical_position_count']],
                ['Posisi alias', (string) $analysis->positions['alias_position_count']],
                ['Alias dengan soft-delete rekonsiliasi', (string) $analysis->positions['synthesized_alias_soft_delete_count']],
                ['Checksum klasifikasi posisi', (string) $analysis->positions['classification_sha256']],
                ['Kelompok konteks duplikat', (string) $analysis->positions['duplicate_context_count']],
                ['Kelompok aktif ganda', (string) $analysis->positions['active_duplicate_context_count']],
                ['Koreksi organisasi allowlist', (string) $analysis->organization['allowed_correction_count']],
                ['Mismatch organisasi tak dikenal', (string) $analysis->organization['unexpected_mismatch_count']],
                ['Konflik profil per NIK', (string) $analysis->identity['profile_conflict_group_count']],
                ['Konflik email lintas NIK', (string) $analysis->identity['cross_nik_email_conflict_count']],
                ['Validator', ($analysis->validation['passed'] ?? false) ? 'LULUS' : 'GAGAL'],
                ['Target users saat ini', (string) ($targetValidation['current_users_count'] ?? '-')],
                ['Target user_positions saat ini', (string) ($targetValidation['current_user_positions_count'] ?? '-')],
                ['Blocker', (string) count($analysis->blockers)],
                ['Warning', (string) count($analysis->warnings)],
                ['Fingerprint', (string) $analysis->source['used_columns_sha256']],
                ['Laporan private', $reportPath],
            ]
        );

        $this->displayPasswordPolicySummary($analysis);

        foreach ($analysis->warnings as $warning) {
            $this->warn($warning);
        }

        $this->displayIssueIds($analysis);

        if ($analysis->pendingDecisions === []) {
            return;
        }

        $this->newLine();
        $this->warn('Keputusan yang masih harus dikunci sebelum --commit:');

        foreach ($analysis->pendingDecisions as $decision) {
            $this->line(' - '.$decision);
        }
    }

    private function displayPasswordPolicySummary(LegacyUserImportAnalysis $analysis): void
    {
        $passwordPolicy = $analysis->validation['metrics']['password_policy'] ?? [];

        if (! is_array($passwordPolicy) || $passwordPolicy === []) {
            $this->warn('Metadata kebijakan password tidak tersedia pada hasil validator.');

            return;
        }

        $developmentOverride = match ($passwordPolicy['development_override'] ?? null) {
            'enabled' => 'Aktif',
            'disabled' => 'Nonaktif',
            default => 'Tidak diketahui',
        };
        $mustChangePassword = match ($passwordPolicy['must_change_password'] ?? null) {
            true => 'Ya',
            false => 'Tidak',
            default => 'Tidak diketahui',
        };
        $invalidPasswordHashAccountIds = is_array($passwordPolicy['invalid_password_hash_account_ids'] ?? null)
            ? $passwordPolicy['invalid_password_hash_account_ids']
            : [];

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Kebijakan Password Import</>');
        $this->table(
            ['Item', 'Hasil'],
            [
                ['Strategi produksi', (string) ($passwordPolicy['configured_strategy'] ?? '-')],
                ['Strategi efektif', (string) ($passwordPolicy['effective_strategy'] ?? '-')],
                ['Override shared password', $developmentOverride],
                ['Wajib ganti password', $mustChangePassword],
                ['Environment', (string) ($passwordPolicy['resolved_environment'] ?? '-')],
                ['Akun yang diproyeksikan', (string) ($passwordPolicy['projected_account_count'] ?? '-')],
                ['Hash password tidak valid', (string) count($invalidPasswordHashAccountIds)],
            ],
        );

        if ($developmentOverride === 'Aktif') {
            $this->warn('Shared password development sedang aktif. Pastikan fitur ini tidak digunakan di luar environment local.');
        }
    }

    private function displayIssueIds(LegacyUserImportAnalysis $analysis): void
    {
        $issueGroups = [
            'NIK tidak valid' => $analysis->identity['invalid_nik_row_ids'],
            'NIP tidak berformat 18 digit' => $analysis->identity['invalid_nip_row_ids'],
            'NIP nonstandar yang terpilih untuk akun canonical' => $analysis->accounts['selected_nonstandard_nip_row_ids'],
            'Email tidak valid' => $analysis->identity['invalid_email_row_ids'],
            'Konflik profil per NIK' => $this->nestedRowIds(
                $analysis->identity['profile_conflicts'],
                'row_ids'
            ),
            'Email dipakai lintas NIK' => $this->nestedRowIds(
                $analysis->identity['cross_nik_email_conflicts'],
                'row_ids'
            ),
            'Koreksi organisasi allowlist' => $this->scalarRowIds(
                $analysis->organization['allowed_corrections'],
                'row_id'
            ),
            'Alias posisi karena konteks duplikat' => $this->nestedRowIds(
                $analysis->positions['duplicate_contexts'],
                'alias_position_ids'
            ),
            'Alias tanpa timestamp rekonsiliasi' => $analysis->positions['missing_reconciliation_timestamp_row_ids'],
            'Referensi document.uploaded_by tanpa posisi proyeksi' => $analysis->validation['metrics']['target']['uncovered_document_uploaded_by_ids'] ?? [],
            'Referensi document.users_to tanpa posisi proyeksi' => $analysis->validation['metrics']['target']['uncovered_document_users_to_ids'] ?? [],
            'Referensi document_process.id_user tanpa posisi proyeksi' => $analysis->validation['metrics']['target']['uncovered_document_process_user_ids'] ?? [],
        ];

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Legacy row ID yang perlu ditinjau</>');

        foreach ($issueGroups as $label => $rowIds) {
            $this->displayIdGroup($label, $rowIds);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return list<int>
     */
    private function nestedRowIds(array $groups, string $key): array
    {
        $rowIds = [];

        foreach ($groups as $group) {
            foreach ((array) ($group[$key] ?? []) as $rowId) {
                $rowIds[] = (int) $rowId;
            }
        }

        return $this->sortedUniqueIds($rowIds);
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return list<int>
     */
    private function scalarRowIds(array $groups, string $key): array
    {
        $rowIds = [];

        foreach ($groups as $group) {
            if (isset($group[$key])) {
                $rowIds[] = (int) $group[$key];
            }
        }

        return $this->sortedUniqueIds($rowIds);
    }

    /**
     * @param  array<int, int|string>  $rowIds
     */
    private function displayIdGroup(string $label, array $rowIds): void
    {
        $rowIds = $this->sortedUniqueIds($rowIds);
        $this->line("<fg=yellow>{$label}</> (".count($rowIds).')');
        $this->line($rowIds === [] ? '  -' : '  '.implode(', ', $rowIds));
    }

    /**
     * @param  array<int, int|string>  $rowIds
     * @return list<int>
     */
    private function sortedUniqueIds(array $rowIds): array
    {
        $rowIds = array_values(array_unique(array_map('intval', $rowIds)));
        sort($rowIds, SORT_NUMERIC);

        return $rowIds;
    }
}
