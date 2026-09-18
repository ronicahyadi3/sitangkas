<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyUserAccount;
use App\Data\LegacyImport\LegacyUserAccountAggregation;
use App\Data\LegacyImport\LegacyUserAccountStatusResolution;
use App\Data\LegacyImport\LegacyUserImportPasswordPolicy;
use App\Data\LegacyImport\LegacyUserImportValidation;
use App\Data\LegacyImport\LegacyUserPositionClassification;
use App\Data\LegacyImport\LegacyUserPositionProjection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

final class LegacyUserImportValidator
{
    public function __construct(
        private DatabaseManager $database,
        private ?string $fingerprintKey = null,
    ) {}

    /**
     * @param  array{
     *     source: array<string, mixed>,
     *     accounts: array<string, mixed>,
     *     identity: array<string, mixed>,
     *     organization: array<string, mixed>,
     *     positions: array<string, mixed>,
     *     status: array<string, mixed>
     * }  $analysis
     */
    public function validateBeforeImport(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
        LegacyUserAccountStatusResolution $accountStatusResolution,
        LegacyUserImportPasswordPolicy $passwordPolicy,
        array $analysis,
    ): LegacyUserImportValidation {
        $projectionValidation = $this->validateProjection($accountAggregation, $positionClassification);
        $accountStatusValidation = $this->validateAccountStatusResolution(
            $accountAggregation,
            $positionClassification,
            $accountStatusResolution,
        );
        $passwordValidation = $this->validatePasswordPolicy($accountAggregation, $passwordPolicy);
        $checks = [
            ...$projectionValidation->checks,
            ...$accountStatusValidation->checks,
            ...$passwordValidation->checks,
        ];
        $blockers = [
            ...$projectionValidation->blockers,
            ...$accountStatusValidation->blockers,
            ...$passwordValidation->blockers,
        ];
        $projection = $projectionValidation->metrics['projection'];
        $target = $this->targetPreflightMetrics($accountAggregation, $positionClassification);

        $this->addSnapshotChecks($checks, $blockers, $analysis);
        $this->addAnalysisInvariantChecks($checks, $blockers, $analysis);
        $this->addZeroChecks($checks, $blockers, [
            'target.current_users' => $target['current_users_count'],
            'target.current_user_positions' => $target['current_user_positions_count'],
            'target.colliding_user_ids' => count($target['colliding_user_ids']),
            'target.colliding_position_ids' => count($target['colliding_position_ids']),
            'history.document_uploaded_by_uncovered' => count($target['uncovered_document_uploaded_by_ids']),
            'history.document_users_to_uncovered' => count($target['uncovered_document_users_to_ids']),
            'history.document_process_id_user_uncovered' => count($target['uncovered_document_process_user_ids']),
        ], 'preflight target read-only');

        return new LegacyUserImportValidation(
            stage: 'before_import',
            checks: $checks,
            blockers: $blockers,
            metrics: [
                'projection' => $projection,
                'account_status' => $accountStatusValidation->metrics['account_status'],
                'password_policy' => $passwordValidation->metrics['password_policy'],
                'target' => $target,
            ],
        );
    }

    /**
     * Validate rows inserted by a future importer. This method only performs
     * SELECT queries and is safe to call before a transaction is committed.
     */
    public function validateAfterImport(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
        LegacyUserAccountStatusResolution $accountStatusResolution,
        LegacyUserImportPasswordPolicy $passwordPolicy,
    ): LegacyUserImportValidation {
        $projectionValidation = $this->validateProjection($accountAggregation, $positionClassification);
        $accountStatusValidation = $this->validateAccountStatusResolution(
            $accountAggregation,
            $positionClassification,
            $accountStatusResolution,
        );
        $checks = [...$projectionValidation->checks, ...$accountStatusValidation->checks];
        $blockers = [...$projectionValidation->blockers, ...$accountStatusValidation->blockers];
        $projection = $projectionValidation->metrics['projection'];
        $target = $this->importedTargetMetrics(
            $accountAggregation,
            $positionClassification,
            $accountStatusResolution,
            $passwordPolicy,
        );

        $this->addCheck(
            $checks,
            $blockers,
            'target.users_count',
            $target['expected_users_count'],
            $target['actual_users_count'],
            'jumlah akun hasil import',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'target.user_positions_count',
            $target['expected_user_positions_count'],
            $target['actual_user_positions_count'],
            'jumlah posisi hasil import',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'target.users_sha256',
            $target['expected_users_sha256'],
            $target['actual_users_sha256'],
            'fingerprint akun hasil import',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'target.user_positions_sha256',
            $target['expected_user_positions_sha256'],
            $target['actual_user_positions_sha256'],
            'fingerprint posisi hasil import',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'target.combined_sha256',
            $target['expected_combined_sha256'],
            $target['actual_combined_sha256'],
            'fingerprint gabungan target hasil import',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'target.users_max_id',
            $target['expected_users_max_id'],
            $target['actual_users_max_id'],
            'ID maksimum akun hasil import',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'target.user_positions_max_id',
            $target['expected_user_positions_max_id'],
            $target['actual_user_positions_max_id'],
            'ID maksimum posisi hasil import',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'target.users_id_auto_increment',
            1,
            $target['users_id_auto_increment'] ? 1 : 0,
            'struktur AUTO_INCREMENT akun',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'target.user_positions_id_auto_increment',
            1,
            $target['user_positions_id_auto_increment'] ? 1 : 0,
            'struktur AUTO_INCREMENT posisi',
        );

        $this->addZeroChecks($checks, $blockers, [
            'target.missing_user_ids' => count($target['missing_user_ids']),
            'target.unexpected_user_ids' => count($target['unexpected_user_ids']),
            'target.user_projection_mismatches' => count($target['user_projection_mismatch_ids']),
            'target.user_status_projection_mismatches' => count($target['user_status_projection_mismatch_ids']),
            'target.missing_user_status_changed_at' => count($target['missing_user_status_changed_at_ids']),
            'target.unexpected_user_status_changed_by' => count($target['unexpected_user_status_changed_by_ids']),
            'target.blank_user_passwords' => count($target['blank_user_password_ids']),
            'target.user_password_projection_mismatches' => count($target['user_password_projection_mismatch_ids']),
            'target.missing_position_ids' => count($target['missing_position_ids']),
            'target.unexpected_position_ids' => count($target['unexpected_position_ids']),
            'target.position_projection_mismatches' => count($target['position_projection_mismatch_ids']),
            'target.positions_without_account' => count($target['position_ids_without_account']),
            'target.accounts_without_matching_position_id' => count($target['account_ids_without_matching_position']),
            'history.document_uploaded_by_orphans' => count($target['orphan_document_uploaded_by_ids']),
            'history.document_users_to_orphans' => count($target['orphan_document_users_to_ids']),
            'history.document_process_id_user_orphans' => count($target['orphan_document_process_user_ids']),
        ], 'invariant target pasca-import');

        return new LegacyUserImportValidation(
            stage: 'after_import',
            checks: $checks,
            blockers: $blockers,
            metrics: [
                'projection' => $projection,
                'account_status' => $accountStatusValidation->metrics['account_status'],
                'target' => $target,
            ],
        );
    }

