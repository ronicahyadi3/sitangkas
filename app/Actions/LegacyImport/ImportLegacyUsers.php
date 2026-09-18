<?php

namespace App\Actions\LegacyImport;

use App\Data\LegacyImport\LegacyUserAccountAggregation;
use App\Data\LegacyImport\LegacyUserAccountStatusResolution;
use App\Data\LegacyImport\LegacyUserImportPasswordPolicy;
use App\Data\LegacyImport\LegacyUserImportPlan;
use App\Data\LegacyImport\LegacyUserImportResult;
use App\Data\LegacyImport\LegacyUserPositionClassification;
use App\Data\LegacyImport\LegacyUserPositionProjection;
use App\Exceptions\LegacyImport\LegacyUserImportBlockedException;
use App\Models\User;
use App\Services\LegacyImport\LegacyUserAccountStatusResolver;
use App\Services\LegacyImport\LegacyUserImportPasswordPolicyResolver;
use App\Services\LegacyImport\LegacyUserImportValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class ImportLegacyUsers
{
    private const array FileSkStrategies = [
        'defer',
    ];

    public function __construct(
        private AnalyzeLegacyUsers $analyzer,
        private LegacyUserImportValidator $validator,
        private LegacyUserImportPasswordPolicyResolver $passwordPolicyResolver,
        private DatabaseManager $database,
    ) {}

    public function handle(int $chunkSize, string $expectedSourceFingerprint): LegacyUserImportResult
    {
        $execution = $this->executionConfiguration();
        $connection = $this->database->connection();
        $this->assertSupportedConnection($connection);
        $this->acquireAdvisoryLock(
            $connection,
            $execution['advisory_lock_name'],
            $execution['advisory_lock_timeout_seconds'],
        );

        try {
            $plan = $this->analyzer->plan($chunkSize, $execution['password_policy']);
            $this->assertExpectedSourceFingerprint(
                (string) $plan->analysis->source['used_columns_sha256'],
                $expectedSourceFingerprint,
            );
            $this->throwWhenBlocked('analysis', $plan->analysis->blockers);

            return $connection->transaction(
                fn (): LegacyUserImportResult => $this->importWithinTransaction(
                    $connection,
                    $plan,
                    $execution,
                ),
                $execution['transaction_attempts'],
            );
        } finally {
            $this->releaseAdvisoryLock($connection, $execution['advisory_lock_name']);
        }
    }

    /**
     * @param  array{
     *     insert_chunk_size: int,
     *     password_policy: LegacyUserImportPasswordPolicy,
     *     account_status_strategy: string,
     *     file_sk_strategy: string,
     *     advisory_lock_name: string,
     *     advisory_lock_timeout_seconds: int,
     *     transaction_attempts: int
     * }  $execution
     */
    private function importWithinTransaction(
        Connection $connection,
        LegacyUserImportPlan $plan,
        array $execution,
    ): LegacyUserImportResult {
        $preflight = $this->validator->validateBeforeImport(
            $plan->accountAggregation,
            $plan->positionClassification,
            $plan->accountStatusResolution,
            $execution['password_policy'],
            $plan->validationInput(),
        );
        $this->throwWhenBlocked('preflight', $preflight->blockers);

        $importedAt = CarbonImmutable::now();
        $userRows = $this->userRows(
            $plan->accountAggregation,
            $execution['password_policy'],
            $plan->accountStatusResolution,
            $importedAt,
        );
        [$canonicalPositionRows, $aliasPositionRows] = $this->positionRows(
            $plan->positionClassification,
            $importedAt,
        );

        $this->insertChunks($connection, 'users', $userRows, $execution['insert_chunk_size']);
        $this->insertChunks($connection, 'user_positions', $canonicalPositionRows, $execution['insert_chunk_size']);
        $this->insertChunks($connection, 'user_positions', $aliasPositionRows, $execution['insert_chunk_size']);

        $postImport = $this->validator->validateAfterImport(
            $plan->accountAggregation,
            $plan->positionClassification,
            $plan->accountStatusResolution,
            $execution['password_policy'],
        );
        $this->throwWhenBlocked('post_import', $postImport->blockers);

        return new LegacyUserImportResult(
            completedAt: CarbonImmutable::now()->toIso8601String(),
            sourceFingerprint: (string) $plan->analysis->source['used_columns_sha256'],
            userCount: count($userRows),
            canonicalPositionCount: count($canonicalPositionRows),
            aliasPositionCount: count($aliasPositionRows),
            decisions: [
                'password_strategy' => $execution['password_policy']->configuredStrategy,
                'effective_password_strategy' => $execution['password_policy']->effectiveStrategy,
                'development_password_override' => $execution['password_policy']->usesDevelopmentOverride()
                    ? 'enabled'
                    : 'disabled',
                'force_password_change' => $execution['password_policy']->mustChangePassword
                    ? 'yes'
                    : 'no',
                'account_status_strategy' => $execution['account_status_strategy'],
                'file_sk_strategy' => $execution['file_sk_strategy'],
            ],
            validation: $postImport->toArray(),
        );
    }

    /**
     * @return array{
     *     insert_chunk_size: int,
     *     password_policy: LegacyUserImportPasswordPolicy,
     *     account_status_strategy: string,
     *     file_sk_strategy: string,
     *     advisory_lock_name: string,
     *     advisory_lock_timeout_seconds: int,
     *     transaction_attempts: int
     * }
     */
    private function executionConfiguration(): array
    {
        $configuration = config('legacy_import.execution', []);

        if (($configuration['enabled'] ?? false) !== true) {
            throw new LegacyUserImportBlockedException('execution_configuration', [
                'legacy_import.execution.enabled belum diaktifkan.',
            ]);
        }

        $pendingDecisions = array_values(config('legacy_import.pending_decisions', []));

        if ($pendingDecisions !== []) {
            throw new LegacyUserImportBlockedException('execution_configuration', $pendingDecisions);
        }

        $decisions = is_array($configuration['decisions'] ?? null)
            ? $configuration['decisions']
            : [];
        $accountStatusStrategy = (string) ($decisions['account_status_strategy'] ?? '');
        $fileSkStrategy = (string) ($decisions['file_sk_strategy'] ?? '');
        $passwordPolicy = $this->passwordPolicyResolver->resolveConfigured();
        $blockers = [];

        $this->validateStrategy(
            $blockers,
            'account_status_strategy',
            $accountStatusStrategy,
            [LegacyUserAccountStatusResolver::Strategy],
        );
        $this->validateStrategy(
            $blockers,
            'file_sk_strategy',
            $fileSkStrategy,
            self::FileSkStrategies,
        );

        $insertChunkSize = (int) ($configuration['insert_chunk_size'] ?? 250);
        $lockName = trim((string) ($configuration['advisory_lock_name'] ?? ''));
        $lockTimeout = (int) ($configuration['advisory_lock_timeout_seconds'] ?? 0);
        $transactionAttempts = (int) ($configuration['transaction_attempts'] ?? 1);

        if ($insertChunkSize < 50 || $insertChunkSize > 1000) {
            $blockers[] = 'legacy_import.execution.insert_chunk_size harus antara 50 dan 1000.';
        }

        if ($lockName === '' || mb_strlen($lockName) > 64) {
            $blockers[] = 'legacy_import.execution.advisory_lock_name wajib berisi maksimal 64 karakter.';
        }

        if ($lockTimeout < 0 || $lockTimeout > 300) {
            $blockers[] = 'legacy_import.execution.advisory_lock_timeout_seconds harus antara 0 dan 300.';
        }

        if ($transactionAttempts < 1 || $transactionAttempts > 3) {
            $blockers[] = 'legacy_import.execution.transaction_attempts harus antara 1 dan 3.';
        }

        $this->throwWhenBlocked('execution_configuration', $blockers);

        return [
            'insert_chunk_size' => $insertChunkSize,
            'password_policy' => $passwordPolicy,
            'account_status_strategy' => $accountStatusStrategy,
            'file_sk_strategy' => $fileSkStrategy,
            'advisory_lock_name' => $lockName,
            'advisory_lock_timeout_seconds' => $lockTimeout,
            'transaction_attempts' => $transactionAttempts,
        ];
    }

    /**
     * @param  list<string>  $blockers
     * @param  list<string>  $supportedStrategies
     */
    private function validateStrategy(
        array &$blockers,
        string $name,
        string $strategy,
        array $supportedStrategies,
    ): void {
        if (! in_array($strategy, $supportedStrategies, true)) {
            $blockers[] = "legacy_import.execution.decisions.{$name} belum valid.";
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function userRows(
        LegacyUserAccountAggregation $aggregation,
        LegacyUserImportPasswordPolicy $passwordPolicy,
        LegacyUserAccountStatusResolution $accountStatusResolution,
        CarbonImmutable $importedAt,
    ): array {
        $rows = [];
        $invalidPasswordAccountIds = [];

        foreach ($aggregation->accounts as $account) {
            $passwordHash = $passwordPolicy->passwordHashFor($account);

            if (! $this->isSupportedPasswordHash($passwordHash)) {
                $invalidPasswordAccountIds[] = $account->id;
            }

            $accountStatus = $accountStatusResolution->forUser($account->id);

            if ($accountStatus === null) {
                throw new LegacyUserImportBlockedException('account_status_projection', [
                    "Proyeksi status tidak ditemukan untuk account ID {$account->id}.",
                ]);
            }

            $rows[] = [
                'id' => $account->id,
                'nik' => $account->nik,
                'nip' => $account->nip,
                'nama' => $account->name,
                'email' => $account->email,
                'account_type' => User::ACCOUNT_TYPE_PERSONAL,
                'status' => $accountStatus->status,
                'status_reason' => $accountStatus->reason,
                'status_changed_at' => $importedAt->format('Y-m-d H:i:s'),
                'status_changed_by_user_id' => null,
                'password' => $passwordHash,
                'must_change_password' => $passwordPolicy->mustChangePassword,
                'source_system' => 'legacy',
                'external_id' => (string) $account->id,
                'last_synced_at' => $importedAt->format('Y-m-d H:i:s'),
                'created_at' => $account->sourceCreatedAt?->format('Y-m-d H:i:s'),
                'updated_at' => $account->sourceUpdatedAt?->format('Y-m-d H:i:s'),
            ];
        }

        if ($invalidPasswordAccountIds !== []) {
            sort($invalidPasswordAccountIds, SORT_NUMERIC);

            throw new LegacyUserImportBlockedException('password_projection', [
                'Hash password legacy tidak dikenali pada account ID: '.implode(', ', $invalidPasswordAccountIds).'.',
            ]);
        }

        return $rows;
    }

    private function isSupportedPasswordHash(string $passwordHash): bool
    {
        $passwordInformation = password_get_info($passwordHash);

        return ($passwordInformation['algoName'] ?? 'unknown') !== 'unknown';
    }

    /**
     * @return array{list<array<string, mixed>>, list<array<string, mixed>>}
     */
    private function positionRows(
        LegacyUserPositionClassification $classification,
        CarbonImmutable $importedAt,
    ): array {
        $canonicalRows = [];
        $aliasRows = [];

        foreach ($classification->positions as $position) {
            $row = $this->positionRow($position, $importedAt);

            if ($position->isCanonical) {
                $canonicalRows[] = $row;
            } else {
                $aliasRows[] = $row;
            }
        }

        return [$canonicalRows, $aliasRows];
    }

    /** @return array<string, mixed> */
    private function positionRow(
        LegacyUserPositionProjection $position,
        CarbonImmutable $importedAt,
    ): array {
        return [
            'id' => $position->id,
            'user_id' => $position->userId,
            'jabatan_id' => $position->jabatanId,
            'instansi_id' => $position->instansiId,
            'unit_kerja_id' => $position->unitKerjaId,
            'is_active' => $position->isActive,
            'is_canonical' => $position->isCanonical,
            'canonical_user_position_id' => $position->canonicalUserPositionId,
            'legacy_duplicate_reason' => $position->legacyDuplicateReason,
            'ended_at' => $position->endedAt?->toDateString(),
            'deactivated_at' => $position->deactivatedAt?->format('Y-m-d H:i:s'),
            'deactivation_reason' => $position->deactivationReason,
            'source_system' => 'legacy',
            'external_id' => (string) $position->id,
            'last_synced_at' => $importedAt->format('Y-m-d H:i:s'),
            'created_at' => $position->sourceCreatedAt?->format('Y-m-d H:i:s'),
            'updated_at' => $position->sourceUpdatedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $position->resultDeletedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertChunks(
        Connection $connection,
        string $table,
        array $rows,
        int $chunkSize,
    ): void {
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $connection->table($table)->insert($chunk);
        }
    }

    private function assertSupportedConnection(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'mysql') {
            throw new LegacyUserImportBlockedException('database_connection', [
                'Import transaksional legacy hanya didukung pada koneksi target MySQL.',
            ]);
        }
    }

    private function assertExpectedSourceFingerprint(
        string $actualFingerprint,
        string $expectedFingerprint,
    ): void {
        $actualFingerprint = Str::lower(trim($actualFingerprint));
        $expectedFingerprint = Str::lower(trim($expectedFingerprint));

        if (preg_match('/\A[a-f0-9]{64}\z/', $expectedFingerprint) !== 1) {
            throw new LegacyUserImportBlockedException('source_fingerprint', [
                'Fingerprint persetujuan operator bukan SHA-256 penuh yang valid.',
            ]);
        }

        if (preg_match('/\A[a-f0-9]{64}\z/', $actualFingerprint) !== 1) {
            throw new LegacyUserImportBlockedException('source_fingerprint', [
                'Plan import tidak menghasilkan fingerprint SHA-256 yang valid.',
            ]);
        }

        if (! hash_equals($expectedFingerprint, $actualFingerprint)) {
            throw new LegacyUserImportBlockedException('source_fingerprint', [
                'Fingerprint source berubah setelah guard command disetujui.',
            ]);
        }
    }

    private function acquireAdvisoryLock(Connection $connection, string $lockName, int $timeout): void
    {
        $result = $connection->selectOne(
            'SELECT GET_LOCK(?, ?) AS acquired',
            [$lockName, $timeout],
        );

        if ((int) ($result->acquired ?? 0) !== 1) {
            throw new LegacyUserImportBlockedException('advisory_lock', [
                'Proses import legacy lain sedang berjalan atau advisory lock tidak tersedia.',
            ]);
        }
    }

    private function releaseAdvisoryLock(Connection $connection, string $lockName): void
    {
        try {
            $result = $connection->selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                [$lockName],
            );

            if ((int) ($result->released ?? 0) !== 1) {
                Log::warning('Legacy user import advisory lock was not released by this connection.', [
                    'lock_name' => $lockName,
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param list<string> $blockers */
    private function throwWhenBlocked(string $stage, array $blockers): void
    {
        if ($blockers !== []) {
            throw new LegacyUserImportBlockedException($stage, $blockers);
        }
    }
}
