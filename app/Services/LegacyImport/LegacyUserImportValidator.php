<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyUserAccount;
use App\Data\LegacyImport\LegacyUserAccountAggregation;
use App\Data\LegacyImport\LegacyUserImportPasswordPolicy;
use App\Data\LegacyImport\LegacyUserImportValidation;
use App\Data\LegacyImport\LegacyUserPositionClassification;
use App\Data\LegacyImport\LegacyUserPositionProjection;
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
     *     positions: array<string, mixed>
     * }  $analysis
     */
    public function validateBeforeImport(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
        LegacyUserImportPasswordPolicy $passwordPolicy,
        array $analysis,
    ): LegacyUserImportValidation {
        $projectionValidation = $this->validateProjection($accountAggregation, $positionClassification);
        $passwordValidation = $this->validatePasswordPolicy($accountAggregation, $passwordPolicy);
        $checks = [...$projectionValidation->checks, ...$passwordValidation->checks];
        $blockers = [...$projectionValidation->blockers, ...$passwordValidation->blockers];
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
    ): LegacyUserImportValidation {
        $projectionValidation = $this->validateProjection($accountAggregation, $positionClassification);
        $checks = $projectionValidation->checks;
        $blockers = $projectionValidation->blockers;
        $projection = $projectionValidation->metrics['projection'];
        $target = $this->importedTargetMetrics($accountAggregation, $positionClassification);

        $this->addZeroChecks($checks, $blockers, [
            'target.missing_user_ids' => count($target['missing_user_ids']),
            'target.unexpected_user_ids' => count($target['unexpected_user_ids']),
            'target.user_projection_mismatches' => count($target['user_projection_mismatch_ids']),
            'target.blank_user_passwords' => count($target['blank_user_password_ids']),
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
     * @param  array{source: array<string, mixed>, accounts: array<string, mixed>, identity: array<string, mixed>, organization: array<string, mixed>, positions: array<string, mixed>}  $analysis
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
     * @param  array{source: array<string, mixed>, accounts: array<string, mixed>, identity: array<string, mixed>, organization: array<string, mixed>, positions: array<string, mixed>}  $analysis
     */
    private function addAnalysisInvariantChecks(array &$checks, array &$blockers, array $analysis): void
    {
        $source = $analysis['source'];
        $accounts = $analysis['accounts'];
        $identity = $analysis['identity'];
        $organization = $analysis['organization'];
        $positions = $analysis['positions'];

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
            'id', 'nik', 'nip', 'nama', 'email', 'password',
        ])->keyBy('id');
        $targetPositions = $connection->table('user_positions')->orderBy('id')->get([
            'id', 'user_id', 'jabatan_id', 'instansi_id', 'unit_kerja_id',
            'is_active', 'is_canonical', 'canonical_user_position_id',
            'legacy_duplicate_reason', 'ended_at', 'deactivated_at',
            'deactivation_reason', 'created_at', 'updated_at', 'deleted_at',
        ])->keyBy('id');
        $targetAccountIds = $this->sortedUniqueIds($targetUsers->keys()->all());
        $targetPositionIds = $this->sortedUniqueIds($targetPositions->keys()->all());
        $expectedAccountIds = $this->sortedUniqueIds($expectedAccountIds);
        $expectedPositionIds = $this->sortedUniqueIds($expectedPositionIds);

        return [
            'missing_user_ids' => array_values(array_diff($expectedAccountIds, $targetAccountIds)),
            'unexpected_user_ids' => array_values(array_diff($targetAccountIds, $expectedAccountIds)),
            'user_projection_mismatch_ids' => $this->userProjectionMismatchIds($accountAggregation, $targetUsers->all()),
            'blank_user_password_ids' => $this->blankTargetUserPasswordIds($targetUsers->all()),
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
            ) {
                $mismatchIds[] = $account->id;
            }
        }

        return $this->sortedUniqueIds($mismatchIds);
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
                || $this->dateTimeString($target->created_at) !== $this->dateTimeString($position->sourceCreatedAt)
                || $this->dateTimeString($target->updated_at) !== $this->dateTimeString($position->sourceUpdatedAt)
                || $this->dateTimeString($target->deleted_at) !== $this->dateTimeString($position->resultDeletedAt)
            ) {
                $mismatchIds[] = $position->id;
            }
        }

        return $this->sortedUniqueIds($mismatchIds);
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
     * @return array<string, list<int>>
     */
    private function historicalReferenceCoverage(
        Connection $connection,
        array $validPositionIds,
        string $prefix,
    ): array {
        $validIds = array_fill_keys($validPositionIds, true);

        return [
            $prefix.'_document_uploaded_by_ids' => $this->uncoveredReferenceIds($connection, 'document', 'uploaded_by', $validIds),
            $prefix.'_document_users_to_ids' => $this->uncoveredReferenceIds($connection, 'document', 'users_to', $validIds),
            $prefix.'_document_process_user_ids' => $this->uncoveredReferenceIds($connection, 'document_process', 'id_user', $validIds),
        ];
    }

    /**
     * @param  array<int, true>  $validIds
     * @return list<int>
     */
    private function uncoveredReferenceIds(
        Connection $connection,
        string $table,
        string $column,
        array $validIds,
    ): array {
        $uncoveredIds = [];
        $referenceIds = $connection->table($table)
            ->whereNotNull($column)
            ->distinct()
            ->pluck($column);

        foreach ($referenceIds as $referenceId) {
            $referenceId = (int) $referenceId;

            if (! isset($validIds[$referenceId])) {
                $uncoveredIds[] = $referenceId;
            }
        }

        return $this->sortedUniqueIds($uncoveredIds);
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