    public function validateProjection(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
    ): LegacyUserImportValidation {
        $checks = [];
        $blockers = [];
        $projection = $this->projectionMetrics($accountAggregation, $positionClassification);

        $this->addZeroChecks($checks, $blockers, [
            'projection.duplicate_account_niks' => count($projection['duplicate_account_nik_fingerprints']),
            'projection.duplicate_account_emails' => count($projection['duplicate_account_email_fingerprints']),
            'projection.blank_account_names' => count($projection['blank_account_name_ids']),
            'projection.blank_account_passwords' => count($projection['blank_account_password_ids']),
            'projection.invalid_account_emails' => count($projection['invalid_account_email_ids']),
            'projection.positions_without_account' => count($projection['position_ids_without_account']),
            'projection.accounts_without_matching_position_id' => count($projection['account_ids_without_matching_position']),
            'projection.invalid_canonical_shapes' => count($projection['invalid_canonical_position_ids']),
            'projection.invalid_alias_shapes' => count($projection['invalid_alias_position_ids']),
            'projection.aliases_without_valid_target' => count($projection['alias_ids_without_valid_target']),
        ], 'invariant proyeksi');

        return new LegacyUserImportValidation(
            stage: 'projection',
            checks: $checks,
            blockers: $blockers,
            metrics: ['projection' => $projection],
        );
    }

    public function validateAccountStatusResolution(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
        LegacyUserAccountStatusResolution $accountStatusResolution,
    ): LegacyUserImportValidation {
        $checks = [];
        $blockers = [];
        $accountIds = [];
        $activeCanonicalPositionByUserId = [];

        foreach ($accountAggregation->accounts as $account) {
            $accountIds[$account->id] = $account;
        }

        foreach ($positionClassification->positions as $position) {
            if ($position->isCanonical && $position->isActive && $position->resultDeletedAt === null) {
                $activeCanonicalPositionByUserId[$position->userId] = true;
            }
        }

        $resolvedIds = [];
        $projectionMismatchIds = [];

        foreach ($accountStatusResolution->statuses() as $status) {
            $resolvedIds[$status->userId] = true;
            $account = $accountIds[$status->userId] ?? null;

            if (! $account instanceof LegacyUserAccount) {
                continue;
            }

            $hasActiveCanonicalPosition = isset($activeCanonicalPositionByUserId[$account->id]);
            $expectedStatus = $hasActiveCanonicalPosition
                ? User::STATUS_ACTIVE
                : User::STATUS_INACTIVE;
            $expectedReason = match (true) {
                $hasActiveCanonicalPosition => LegacyUserAccountStatusResolver::ActiveReason,
                $account->allSourceRowsDeleted => LegacyUserAccountStatusResolver::AllPositionsDeletedReason,
                default => LegacyUserAccountStatusResolver::InactiveReason,
            };

            if (
                $status->status !== $expectedStatus
                || $status->reason !== $expectedReason
                || $status->hasActiveCanonicalPosition !== $hasActiveCanonicalPosition
                || $status->allSourceRowsDeleted !== $account->allSourceRowsDeleted
            ) {
                $projectionMismatchIds[] = $account->id;
            }
        }

        $missingAccountIds = array_values(array_diff(array_keys($accountIds), array_keys($resolvedIds)));
        $unexpectedAccountIds = array_values(array_diff(array_keys($resolvedIds), array_keys($accountIds)));
        $metrics = [
            ...$accountStatusResolution->toAnalysis(),
            'missing_account_ids' => $this->sortedUniqueIds($missingAccountIds),
            'unexpected_account_ids' => $this->sortedUniqueIds($unexpectedAccountIds),
            'projection_mismatch_ids' => $this->sortedUniqueIds($projectionMismatchIds),
        ];
        $expected = config('legacy_import.users.expected', []);

        $this->addCheck(
            $checks,
            $blockers,
            'account_status.strategy',
            LegacyUserAccountStatusResolver::Strategy,
            $accountStatusResolution->strategy,
            'strategi status akun yang disetujui',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'account_status.active_account_count',
            (int) ($expected['active_account_count'] ?? 0),
            $metrics['active_account_count'],
            'snapshot status akun yang disetujui',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'account_status.inactive_account_count',
            (int) ($expected['inactive_account_count'] ?? 0),
            $metrics['inactive_account_count'],
            'snapshot status akun yang disetujui',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'account_status.inactive_non_deleted_account_count',
            (int) ($expected['inactive_non_deleted_account_count'] ?? 0),
            $metrics['inactive_non_deleted_account_count'],
            'snapshot status akun yang disetujui',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'account_status.all_source_rows_deleted_account_count',
            (int) ($expected['all_source_rows_deleted_account_count'] ?? 0),
            $metrics['all_source_rows_deleted_account_count'],
            'snapshot status akun yang disetujui',
        );
        $this->addCheck(
            $checks,
            $blockers,
            'account_status.resolution_sha256',
            (string) ($expected['account_status_resolution_sha256'] ?? ''),
            $metrics['resolution_sha256'],
            'snapshot status akun yang disetujui',
        );
        $this->addZeroChecks($checks, $blockers, [
            'account_status.missing_accounts' => count($metrics['missing_account_ids']),
            'account_status.unexpected_accounts' => count($metrics['unexpected_account_ids']),
            'account_status.projection_mismatches' => count($metrics['projection_mismatch_ids']),
        ], 'invariant status akun');

        return new LegacyUserImportValidation(
            stage: 'account_status',
            checks: $checks,
            blockers: $blockers,
            metrics: ['account_status' => $metrics],
        );
    }

