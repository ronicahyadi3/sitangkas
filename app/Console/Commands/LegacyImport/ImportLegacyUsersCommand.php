<?php

namespace App\Console\Commands\LegacyImport;

use App\Actions\LegacyImport\AnalyzeLegacyUsers;
use App\Actions\LegacyImport\ImportLegacyUsers;
use App\Data\LegacyImport\LegacyUserImportAnalysis;
use App\Data\LegacyImport\LegacyUserImportResult;
use App\Exceptions\LegacyImport\LegacyUserImportBlockedException;
use App\Services\LegacyImport\LegacyUserImportCommitReportWriter;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

#[Signature('legacy:import-users {--dry-run : Analyze source data without writing target tables} {--commit : Import analyzed legacy users into target tables} {--fingerprint= : Full approved SHA-256 fingerprint required for commit} {--chunk= : Source rows read per chunk}')]
#[Description('Analyze or import legacy users into users and user_positions.')]
final class ImportLegacyUsersCommand extends Command
{
    private const string ModeCommit = 'commit';

    private const string ModeDryRun = 'dry-run';

    public function handle(
        AnalyzeLegacyUsers $analyzer,
        ImportLegacyUsers $importer,
        LegacyUserImportCommitReportWriter $commitReportWriter,
        FilesystemManager $filesystems,
    ): int {
        $mode = $this->mode();

        if ($mode === null) {
            return self::INVALID;
        }

        if ($mode === self::ModeDryRun && $this->option('fingerprint') !== null) {
            $this->error('--fingerprint hanya boleh digunakan bersama --commit.');

            return self::INVALID;
        }

        $chunkSize = $this->chunkSize();

        if ($chunkSize === null) {
            return self::INVALID;
        }

        if ($mode === self::ModeCommit) {
            return $this->handleCommitGuard(
                $analyzer,
                $importer,
                $commitReportWriter,
                $filesystems,
                $chunkSize,
            );
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

    private function mode(): ?string
    {
        $dryRun = (bool) $this->option('dry-run');
        $commit = (bool) $this->option('commit');

        if ($dryRun && $commit) {
            $this->error('Pilih salah satu mode: --dry-run atau --commit, bukan keduanya.');

            return null;
        }

        if (! $dryRun && ! $commit) {
            $this->error('Mode wajib dipilih. Gunakan --dry-run atau --commit.');

            return null;
        }

        return $commit ? self::ModeCommit : self::ModeDryRun;
    }

    private function handleCommitGuard(
        AnalyzeLegacyUsers $analyzer,
        ImportLegacyUsers $importer,
        LegacyUserImportCommitReportWriter $commitReportWriter,
        FilesystemManager $filesystems,
        int $chunkSize,
    ): int {
        $fingerprint = $this->commitFingerprint();

        if ($fingerprint === null) {
            return self::INVALID;
        }

        $this->info('Menghitung ulang analisis source untuk guard commit...');

        try {
            $analysis = $analyzer->handle($chunkSize);
            $reportPath = $this->writeReport($analysis, $filesystems);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Guard commit gagal menjalankan analisis terbaru: '.$exception->getMessage());

            return self::FAILURE;
        }

        $latestFingerprint = Str::lower((string) ($analysis->source['used_columns_sha256'] ?? ''));

        if (preg_match('/\A[a-f0-9]{64}\z/', $latestFingerprint) !== 1) {
            $this->error('Analisis terbaru tidak menghasilkan fingerprint SHA-256 yang valid.');

            return self::FAILURE;
        }

        if (! hash_equals($latestFingerprint, $fingerprint)) {
            $this->table(
                ['Fingerprint', 'Nilai'],
                [
                    ['Disetujui dari --fingerprint', $fingerprint],
                    ['Analisis source terbaru', $latestFingerprint],
                ],
            );
            $this->error('Fingerprint berbeda. Source legacy berubah atau fingerprint yang diberikan bukan hasil dry-run terbaru.');
            $this->line('Laporan analisis terbaru: '.$reportPath);

            return self::FAILURE;
        }

        if ($analysis->hasBlockers()) {
            $this->error('Fingerprint cocok, tetapi analisis terbaru masih mempunyai blocker.');

            foreach ($analysis->blockers as $blocker) {
                $this->line(' - '.$blocker, 'error');
            }

            $this->line('Laporan analisis terbaru: '.$reportPath);

            return self::FAILURE;
        }

        $this->info('Guard fingerprint lulus. Fingerprint sama dengan analisis source terbaru dan blocker berjumlah 0.');
        $this->line('Fingerprint: '.$latestFingerprint);
        $this->line('Laporan analisis terbaru: '.$reportPath);

        if (! $this->operatorConfirmed($analysis, $latestFingerprint)) {
            return self::FAILURE;
        }

        $this->info('Konfirmasi operator diterima untuk fingerprint source terbaru.');

        return $this->executeCommit(
            $importer,
            $commitReportWriter,
            $chunkSize,
            $latestFingerprint,
        );
    }

    private function executeCommit(
        ImportLegacyUsers $importer,
        LegacyUserImportCommitReportWriter $commitReportWriter,
        int $chunkSize,
        string $sourceFingerprint,
    ): int {
        $startedAt = CarbonImmutable::now();

        Log::notice('Legacy user import commit started.', [
            'event' => 'legacy_user_import.commit_started',
            'status' => 'running',
            'source_fingerprint' => $sourceFingerprint,
            'chunk_size' => $chunkSize,
        ]);

        try {
            $result = $importer->handle($chunkSize, $sourceFingerprint);
        } catch (Throwable $exception) {
            return $this->handleCommitFailure(
                $commitReportWriter,
                $exception,
                $startedAt,
                $sourceFingerprint,
                $chunkSize,
            );
        }

        return $this->handleCommitSuccess(
            $commitReportWriter,
            $result,
            $startedAt,
            $chunkSize,
        );
    }

    private function handleCommitSuccess(
        LegacyUserImportCommitReportWriter $commitReportWriter,
        LegacyUserImportResult $result,
        CarbonImmutable $startedAt,
        int $chunkSize,
    ): int {
        try {
            $reportPath = $commitReportWriter->writeCompleted($result, $startedAt);
        } catch (Throwable $exception) {
            Log::critical('Legacy user import committed but its completion report could not be written.', [
                'event' => 'legacy_user_import.commit_report_failed',
                'status' => 'completed',
                'transaction_committed' => true,
                'source_fingerprint' => $result->sourceFingerprint,
                'exception_class' => $exception::class,
            ]);

            $this->error('Import berhasil di-commit, tetapi laporan completed gagal ditulis. Periksa structured log sebelum tindakan lain.');

            return self::FAILURE;
        }

        Log::notice('Legacy user import commit completed.', [
            'event' => 'legacy_user_import.commit_completed',
            'status' => 'completed',
            'transaction_committed' => true,
            'source_fingerprint' => $result->sourceFingerprint,
            'chunk_size' => $chunkSize,
            'duration_ms' => $this->durationMilliseconds($startedAt),
            'user_count' => $result->userCount,
            'canonical_position_count' => $result->canonicalPositionCount,
            'alias_position_count' => $result->aliasPositionCount,
            'report_path' => $reportPath,
        ]);

        $this->newLine();
        $this->info('Import legacy users berhasil di-commit.');
        $this->table(
            ['Item', 'Hasil'],
            [
                ['Users', (string) $result->userCount],
                ['Posisi canonical', (string) $result->canonicalPositionCount],
                ['Posisi alias', (string) $result->aliasPositionCount],
                ['Fingerprint', $result->sourceFingerprint],
                ['Laporan completed', $reportPath],
            ],
        );

        return self::SUCCESS;
    }

    private function handleCommitFailure(
        LegacyUserImportCommitReportWriter $commitReportWriter,
        Throwable $exception,
        CarbonImmutable $startedAt,
        string $sourceFingerprint,
        int $chunkSize,
    ): int {
        $failure = $this->commitFailureMetadata($exception);
        $reportPath = null;

        try {
            $reportPath = $commitReportWriter->writeFailed(
                startedAt: $startedAt,
                stage: $failure['stage'],
                failureCode: $failure['code'],
                blockerCount: $failure['blocker_count'],
                sourceFingerprint: $sourceFingerprint,
            );
        } catch (Throwable $reportException) {
            Log::critical('Legacy user import failure report could not be written.', [
                'event' => 'legacy_user_import.failure_report_failed',
                'status' => 'failed',
                'transaction_committed' => false,
                'source_fingerprint' => $sourceFingerprint,
                'failure_stage' => $failure['stage'],
                'failure_code' => $failure['code'],
                'exception_class' => $reportException::class,
            ]);
        }

        Log::error('Legacy user import commit failed.', [
            'event' => 'legacy_user_import.commit_failed',
            'status' => 'failed',
            'transaction_committed' => false,
            'source_fingerprint' => $sourceFingerprint,
            'chunk_size' => $chunkSize,
            'duration_ms' => $this->durationMilliseconds($startedAt),
            'failure_stage' => $failure['stage'],
            'failure_code' => $failure['code'],
            'blocker_count' => $failure['blocker_count'],
            'exception_class' => $exception::class,
            'report_path' => $reportPath,
        ]);

        $this->error("Import gagal pada stage [{$failure['stage']}]. Database target tidak di-commit.");

        if ($exception instanceof LegacyUserImportBlockedException) {
            foreach ($exception->blockers as $blocker) {
                $this->line(' - '.$blocker, 'error');
            }
        } else {
            $this->line('Detail exception mentah tidak ditampilkan untuk mencegah kebocoran data sensitif.', 'error');
        }

        if ($reportPath !== null) {
            $this->line('Laporan failed: '.$reportPath);
        }

        return self::FAILURE;
    }

    /** @return array{stage: string, code: string, blocker_count: int} */
    private function commitFailureMetadata(Throwable $exception): array
    {
        if ($exception instanceof LegacyUserImportBlockedException) {
            return [
                'stage' => $exception->stage,
                'code' => 'import_blocked',
                'blocker_count' => count($exception->blockers),
            ];
        }

        return [
            'stage' => 'import_execution',
            'code' => 'unexpected_exception',
            'blocker_count' => 0,
        ];
    }

    private function durationMilliseconds(CarbonImmutable $startedAt): int
    {
        return max(0, (int) round($startedAt->diffInMilliseconds(CarbonImmutable::now())));
    }

    private function operatorConfirmed(
        LegacyUserImportAnalysis $analysis,
        string $fingerprint,
    ): bool {
        $target = $analysis->validation['metrics']['target'] ?? [];
        $confirmationPhrase = 'IMPORT LEGACY USERS '.Str::substr($fingerprint, 0, 12);

        $this->newLine();
        $this->warn('PERINGATAN: mode ini dipersiapkan untuk menulis akun dan posisi legacy ke database target.');
        $this->table(
            ['Item', 'Nilai'],
            [
                ['Fingerprint', $fingerprint],
                ['Source rows', (string) ($analysis->source['row_count'] ?? '-')],
                ['Users', (string) ($analysis->accounts['account_count'] ?? '-')],
                ['Posisi canonical', (string) ($analysis->positions['canonical_position_count'] ?? '-')],
                ['Posisi alias', (string) ($analysis->positions['alias_position_count'] ?? '-')],
                ['Blocker', (string) count($analysis->blockers)],
                ['Target users saat ini', (string) ($target['current_users_count'] ?? '-')],
                ['Target user_positions saat ini', (string) ($target['current_user_positions_count'] ?? '-')],
            ],
        );

        if (! $this->input->isInteractive()) {
            $this->error('Mode --commit wajib dijalankan secara interaktif untuk konfirmasi operator.');

            return false;
        }

        $answer = $this->ask("Ketik tepat '{$confirmationPhrase}' untuk mengonfirmasi");

        if (! is_string($answer) || ! hash_equals($confirmationPhrase, trim($answer))) {
            $this->error('Konfirmasi operator tidak cocok. Import dibatalkan.');

            return false;
        }

        return true;
    }

    private function commitFingerprint(): ?string
    {
        $value = $this->option('fingerprint');

        if (! is_string($value) || trim($value) === '') {
            $this->error('--fingerprint wajib diisi dengan SHA-256 penuh saat menggunakan --commit.');

            return null;
        }

        $fingerprint = Str::lower(trim($value));

        if (preg_match('/\A[a-f0-9]{64}\z/', $fingerprint) !== 1) {
            $this->error('--fingerprint harus berupa SHA-256 penuh: tepat 64 karakter heksadesimal.');

            return null;
        }

        return $fingerprint;
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
        $filename = implode('-', [
            'dry-run',
            now()->format('Ymd-His-u'),
            $fingerprint,
            Str::lower((string) Str::ulid()),
        ]).'.json';
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

        $this->displayAccountStatusSummary($analysis);
        $this->displayPasswordPolicySummary($analysis);
        $this->displayImportExecutionSummary();

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

    private function displayAccountStatusSummary(LegacyUserImportAnalysis $analysis): void
    {
        $accountStatus = $analysis->validation['metrics']['account_status'] ?? [];

        if (! is_array($accountStatus) || $accountStatus === []) {
            $this->warn('Metadata kebijakan status akun tidak tersedia pada hasil validator.');

            return;
        }

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Kebijakan Status Akun Import</>');
        $this->table(
            ['Item', 'Hasil'],
            [
                ['Strategi', (string) ($accountStatus['strategy'] ?? '-')],
                ['Akun yang diproyeksikan', (string) ($accountStatus['account_count'] ?? '-')],
                ['Akun aktif', (string) ($accountStatus['active_account_count'] ?? '-')],
                ['Akun nonaktif', (string) ($accountStatus['inactive_account_count'] ?? '-')],
                ['Nonaktif tanpa posisi canonical aktif', (string) ($accountStatus['inactive_non_deleted_account_count'] ?? '-')],
                ['Nonaktif, seluruh row sumber terhapus', (string) ($accountStatus['all_source_rows_deleted_account_count'] ?? '-')],
                ['Checksum resolusi', (string) ($accountStatus['resolution_sha256'] ?? '-')],
            ],
        );
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

    private function displayImportExecutionSummary(): void
    {
        $executionEnabled = config('legacy_import.execution.enabled', false) === true;
        $fileSkStrategy = (string) config('legacy_import.execution.decisions.file_sk_strategy', '');
        $pendingDecisions = array_values(config('legacy_import.pending_decisions', []));

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Kebijakan Eksekusi Import</>');
        $this->table(
            ['Item', 'Hasil'],
            [
                ['Safety gate import', $executionEnabled ? 'AKTIF' : 'NONAKTIF'],
                ['Strategi file SK', $fileSkStrategy !== '' ? $fileSkStrategy : '-'],
                ['Penulisan file SK saat import users', $fileSkStrategy === 'defer' ? 'Ditunda' : 'Tidak diketahui'],
                ['Keputusan pending', (string) count($pendingDecisions)],
            ],
        );

        if (! $executionEnabled) {
            $this->warn('Safety gate import tetap nonaktif. Command ini hanya menjalankan dry-run read-only.');
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
            'Akun nonaktif tanpa posisi canonical aktif' => $analysis->status['inactive_non_deleted_account_ids'] ?? [],
            'Akun nonaktif karena seluruh row sumber terhapus' => $analysis->status['all_source_rows_deleted_account_ids'] ?? [],
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