    public function validatePasswordPolicy(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserImportPasswordPolicy $passwordPolicy,
    ): LegacyUserImportValidation {
        $checks = [];
        $blockers = [];
        $invalidPasswordHashAccountIds = [];

        foreach ($accountAggregation->accounts as $account) {
            if (! $this->isSupportedPasswordHash($passwordPolicy->passwordHashFor($account))) {
                $invalidPasswordHashAccountIds[] = $account->id;
            }
        }

        $usesDevelopmentOverride = $passwordPolicy->usesDevelopmentOverride();
        $effectiveStrategyIsSupported = in_array($passwordPolicy->effectiveStrategy, [
            LegacyUserImportPasswordPolicyResolver::ProductionStrategy,
            LegacyUserImportPasswordPolicyResolver::DevelopmentStrategy,
        ], true);
        $developmentStrategyIsConsistent = $usesDevelopmentOverride
            === ($passwordPolicy->effectiveStrategy === LegacyUserImportPasswordPolicyResolver::DevelopmentStrategy);
        $productionForceChangeIsValid = $passwordPolicy->effectiveStrategy
            !== LegacyUserImportPasswordPolicyResolver::ProductionStrategy
            || $passwordPolicy->mustChangePassword;
        $developmentEnvironmentIsValid = ! $usesDevelopmentOverride
            || $passwordPolicy->resolvedEnvironment === 'local';

        $this->addCheck(
            $checks,
            $blockers,
            'password_policy.configured_strategy',
            LegacyUserImportPasswordPolicyResolver::ProductionStrategy,
            $passwordPolicy->configuredStrategy,
            'strategi produksi yang disetujui',
        );
        $this->addZeroChecks($checks, $blockers, [
            'password_policy.unsupported_effective_strategy' => $effectiveStrategyIsSupported ? 0 : 1,
            'password_policy.inconsistent_development_override' => $developmentStrategyIsConsistent ? 0 : 1,
            'password_policy.invalid_production_force_change' => $productionForceChangeIsValid ? 0 : 1,
            'password_policy.invalid_development_environment' => $developmentEnvironmentIsValid ? 0 : 1,
            'password_policy.invalid_projected_password_hashes' => count($invalidPasswordHashAccountIds),
        ], 'kebijakan password import');

        return new LegacyUserImportValidation(
            stage: 'password_policy',
            checks: $checks,
            blockers: $blockers,
            metrics: [
                'password_policy' => [
                    'configured_strategy' => $passwordPolicy->configuredStrategy,
                    'effective_strategy' => $passwordPolicy->effectiveStrategy,
                    'development_override' => $usesDevelopmentOverride ? 'enabled' : 'disabled',
                    'must_change_password' => $passwordPolicy->mustChangePassword,
                    'resolved_environment' => $passwordPolicy->resolvedEnvironment,
                    'projected_account_count' => count($accountAggregation->accounts),
                    'invalid_password_hash_account_ids' => $this->sortedUniqueIds($invalidPasswordHashAccountIds),
                ],
            ],
        );
    }

    /**
     * @param  list<array{name: string, expected: int|string, actual: int|string, passed: bool}>  $checks
     * @param  list<string>  $blockers
     * @param  array{source: array<string, mixed>, accounts: array<string, mixed>, identity: array<string, mixed>, organization: array<string, mixed>, positions: array<string, mixed>, status: array<string, mixed>}  $analysis
     */
    private function addSnapshotChecks(array &$checks, array &$blockers, array $analysis): void
    {
        $expected = config('legacy_import.users.expected', []);
        $expectedChecks = [
            'source.row_count' => [$expected['row_count'] ?? 0, $analysis['source']['row_count']],
            'source.distinct_nik_count' => [$expected['distinct_nik_count'] ?? 0, $analysis['source']['distinct_nik_count']],
            'source.minimum_id' => [$expected['minimum_id'] ?? 0, $analysis['source']['minimum_id'] ?? 0],
            'source.maximum_id' => [$expected['maximum_id'] ?? 0, $analysis['source']['maximum_id'] ?? 0],
            'accounts.account_count' => [$expected['account_count'] ?? 0, $analysis['accounts']['account_count']],
            'accounts.canonical_account_ids_sha256' => [$expected['canonical_account_ids_sha256'] ?? '', $analysis['accounts']['canonical_account_ids_sha256']],
            'positions.canonical_position_count' => [$expected['canonical_position_count'] ?? 0, $analysis['positions']['canonical_position_count']],
            'positions.alias_position_count' => [$expected['alias_position_count'] ?? 0, $analysis['positions']['alias_position_count']],
            'positions.classification_sha256' => [$expected['position_classification_sha256'] ?? '', $analysis['positions']['classification_sha256']],
            'positions.synthesized_alias_soft_delete_count' => [$expected['synthesized_alias_soft_delete_count'] ?? 0, $analysis['positions']['synthesized_alias_soft_delete_count']],
            'positions.duplicate_context_count' => [$expected['duplicate_context_count'] ?? 0, $analysis['positions']['duplicate_context_count']],
            'positions.active_duplicate_context_count' => [$expected['active_duplicate_context_count'] ?? 0, $analysis['positions']['active_duplicate_context_count']],
            'organization.allowed_correction_count' => [$expected['organization_correction_count'] ?? 0, $analysis['organization']['allowed_correction_count']],
            'source.used_columns_sha256' => [$expected['used_columns_sha256'] ?? '', $analysis['source']['used_columns_sha256']],
        ];

        foreach ($expectedChecks as $name => [$expectedValue, $actualValue]) {
            $this->addCheck($checks, $blockers, $name, $expectedValue, $actualValue, 'snapshot yang disetujui');
        }
    }

    /**
     * @param  list<array{name: string, expected: int|string, actual: int|string, passed: bool}>  $checks
     * @param  list<string>  $blockers
     * @param  array{source: array<string, mixed>, accounts: array<string, mixed>, identity: array<string, mixed>, organization: array<string, mixed>, positions: array<string, mixed>, status: array<string, mixed>}  $analysis
     */
    private function addAnalysisInvariantChecks(array &$checks, array &$blockers, array $analysis): void
    {
        $source = $analysis['source'];
        $accounts = $analysis['accounts'];
        $identity = $analysis['identity'];
        $organization = $analysis['organization'];
        $positions = $analysis['positions'];
        $status = $analysis['status'];

        $this->addZeroChecks($checks, $blockers, [
            'source.duplicate_ids' => $source['row_count'] - $source['unique_id_count'],
            'source.missing_expected_ids' => count($source['missing_expected_ids']),
            'source.unexpected_ids' => count($source['unexpected_ids']),
            'accounts.duplicate_account_ids' => $accounts['account_count'] - $accounts['unique_account_id_count'],
            'accounts.invalid_nik_rows' => count($accounts['invalid_nik_row_ids']),
            'identity.invalid_nik_rows' => count($identity['invalid_nik_row_ids']),
            'organization.missing_reference_rows' => $organization['missing_reference_row_count'],
            'organization.unexpected_mismatches' => $organization['unexpected_mismatch_count'],
            'positions.duplicate_position_ids' => $positions['classified_row_count'] - $positions['unique_position_id_count'],
            'positions.unaccounted_source_rows' => $source['row_count'] - $positions['classified_row_count'] - count($positions['unclassifiable_row_ids']),
            'positions.unclassifiable_rows' => count($positions['unclassifiable_row_ids']),
            'positions.missing_reconciliation_timestamps' => count($positions['missing_reconciliation_timestamp_row_ids']),
            'positions.invalid_canonical_contexts' => $positions['invalid_canonical_context_count'],
            'positions.accounts_without_canonical_position' => $positions['account_without_canonical_position_count'],
            'positions.aliases_without_canonical_target' => $positions['alias_without_canonical_target_count'],
            'status.unaccounted_accounts' => $accounts['account_count'] - $status['active_account_count'] - $status['inactive_account_count'],
        ], 'invariant import');
    }

    /** @return array<string, list<int|string>> */
    private function projectionMetrics(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
    ): array {
        $accountIds = [];
        $nikAccountIds = [];
        $emailAccountIds = [];
        $blankAccountNameIds = [];
        $blankAccountPasswordIds = [];
        $invalidAccountEmailIds = [];

        foreach ($accountAggregation->accounts as $account) {
            $accountIds[$account->id] = true;
            $nikAccountIds[$account->nik][] = $account->id;

            if ($account->email !== null) {
                $emailAccountIds[Str::lower($account->email)][] = $account->id;

                if (filter_var($account->email, FILTER_VALIDATE_EMAIL) === false) {
                    $invalidAccountEmailIds[] = $account->id;
                }
            }

            if (trim($account->name) === '') {
                $blankAccountNameIds[] = $account->id;
            }

            if ($account->passwordHash() === '') {
                $blankAccountPasswordIds[] = $account->id;
            }
        }

        $positionsById = [];
        $positionIdsWithoutAccount = [];
        $invalidCanonicalPositionIds = [];
        $invalidAliasPositionIds = [];

        foreach ($positionClassification->positions as $position) {
            $positionsById[$position->id] = $position;

            if (! isset($accountIds[$position->userId])) {
                $positionIdsWithoutAccount[] = $position->id;
            }

            if ($position->isCanonical) {
                if (
                    $position->canonicalUserPositionId !== null
                    || $position->legacyDuplicateReason !== null
                    || ($position->isActive && $position->resultDeletedAt !== null)
                ) {
                    $invalidCanonicalPositionIds[] = $position->id;
                }

                continue;
            }

            if (
                $position->canonicalUserPositionId === null
                || trim((string) $position->legacyDuplicateReason) === ''
                || $position->isActive
                || $position->resultDeletedAt === null
            ) {
                $invalidAliasPositionIds[] = $position->id;
            }
        }

        $accountIdsWithoutMatchingPosition = [];

        foreach (array_keys($accountIds) as $accountId) {
            $ownPosition = $positionsById[$accountId] ?? null;

            if (! $ownPosition instanceof LegacyUserPositionProjection || $ownPosition->userId !== $accountId) {
                $accountIdsWithoutMatchingPosition[] = $accountId;
            }
        }

        $aliasIdsWithoutValidTarget = [];

        foreach ($positionClassification->positions as $position) {
            if ($position->isCanonical) {
                continue;
            }

            $canonical = $positionsById[$position->canonicalUserPositionId ?? 0] ?? null;

            if (
                ! $canonical instanceof LegacyUserPositionProjection
                || ! $canonical->isCanonical
                || $canonical->contextKey() !== $position->contextKey()
            ) {
                $aliasIdsWithoutValidTarget[] = $position->id;
            }
        }

        return [
            'duplicate_account_nik_fingerprints' => $this->fingerprintedDuplicateKeys($nikAccountIds),
            'duplicate_account_email_fingerprints' => $this->fingerprintedDuplicateKeys($emailAccountIds),
            'blank_account_name_ids' => $this->sortedUniqueIds($blankAccountNameIds),
            'blank_account_password_ids' => $this->sortedUniqueIds($blankAccountPasswordIds),
            'invalid_account_email_ids' => $this->sortedUniqueIds($invalidAccountEmailIds),
            'position_ids_without_account' => $this->sortedUniqueIds($positionIdsWithoutAccount),
            'account_ids_without_matching_position' => $this->sortedUniqueIds($accountIdsWithoutMatchingPosition),
            'invalid_canonical_position_ids' => $this->sortedUniqueIds($invalidCanonicalPositionIds),
            'invalid_alias_position_ids' => $this->sortedUniqueIds($invalidAliasPositionIds),
            'alias_ids_without_valid_target' => $this->sortedUniqueIds($aliasIdsWithoutValidTarget),
        ];
    }

    /** @return array<string, mixed> */
    private function targetPreflightMetrics(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
    ): array {
        $connection = $this->database->connection();
        $accountIds = array_map(
            static fn (LegacyUserAccount $account): int => $account->id,
            $accountAggregation->accounts,
        );
        $positionIds = array_map(
            static fn (LegacyUserPositionProjection $position): int => $position->id,
            $positionClassification->positions,
        );

        return [
            'current_users_count' => $connection->table('users')->count(),
            'current_user_positions_count' => $connection->table('user_positions')->count(),
            'colliding_user_ids' => $this->existingIds($connection, 'users', $accountIds),
            'colliding_position_ids' => $this->existingIds($connection, 'user_positions', $positionIds),
            ...$this->historicalReferenceCoverage($connection, $positionIds, 'uncovered'),
        ];
    }

    /** @return array<string, mixed> */
    private function importedTargetMetrics(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
        LegacyUserAccountStatusResolution $accountStatusResolution,
        LegacyUserImportPasswordPolicy $passwordPolicy,
    ): array {
        $connection = $this->database->connection();
        $expectedAccountIds = array_map(
            static fn (LegacyUserAccount $account): int => $account->id,
            $accountAggregation->accounts,
        );
        $expectedPositionIds = array_map(
            static fn (LegacyUserPositionProjection $position): int => $position->id,
            $positionClassification->positions,
        );
        $targetUsers = $connection->table('users')->orderBy('id')->get([
            'id', 'nik', 'nip', 'nama', 'email', 'password', 'status',
            'status_reason', 'status_changed_at', 'status_changed_by_user_id',
            'account_type', 'must_change_password', 'source_system', 'external_id',
            'created_at', 'updated_at',
        ])->keyBy('id');
        $targetPositions = $connection->table('user_positions')->orderBy('id')->get([
            'id', 'user_id', 'jabatan_id', 'instansi_id', 'unit_kerja_id',
            'is_active', 'is_canonical', 'canonical_user_position_id',
            'legacy_duplicate_reason', 'ended_at', 'deactivated_at',
            'deactivation_reason', 'created_at', 'updated_at', 'deleted_at',
            'source_system', 'external_id',
        ])->keyBy('id');
        $targetAccountIds = $this->sortedUniqueIds($targetUsers->keys()->all());
        $targetPositionIds = $this->sortedUniqueIds($targetPositions->keys()->all());
        $expectedAccountIds = $this->sortedUniqueIds($expectedAccountIds);
        $expectedPositionIds = $this->sortedUniqueIds($expectedPositionIds);
        $targetStatus = $this->targetUserStatusMetrics(
            $accountStatusResolution,
            $targetUsers->all(),
        );
        $expectedUserRows = $this->expectedUserFingerprintRows(
            $accountAggregation,
            $accountStatusResolution,
            $passwordPolicy,
        );
        $actualUserRows = $this->actualUserFingerprintRows($targetUsers->all());
        $expectedPositionRows = $this->expectedPositionFingerprintRows($positionClassification);
        $actualPositionRows = $this->actualPositionFingerprintRows($targetPositions->all());
        $expectedUsersFingerprint = $this->fingerprintRows($expectedUserRows);
        $actualUsersFingerprint = $this->fingerprintRows($actualUserRows);
        $expectedPositionsFingerprint = $this->fingerprintRows($expectedPositionRows);
        $actualPositionsFingerprint = $this->fingerprintRows($actualPositionRows);

        return [
            'expected_users_count' => count($expectedAccountIds),
            'actual_users_count' => count($targetAccountIds),
            'expected_user_positions_count' => count($expectedPositionIds),
            'actual_user_positions_count' => count($targetPositionIds),
            'expected_users_sha256' => $expectedUsersFingerprint,
            'actual_users_sha256' => $actualUsersFingerprint,
            'expected_user_positions_sha256' => $expectedPositionsFingerprint,
            'actual_user_positions_sha256' => $actualPositionsFingerprint,
            'expected_combined_sha256' => $this->combinedTargetFingerprint(
                $expectedUsersFingerprint,
                $expectedPositionsFingerprint,
            ),
            'actual_combined_sha256' => $this->combinedTargetFingerprint(
                $actualUsersFingerprint,
                $actualPositionsFingerprint,
            ),
            'expected_users_max_id' => $this->maximumId($expectedAccountIds),
            'actual_users_max_id' => $this->maximumId($targetAccountIds),
            'required_users_next_id' => $this->nextIdAfter($expectedAccountIds),
            'users_id_auto_increment' => $this->idColumnIsAutoIncrement($connection, 'users'),
            'expected_user_positions_max_id' => $this->maximumId($expectedPositionIds),
            'actual_user_positions_max_id' => $this->maximumId($targetPositionIds),
            'required_user_positions_next_id' => $this->nextIdAfter($expectedPositionIds),
            'user_positions_id_auto_increment' => $this->idColumnIsAutoIncrement($connection, 'user_positions'),
            'missing_user_ids' => array_values(array_diff($expectedAccountIds, $targetAccountIds)),
            'unexpected_user_ids' => array_values(array_diff($targetAccountIds, $expectedAccountIds)),
            'user_projection_mismatch_ids' => $this->userProjectionMismatchIds($accountAggregation, $targetUsers->all()),
            ...$targetStatus,
            'blank_user_password_ids' => $this->blankTargetUserPasswordIds($targetUsers->all()),
            'user_password_projection_mismatch_ids' => $this->userPasswordProjectionMismatchIds(
                $accountAggregation,
                $passwordPolicy,
                $targetUsers->all(),
            ),
            'missing_position_ids' => array_values(array_diff($expectedPositionIds, $targetPositionIds)),
            'unexpected_position_ids' => array_values(array_diff($targetPositionIds, $expectedPositionIds)),
            'position_projection_mismatch_ids' => $this->positionProjectionMismatchIds($positionClassification, $targetPositions->all()),
            'position_ids_without_account' => $this->targetPositionIdsWithoutAccount($targetPositions->all(), $targetAccountIds),
            'account_ids_without_matching_position' => $this->targetAccountIdsWithoutMatchingPosition($targetAccountIds, $targetPositions->all()),
            ...$this->historicalReferenceCoverage($connection, $targetPositionIds, 'orphan'),
        ];
    }

    /**
     * @param  array<int|string, object>  $targetUsers
     * @return list<int>
     */
    private function userProjectionMismatchIds(
        LegacyUserAccountAggregation $accountAggregation,
        array $targetUsers,
    ): array {
        $mismatchIds = [];

        foreach ($accountAggregation->accounts as $account) {
            $target = $targetUsers[$account->id] ?? null;

            if (
                $target === null
                || (string) $target->nik !== $account->nik
                || $this->nullableString($target->nip) !== $account->nip
                || (string) $target->nama !== $account->name
                || $this->nullableString($target->email) !== $account->email
                || (string) $target->account_type !== User::ACCOUNT_TYPE_PERSONAL
                || (string) $target->source_system !== 'legacy'
                || (string) $target->external_id !== (string) $account->id
            ) {
                $mismatchIds[] = $account->id;
            }
        }

        return $this->sortedUniqueIds($mismatchIds);
    }

    /**
     * @param  array<int|string, object>  $targetUsers
     * @return array{
     *     user_status_projection_mismatch_ids: list<int>,
     *     missing_user_status_changed_at_ids: list<int>,
     *     unexpected_user_status_changed_by_ids: list<int>
     * }
     */
    private function targetUserStatusMetrics(
        LegacyUserAccountStatusResolution $accountStatusResolution,
        array $targetUsers,
    ): array {
        $projectionMismatchIds = [];
        $missingStatusChangedAtIds = [];
        $unexpectedStatusChangedByIds = [];

        foreach ($accountStatusResolution->statuses() as $status) {
            $target = $targetUsers[$status->userId] ?? null;

            if (
                $target === null
                || (string) $target->status !== $status->status
                || (string) $target->status_reason !== $status->reason
            ) {
                $projectionMismatchIds[] = $status->userId;
            }

            if ($target !== null && $target->status_changed_at === null) {
                $missingStatusChangedAtIds[] = $status->userId;
            }

            if ($target !== null && $target->status_changed_by_user_id !== null) {
                $unexpectedStatusChangedByIds[] = $status->userId;
            }
        }

        return [
            'user_status_projection_mismatch_ids' => $this->sortedUniqueIds($projectionMismatchIds),
            'missing_user_status_changed_at_ids' => $this->sortedUniqueIds($missingStatusChangedAtIds),
            'unexpected_user_status_changed_by_ids' => $this->sortedUniqueIds($unexpectedStatusChangedByIds),
        ];
    }

    /**
     * @param  array<int|string, object>  $targetUsers
     * @return list<int>
     */
    private function blankTargetUserPasswordIds(array $targetUsers): array
    {
        $userIds = [];

        foreach ($targetUsers as $user) {
            if ((string) $user->password === '') {
                $userIds[] = (int) $user->id;
            }
        }

        return $this->sortedUniqueIds($userIds);
    }

    /**
     * @param  array<int|string, object>  $targetUsers
     * @return list<int>
     */
    private function userPasswordProjectionMismatchIds(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserImportPasswordPolicy $passwordPolicy,
        array $targetUsers,
    ): array {
        $userIds = [];

        foreach ($accountAggregation->accounts as $account) {
            $target = $targetUsers[$account->id] ?? null;

            if (
                $target === null
                || ! hash_equals($passwordPolicy->passwordHashFor($account), (string) $target->password)
                || (bool) $target->must_change_password !== $passwordPolicy->mustChangePassword
            ) {
                $userIds[] = $account->id;
            }
        }

        return $this->sortedUniqueIds($userIds);
    }

    private function isSupportedPasswordHash(string $passwordHash): bool
    {
        $passwordInformation = password_get_info($passwordHash);

        return ($passwordInformation['algoName'] ?? 'unknown') !== 'unknown';
    }

    /**
     * @param  array<int|string, object>  $targetPositions
     * @return list<int>
     */
    private function positionProjectionMismatchIds(
        LegacyUserPositionClassification $positionClassification,
        array $targetPositions,
    ): array {
        $mismatchIds = [];

        foreach ($positionClassification->positions as $position) {
            $target = $targetPositions[$position->id] ?? null;

            if (
                $target === null
                || (int) $target->user_id !== $position->userId
                || (int) $target->jabatan_id !== $position->jabatanId
                || (int) $target->instansi_id !== $position->instansiId
                || (int) $target->unit_kerja_id !== $position->unitKerjaId
                || (bool) $target->is_active !== $position->isActive
                || (bool) $target->is_canonical !== $position->isCanonical
                || $this->nullableInteger($target->canonical_user_position_id) !== $position->canonicalUserPositionId
                || $this->nullableString($target->legacy_duplicate_reason) !== $position->legacyDuplicateReason
                || $this->dateString($target->ended_at) !== $this->dateString($position->endedAt)
                || $this->dateTimeString($target->deactivated_at) !== $this->dateTimeString($position->deactivatedAt)
                || $this->nullableString($target->deactivation_reason) !== $position->deactivationReason
                || (string) $target->source_system !== 'legacy'
                || (string) $target->external_id !== (string) $position->id
                || $this->dateTimeString($target->created_at) !== $this->dateTimeString($position->sourceCreatedAt)
                || $this->dateTimeString($target->updated_at) !== $this->dateTimeString($position->sourceUpdatedAt)
                || $this->dateTimeString($target->deleted_at) !== $this->dateTimeString($position->resultDeletedAt)
            ) {
                $mismatchIds[] = $position->id;
            }
        }

        return $this->sortedUniqueIds($mismatchIds);
    }

    /** @return list<array<string, mixed>> */
    private function expectedUserFingerprintRows(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserAccountStatusResolution $accountStatusResolution,
        LegacyUserImportPasswordPolicy $passwordPolicy,
    ): array {
        $rows = [];

        foreach ($accountAggregation->accounts as $account) {
            $status = $accountStatusResolution->forUser($account->id);
            $rows[] = [
                'id' => $account->id,
                'nik' => $account->nik,
                'nip' => $account->nip,
                'nama' => $account->name,
                'email' => $account->email,
                'account_type' => User::ACCOUNT_TYPE_PERSONAL,
                'status' => $status?->status,
                'status_reason' => $status?->reason,
                'must_change_password' => $passwordPolicy->mustChangePassword ? 1 : 0,
                'source_system' => 'legacy',
                'external_id' => (string) $account->id,
                'created_at' => $this->dateTimeString($account->sourceCreatedAt),
                'updated_at' => $this->dateTimeString($account->sourceUpdatedAt),
            ];
        }

        return $this->sortFingerprintRows($rows);
    }

    /**
     * @param  array<int|string, object>  $targetUsers
     * @return list<array<string, mixed>>
     */
    private function actualUserFingerprintRows(array $targetUsers): array
    {
        $rows = [];

        foreach ($targetUsers as $user) {
            $rows[] = [
                'id' => (int) $user->id,
                'nik' => (string) $user->nik,
                'nip' => $this->nullableString($user->nip),
                'nama' => (string) $user->nama,
                'email' => $this->nullableString($user->email),
                'account_type' => (string) $user->account_type,
                'status' => (string) $user->status,
                'status_reason' => $this->nullableString($user->status_reason),
                'must_change_password' => (bool) $user->must_change_password ? 1 : 0,
                'source_system' => (string) $user->source_system,
                'external_id' => (string) $user->external_id,
                'created_at' => $this->dateTimeString($user->created_at),
                'updated_at' => $this->dateTimeString($user->updated_at),
            ];
        }

        return $this->sortFingerprintRows($rows);
    }

    /** @return list<array<string, mixed>> */
    private function expectedPositionFingerprintRows(
        LegacyUserPositionClassification $positionClassification,
    ): array {
        $rows = [];

        foreach ($positionClassification->positions as $position) {
            $rows[] = [
                'id' => $position->id,
                'user_id' => $position->userId,
                'jabatan_id' => $position->jabatanId,
                'instansi_id' => $position->instansiId,
                'unit_kerja_id' => $position->unitKerjaId,
                'is_active' => $position->isActive ? 1 : 0,
                'is_canonical' => $position->isCanonical ? 1 : 0,
                'canonical_user_position_id' => $position->canonicalUserPositionId,
                'legacy_duplicate_reason' => $position->legacyDuplicateReason,
                'ended_at' => $this->dateString($position->endedAt),
                'deactivated_at' => $this->dateTimeString($position->deactivatedAt),
                'deactivation_reason' => $position->deactivationReason,
                'source_system' => 'legacy',
                'external_id' => (string) $position->id,
                'created_at' => $this->dateTimeString($position->sourceCreatedAt),
                'updated_at' => $this->dateTimeString($position->sourceUpdatedAt),
                'deleted_at' => $this->dateTimeString($position->resultDeletedAt),
            ];
        }

        return $this->sortFingerprintRows($rows);
    }

    /**
     * @param  array<int|string, object>  $targetPositions
     * @return list<array<string, mixed>>
     */
    private function actualPositionFingerprintRows(array $targetPositions): array
    {
        $rows = [];

        foreach ($targetPositions as $position) {
            $rows[] = [
                'id' => (int) $position->id,
                'user_id' => (int) $position->user_id,
                'jabatan_id' => (int) $position->jabatan_id,
                'instansi_id' => (int) $position->instansi_id,
                'unit_kerja_id' => (int) $position->unit_kerja_id,
                'is_active' => (bool) $position->is_active ? 1 : 0,
                'is_canonical' => (bool) $position->is_canonical ? 1 : 0,
                'canonical_user_position_id' => $this->nullableInteger($position->canonical_user_position_id),
                'legacy_duplicate_reason' => $this->nullableString($position->legacy_duplicate_reason),
                'ended_at' => $this->dateString($position->ended_at),
                'deactivated_at' => $this->dateTimeString($position->deactivated_at),
                'deactivation_reason' => $this->nullableString($position->deactivation_reason),
                'source_system' => (string) $position->source_system,
                'external_id' => (string) $position->external_id,
                'created_at' => $this->dateTimeString($position->created_at),
                'updated_at' => $this->dateTimeString($position->updated_at),
                'deleted_at' => $this->dateTimeString($position->deleted_at),
            ];
        }

        return $this->sortFingerprintRows($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortFingerprintRows(array $rows): array
    {
        usort(
            $rows,
            static fn (array $left, array $right): int => $left['id'] <=> $right['id'],
        );

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    private function fingerprintRows(array $rows): string
    {
        $context = hash_init('sha256');

        foreach ($rows as $row) {
            hash_update(
                $context,
                json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
            );
        }

        return hash_final($context);
    }

    private function combinedTargetFingerprint(string $usersFingerprint, string $positionsFingerprint): string
    {
        return hash('sha256', "users:{$usersFingerprint}\nuser_positions:{$positionsFingerprint}\n");
    }

    /** @param list<int> $ids */
    private function nextIdAfter(array $ids): int
    {
        return $ids === [] ? 1 : max($ids) + 1;
    }

    /** @param list<int> $ids */
    private function maximumId(array $ids): int
    {
        return $ids === [] ? 0 : max($ids);
    }

    private function idColumnIsAutoIncrement(Connection $connection, string $table): bool
    {
        $result = $connection->selectOne(
            'SELECT EXTRA AS column_extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$connection->getDatabaseName(), $table, 'id'],
        );

        return str_contains(Str::lower((string) ($result->column_extra ?? '')), 'auto_increment');
    }

    /**
     * @param  array<int|string, object>  $targetPositions
     * @param  list<int>  $targetAccountIds
     * @return list<int>
     */
    private function targetPositionIdsWithoutAccount(array $targetPositions, array $targetAccountIds): array
    {
        $accountIds = array_fill_keys($targetAccountIds, true);
        $positionIds = [];

        foreach ($targetPositions as $position) {
            if (! isset($accountIds[(int) $position->user_id])) {
                $positionIds[] = (int) $position->id;
            }
        }

        return $this->sortedUniqueIds($positionIds);
    }

    /**
     * @param  list<int>  $targetAccountIds
     * @param  array<int|string, object>  $targetPositions
     * @return list<int>
     */
    private function targetAccountIdsWithoutMatchingPosition(array $targetAccountIds, array $targetPositions): array
    {
        $accountIds = [];

        foreach ($targetAccountIds as $accountId) {
            $position = $targetPositions[$accountId] ?? null;

            if ($position === null || (int) $position->user_id !== $accountId) {
                $accountIds[] = $accountId;
            }
        }

        return $accountIds;
    }

    /**
     * @param  list<int>  $validPositionIds
     * @return array<string, int|list<int>>
     */
    private function historicalReferenceCoverage(
        Connection $connection,
        array $validPositionIds,
        string $prefix,
    ): array {
        $validIds = array_fill_keys($validPositionIds, true);
        $documentUploadedBy = $this->referenceCoverage($connection, 'document', 'uploaded_by', $validIds);
        $documentUsersTo = $this->referenceCoverage($connection, 'document', 'users_to', $validIds);
        $documentProcessUser = $this->referenceCoverage($connection, 'document_process', 'id_user', $validIds);

        return [
            $prefix.'_document_uploaded_by_reference_count' => $documentUploadedBy['reference_count'],
            $prefix.'_document_uploaded_by_covered_count' => $documentUploadedBy['covered_count'],
            $prefix.'_document_uploaded_by_ids' => $documentUploadedBy['uncovered_ids'],
            $prefix.'_document_users_to_reference_count' => $documentUsersTo['reference_count'],
            $prefix.'_document_users_to_covered_count' => $documentUsersTo['covered_count'],
            $prefix.'_document_users_to_ids' => $documentUsersTo['uncovered_ids'],
            $prefix.'_document_process_user_reference_count' => $documentProcessUser['reference_count'],
            $prefix.'_document_process_user_covered_count' => $documentProcessUser['covered_count'],
            $prefix.'_document_process_user_ids' => $documentProcessUser['uncovered_ids'],
        ];
    }

    /**
     * @param  array<int, true>  $validIds
     * @return array{reference_count: int, covered_count: int, uncovered_ids: list<int>}
     */
    private function referenceCoverage(
        Connection $connection,
        string $table,
        string $column,
        array $validIds,
    ): array {
        $referenceCount = 0;
        $coveredCount = 0;
        $uncoveredIds = [];
        $referenceIds = $connection->table($table)
            ->whereNotNull($column)
            ->distinct()
            ->pluck($column);

        foreach ($referenceIds as $referenceId) {
            $referenceId = (int) $referenceId;
            $referenceCount++;

            if (isset($validIds[$referenceId])) {
                $coveredCount++;
            } else {
                $uncoveredIds[] = $referenceId;
            }
        }

        return [
            'reference_count' => $referenceCount,
            'covered_count' => $coveredCount,
            'uncovered_ids' => $this->sortedUniqueIds($uncoveredIds),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function existingIds(Connection $connection, string $table, array $ids): array
    {
        $existingIds = [];

        foreach (array_chunk($ids, 500) as $idChunk) {
            foreach ($connection->table($table)->whereIn('id', $idChunk)->pluck('id') as $id) {
                $existingIds[] = (int) $id;
            }
        }

        return $this->sortedUniqueIds($existingIds);
    }

    /**
     * @param  array<string, list<int>>  $groups
     * @return list<string>
     */
    private function fingerprintedDuplicateKeys(array $groups): array
    {
        $keys = array_keys(array_filter(
            $groups,
            static fn (array $ids): bool => count($ids) > 1,
        ));
        $fingerprints = array_map(
            fn (string $value): string => hash_hmac(
                'sha256',
                $value,
                $this->fingerprintKey ?? (string) config('app.key'),
            ),
            $keys,
        );
        sort($fingerprints, SORT_STRING);

        return $fingerprints;
    }

    /**
     * @param  list<array{name: string, expected: int|string, actual: int|string, passed: bool}>  $checks
     * @param  list<string>  $blockers
     * @param  array<string, int>  $actualValues
     */
    private function addZeroChecks(
        array &$checks,
        array &$blockers,
        array $actualValues,
        string $rule,
    ): void {
        foreach ($actualValues as $name => $actualValue) {
            $this->addCheck($checks, $blockers, $name, 0, $actualValue, $rule);
        }
    }

    /**
     * @param  list<array{name: string, expected: int|string, actual: int|string, passed: bool}>  $checks
     * @param  list<string>  $blockers
     */
    private function addCheck(
        array &$checks,
        array &$blockers,
        string $name,
        int|string $expected,
        int|string $actual,
        string $rule,
    ): void {
        $passed = $expected === $actual;
        $checks[] = compact('name', 'expected', 'actual', 'passed');

        if (! $passed) {
            $blockers[] = "{$name} tidak sesuai {$rule}.";
        }
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function dateTimeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->format('Y-m-d H:i:s');
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->toDateString();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return list<int>
     */
    private function sortedUniqueIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }
}
