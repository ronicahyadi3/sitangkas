<?php

namespace App\Actions\LegacyImport;

use App\Data\LegacyImport\LegacyOrganizationResolution;
use App\Data\LegacyImport\LegacyUserAccount;
use App\Data\LegacyImport\LegacyUserAccountAggregation;
use App\Data\LegacyImport\LegacyUserImportAnalysis;
use App\Data\LegacyImport\LegacyUserImportPasswordPolicy;
use App\Data\LegacyImport\LegacyUserImportPlan;
use App\Data\LegacyImport\LegacyUserPositionClassification;
use App\Data\LegacyImport\LegacyUserPositionProjection;
use App\Data\LegacyImport\LegacyUserRow;
use App\Services\LegacyImport\LegacyOrganizationResolver;
use App\Services\LegacyImport\LegacyUserAccountAggregator;
use App\Services\LegacyImport\LegacyUserImportPasswordPolicyResolver;
use App\Services\LegacyImport\LegacyUserImportValidator;
use App\Services\LegacyImport\LegacyUserPositionClassifier;
use App\Services\LegacyImport\LegacyUserSourceReader;
use HashContext;
use Illuminate\Support\Str;

final class AnalyzeLegacyUsers
{
    public function __construct(
        private LegacyUserSourceReader $sourceReader,
        private LegacyOrganizationResolver $organizationResolver,
        private LegacyUserAccountAggregator $accountAggregator,
        private LegacyUserPositionClassifier $positionClassifier,
        private LegacyUserImportValidator $validator,
        private LegacyUserImportPasswordPolicyResolver $passwordPolicyResolver,
    ) {}

    public function handle(int $chunkSize): LegacyUserImportAnalysis
    {
        return $this->plan($chunkSize)->analysis;
    }

    public function plan(
        int $chunkSize,
        ?LegacyUserImportPasswordPolicy $passwordPolicy = null,
    ): LegacyUserImportPlan {
        $passwordPolicy ??= $this->passwordPolicyResolver->resolveConfigured();
        $expected = config('legacy_import.users.expected', []);
        $sourceFingerprint = hash_init('sha256');

        $sourceIds = [];
        $accountSourceRows = [];
        $identityGroups = [];
        $emailGroups = [];
        $organizationResolutions = [];
        $invalidNikRowIds = [];
        $invalidNipRowIds = [];
        $blankEmailRowIds = [];
        $invalidEmailRowIds = [];
        $missingReferences = ['jabatan' => [], 'instansi' => [], 'unit_kerja' => []];
        $inactiveReferences = ['jabatan' => [], 'instansi' => [], 'unit_kerja' => []];
        $allowedOrganizationCorrections = [];
        $unexpectedOrganizationMismatches = [];
        $statusCounts = [];
        $deletedRowCount = 0;
        $sourceActiveRowCount = 0;

        foreach ($this->sourceReader->lazy($chunkSize) as $row) {
            $sourceIds[] = $row->id;
            $accountSourceRows[] = $row;
            $statusCounts[(string) $row->status] = ($statusCounts[(string) $row->status] ?? 0) + 1;
            $deletedRowCount += $row->deletedAt !== null ? 1 : 0;
            $sourceActiveRowCount += $this->isSourceActive($row) ? 1 : 0;

            $this->updateSourceFingerprint($sourceFingerprint, $row);

            $nik = trim((string) $row->nik);

            if (! preg_match('/^\d{16}$/', $nik)) {
                $invalidNikRowIds[] = $row->id;
            }

            if ($row->nip !== null && trim($row->nip) !== '' && ! preg_match('/^\d{18}$/', trim($row->nip))) {
                $invalidNipRowIds[] = $row->id;
            }

            if ($nik !== '') {
                $this->collectIdentityProfile($identityGroups, $nik, $row);
            }

            $this->collectEmail($emailGroups, $blankEmailRowIds, $invalidEmailRowIds, $nik, $row);

            $organizationResolution = $this->organizationResolver->resolve($row);
            $organizationResolutions[$row->id] = $organizationResolution;
            $this->collectOrganizationResolution(
                $organizationResolution,
                $row->id,
                $missingReferences,
                $inactiveReferences,
                $allowedOrganizationCorrections,
                $unexpectedOrganizationMismatches,
            );
        }

        sort($sourceIds);
        ksort($statusCounts, SORT_NUMERIC);

        $accountAggregation = $this->accountAggregator->aggregate($accountSourceRows);
        $positionClassification = $this->positionClassifier->classify(
            $accountSourceRows,
            $accountAggregation,
            $organizationResolutions,
        );
        $source = $this->sourceAnalysis($sourceIds, $identityGroups, $sourceFingerprint, $expected);
        $accounts = $this->accountAnalysis($accountAggregation);
        $identity = $this->identityAnalysis(
            $identityGroups,
            $emailGroups,
            $invalidNikRowIds,
            $invalidNipRowIds,
            $blankEmailRowIds,
            $invalidEmailRowIds,
        );
        $organization = $this->organizationAnalysis(
            $missingReferences,
            $inactiveReferences,
            $allowedOrganizationCorrections,
            $unexpectedOrganizationMismatches,
        );
        $positions = $this->positionAnalysis($positionClassification, $accountAggregation);
        $status = [
            'source_status_counts' => $statusCounts,
            'source_active_row_count' => $sourceActiveRowCount,
            'deleted_row_count' => $deletedRowCount,
            'nondeleted_row_count' => count($sourceIds) - $deletedRowCount,
        ];

        $validation = $this->validator->validateBeforeImport(
            $accountAggregation,
            $positionClassification,
            $passwordPolicy,
            compact('source', 'accounts', 'identity', 'organization', 'positions'),
        );

        $analysis = new LegacyUserImportAnalysis(
            generatedAt: now()->toIso8601String(),
            source: $source,
            accounts: $accounts,
            identity: $identity,
            organization: $organization,
            positions: $positions,
            status: $status,
            checks: $validation->checks,
            blockers: $validation->blockers,
            warnings: $this->warnings($identity, $organization, $positions),
            pendingDecisions: array_values(config('legacy_import.pending_decisions', [])),
            validation: $validation->toArray(),
        );

        return new LegacyUserImportPlan(
            analysis: $analysis,
            accountAggregation: $accountAggregation,
            positionClassification: $positionClassification,
        );
    }

    /** @return array<string, mixed> */
    private function accountAnalysis(LegacyUserAccountAggregation $aggregation): array
    {
        $accountIds = [];
        $selectionTierCounts = [];
        $activeCandidateCount = 0;
        $allSourceRowsDeletedCount = 0;
        $selectedNonstandardNipRowIds = [];
        $multiRowSelections = [];

        foreach ($aggregation->accounts as $account) {
            $accountIds[] = $account->id;
            $selectionTierCounts[$account->selectionTier] = ($selectionTierCounts[$account->selectionTier] ?? 0) + 1;
            $activeCandidateCount += $account->hasActiveSourceRow ? 1 : 0;
            $allSourceRowsDeletedCount += $account->allSourceRowsDeleted ? 1 : 0;

            if ($account->nip !== null && ! preg_match('/^\d{18}$/', $account->nip)) {
                $selectedNonstandardNipRowIds[] = $account->id;
            }

            if (count($account->sourceRowIds) > 1) {
                $multiRowSelections[] = [
                    'nik_fingerprint' => $this->fingerprint($account->nik),
                    'canonical_account_id' => $account->id,
                    'canonical_source_row_id' => $account->id,
                    'source_row_ids' => $account->sourceRowIds,
                    'conflicting_fields' => $account->conflictingFields,
                    'selection_tier' => $account->selectionTier,
                ];
            }
        }

        sort($accountIds, SORT_NUMERIC);
        sort($selectedNonstandardNipRowIds, SORT_NUMERIC);
        ksort($selectionTierCounts, SORT_STRING);

        return [
            'account_count' => count($aggregation->accounts),
            'unique_account_id_count' => count(array_unique($accountIds)),
            'canonical_account_ids_sha256' => hash('sha256', implode(',', $accountIds)),
            'selection_tier_counts' => $selectionTierCounts,
            'active_account_candidate_count' => $activeCandidateCount,
            'without_active_position_candidate_count' => count($aggregation->accounts) - $activeCandidateCount,
            'all_source_rows_deleted_account_count' => $allSourceRowsDeletedCount,
            'multi_row_account_count' => count($multiRowSelections),
            'multi_row_account_selections' => $multiRowSelections,
            'selected_nonstandard_nip_row_ids' => $selectedNonstandardNipRowIds,
            'invalid_nik_row_ids' => $aggregation->invalidNikRowIds,
        ];
    }

    private function updateSourceFingerprint(HashContext $sourceFingerprint, LegacyUserRow $row): void
    {
        hash_update($sourceFingerprint, json_encode([
            $row->id,
            $row->name,
            $row->status,
            $row->nip,
            $row->nik,
            $row->jabatanId,
            $row->instansiId,
            $row->unitKerjaId,
            $row->fileSk,
            $row->email,
            hash('sha256', $row->passwordHash()),
            $row->createdAt?->format('Y-m-d H:i:s'),
            $row->updatedAt?->format('Y-m-d H:i:s'),
            $row->deletedAt?->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR)."\n");
    }

    /** @param array<string, array<string, mixed>> $identityGroups */
    private function collectIdentityProfile(array &$identityGroups, string $nik, LegacyUserRow $row): void
    {
        $identityGroups[$nik]['row_ids'][] = $row->id;
        $identityGroups[$nik]['names'][$this->normalizedValue($row->name)] = true;

        if ($row->nip !== null && trim($row->nip) !== '') {
            $identityGroups[$nik]['nips'][trim($row->nip)] = true;
        }

        if (trim($row->email) !== '') {
            $identityGroups[$nik]['emails'][Str::lower(trim($row->email))] = true;
        }

        $identityGroups[$nik]['passwords'][hash('sha256', $row->passwordHash())] = true;
    }

    /**
     * @param  array<string, array{row_ids: list<int>, niks: array<string, true>}>  $emailGroups
     * @param  list<int>  $blankEmailRowIds
     * @param  list<int>  $invalidEmailRowIds
     */
    private function collectEmail(
        array &$emailGroups,
        array &$blankEmailRowIds,
        array &$invalidEmailRowIds,
        string $nik,
        LegacyUserRow $row,
    ): void {
        $email = Str::lower(trim($row->email));

        if ($email === '') {
            $blankEmailRowIds[] = $row->id;

            return;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $invalidEmailRowIds[] = $row->id;
        }

        $emailGroups[$email]['row_ids'][] = $row->id;

        if ($nik !== '') {
            $emailGroups[$email]['niks'][$nik] = true;
        }
    }

    /**
     * @param  array<string, array<int, list<int>>>  $missingReferences
     * @param  array<string, array<int, list<int>>>  $inactiveReferences
     * @param  list<array<string, int|null>>  $allowedOrganizationCorrections
     * @param  list<array<string, int|null>>  $unexpectedOrganizationMismatches
     */
    private function collectOrganizationResolution(
        LegacyOrganizationResolution $resolution,
        int $rowId,
        array &$missingReferences,
        array &$inactiveReferences,
        array &$allowedOrganizationCorrections,
        array &$unexpectedOrganizationMismatches,
    ): void {
        foreach ($resolution->missingReferences as $reference) {
            $missingReferences[$reference['type']][$reference['reference_id'] ?? 0][] = $rowId;
        }

        foreach ($resolution->inactiveReferences as $reference) {
            $inactiveReferences[$reference['type']][$reference['reference_id']][] = $rowId;
        }

        if ($resolution->allowedCorrection !== null) {
            $allowedOrganizationCorrections[] = $resolution->allowedCorrection;
        }

        if ($resolution->unexpectedMismatch !== null) {
            $unexpectedOrganizationMismatches[] = $resolution->unexpectedMismatch;
        }
    }

    /**
     * @param  list<int>  $sourceIds
     * @param  array<string, array<string, mixed>>  $identityGroups
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function sourceAnalysis(
        array $sourceIds,
        array $identityGroups,
        HashContext $sourceFingerprint,
        array $expected,
    ): array {
        $minimumId = $sourceIds !== [] ? min($sourceIds) : null;
        $maximumId = $sourceIds !== [] ? max($sourceIds) : null;
        $expectedMinimumId = (int) ($expected['minimum_id'] ?? 0);
        $expectedMaximumId = (int) ($expected['maximum_id'] ?? -1);
        $expectedIds = $expectedMaximumId >= $expectedMinimumId
            ? range($expectedMinimumId, $expectedMaximumId)
            : [];

        return [
            'connection' => 'legacy_import',
            'table' => 'users',
            'selected_column_count' => 14,
            'ignored_columns' => ['uuid', 'access'],
            'row_count' => count($sourceIds),
            'unique_id_count' => count(array_unique($sourceIds)),
            'minimum_id' => $minimumId,
            'maximum_id' => $maximumId,
            'missing_expected_ids' => array_values(array_diff($expectedIds, $sourceIds)),
            'unexpected_ids' => array_values(array_diff($sourceIds, $expectedIds)),
            'distinct_nik_count' => count($identityGroups),
            'used_columns_sha256' => hash_final($sourceFingerprint),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $identityGroups
     * @param  array<string, array{row_ids: list<int>, niks: array<string, true>}>  $emailGroups
     * @param  list<int>  $invalidNikRowIds
     * @param  list<int>  $invalidNipRowIds
     * @param  list<int>  $blankEmailRowIds
     * @param  list<int>  $invalidEmailRowIds
     * @return array<string, mixed>
     */
    private function identityAnalysis(
        array $identityGroups,
        array $emailGroups,
        array $invalidNikRowIds,
        array $invalidNipRowIds,
        array $blankEmailRowIds,
        array $invalidEmailRowIds,
    ): array {
        $duplicateNikGroupCount = 0;
        $profileConflicts = [];

        foreach ($identityGroups as $nik => $profile) {
            $rowIds = $profile['row_ids'] ?? [];
            $duplicateNikGroupCount += count($rowIds) > 1 ? 1 : 0;
            $conflictingFields = [];

            foreach (['names', 'nips', 'emails', 'passwords'] as $field) {
                if (count($profile[$field] ?? []) > 1) {
                    $conflictingFields[] = $field;
                }
            }

            if ($conflictingFields !== []) {
                $profileConflicts[] = [
                    'nik_fingerprint' => $this->fingerprint($nik),
                    'row_ids' => $rowIds,
                    'conflicting_fields' => $conflictingFields,
                ];
            }
        }

        $crossNikEmailConflicts = [];

        foreach ($emailGroups as $email => $group) {
            if (count($group['niks'] ?? []) <= 1) {
                continue;
            }

            $crossNikEmailConflicts[] = [
                'email_fingerprint' => $this->fingerprint($email),
                'row_ids' => $group['row_ids'],
                'distinct_nik_count' => count($group['niks']),
            ];
        }

        return [
            'invalid_nik_row_ids' => $invalidNikRowIds,
            'invalid_nip_row_ids' => $invalidNipRowIds,
            'blank_email_row_ids' => $blankEmailRowIds,
            'invalid_email_row_ids' => $invalidEmailRowIds,
            'duplicate_nik_group_count' => $duplicateNikGroupCount,
            'profile_conflict_group_count' => count($profileConflicts),
            'profile_conflicts' => $profileConflicts,
            'cross_nik_email_conflict_count' => count($crossNikEmailConflicts),
            'cross_nik_email_conflicts' => $crossNikEmailConflicts,
        ];
    }

    /**
     * @param  array<string, array<int, list<int>>>  $missingReferences
     * @param  array<string, array<int, list<int>>>  $inactiveReferences
     * @param  list<array<string, int|null>>  $allowedOrganizationCorrections
     * @param  list<array<string, int|null>>  $unexpectedOrganizationMismatches
     * @return array<string, mixed>
     */
    private function organizationAnalysis(
        array $missingReferences,
        array $inactiveReferences,
        array $allowedOrganizationCorrections,
        array $unexpectedOrganizationMismatches,
    ): array {
        return [
            'missing_references' => $this->sortedReferenceIssues($missingReferences),
            'missing_reference_row_count' => $this->referenceIssueRowCount($missingReferences),
            'inactive_references' => $this->sortedReferenceIssues($inactiveReferences),
            'inactive_reference_row_count' => $this->referenceIssueRowCount($inactiveReferences),
            'allowed_correction_count' => count($allowedOrganizationCorrections),
            'allowed_corrections' => $allowedOrganizationCorrections,
            'unexpected_mismatch_count' => count($unexpectedOrganizationMismatches),
            'unexpected_mismatches' => $unexpectedOrganizationMismatches,
        ];
    }

    /** @return array<string, mixed> */
    private function positionAnalysis(
        LegacyUserPositionClassification $classification,
        LegacyUserAccountAggregation $accountAggregation,
    ): array {
        $contextGroups = [];
        $positionIds = [];
        $positionsById = [];
        $canonicalPositionIds = [];
        $classificationFingerprint = hash_init('sha256');

        foreach ($classification->positions as $position) {
            $positionIds[] = $position->id;
            $positionsById[$position->id] = $position;
            $contextGroups[$position->contextKey()][] = $position;

            if ($position->isCanonical) {
                $canonicalPositionIds[$position->id] = true;
            }

            hash_update($classificationFingerprint, json_encode([
                $position->id,
                $position->userId,
                $position->jabatanId,
                $position->instansiId,
                $position->unitKerjaId,
                $position->isActive,
                $position->isCanonical,
                $position->canonicalUserPositionId,
                $position->legacyDuplicateReason,
                $position->resultDeletedAt?->format('Y-m-d H:i:s'),
                $position->endedAt?->format('Y-m-d H:i:s'),
                $position->deactivatedAt?->format('Y-m-d H:i:s'),
                $position->deactivationReason,
            ], JSON_THROW_ON_ERROR)."\n");
        }

        $duplicateContexts = [];
        $aliasPositionCount = 0;
        $activeDuplicateContextCount = 0;
        $activeDuplicateExtraRowCount = 0;
        $canonicalActivePositionCount = 0;
        $allDeletedContextCount = 0;
        $invalidCanonicalContextCount = 0;

        foreach ($contextGroups as $contextKey => $positions) {
            $canonicalPositions = array_values(array_filter(
                $positions,
                static fn (LegacyUserPositionProjection $position): bool => $position->isCanonical,
            ));
            $invalidCanonicalContextCount += count($canonicalPositions) === 1 ? 0 : 1;
            $canonical = $canonicalPositions[0] ?? null;
            $sourceActiveIds = array_values(array_map(
                static fn (LegacyUserPositionProjection $position): int => $position->id,
                array_filter(
                    $positions,
                    static fn (LegacyUserPositionProjection $position): bool => $position->wasSourceActive,
                ),
            ));
            $nondeletedCount = count(array_filter(
                $positions,
                static fn (LegacyUserPositionProjection $position): bool => $position->originalDeletedAt === null,
            ));

            $canonicalActivePositionCount += $canonical?->isActive ? 1 : 0;
            $allDeletedContextCount += $nondeletedCount === 0 ? 1 : 0;

            if (count($positions) <= 1 || $canonical === null) {
                continue;
            }

            $aliasIds = array_values(array_map(
                static fn (LegacyUserPositionProjection $position): int => $position->id,
                array_filter(
                    $positions,
                    static fn (LegacyUserPositionProjection $position): bool => ! $position->isCanonical,
                ),
            ));
            sort($aliasIds, SORT_NUMERIC);
            sort($sourceActiveIds, SORT_NUMERIC);

            $aliasPositionCount += count($aliasIds);
            $activeDuplicateContextCount += count($sourceActiveIds) > 1 ? 1 : 0;
            $activeDuplicateExtraRowCount += max(0, count($sourceActiveIds) - 1);
            $duplicateContexts[] = [
                'context_fingerprint' => $this->fingerprint($contextKey),
                'canonical_position_id' => $canonical->id,
                'alias_position_ids' => $aliasIds,
                'source_active_position_ids' => $sourceActiveIds,
            ];
        }

        sort($positionIds, SORT_NUMERIC);

        return [
            'classified_row_count' => count($classification->positions),
            'unique_position_id_count' => count(array_unique($positionIds)),
            'unclassifiable_row_ids' => $classification->unclassifiableRowIds,
            'missing_reconciliation_timestamp_row_ids' => $classification->missingReconciliationTimestampRowIds,
            'classification_sha256' => hash_final($classificationFingerprint),
            'canonical_position_count' => count(array_filter(
                $classification->positions,
                static fn (LegacyUserPositionProjection $position): bool => $position->isCanonical,
            )),
            'canonical_active_position_count' => $canonicalActivePositionCount,
            'alias_position_count' => $aliasPositionCount,
            'synthesized_alias_soft_delete_count' => count(array_filter(
                $classification->positions,
                static fn (LegacyUserPositionProjection $position): bool => $position->softDeleteSynthesized,
            )),
            'duplicate_context_count' => count($duplicateContexts),
            'active_duplicate_context_count' => $activeDuplicateContextCount,
            'active_duplicate_extra_row_count' => $activeDuplicateExtraRowCount,
            'all_deleted_context_count' => $allDeletedContextCount,
            'invalid_canonical_context_count' => $invalidCanonicalContextCount,
            'account_without_canonical_position_count' => count(array_filter(
                $accountAggregation->accounts,
                static fn (LegacyUserAccount $account): bool => ! isset($canonicalPositionIds[$account->id]),
            )),
            'alias_without_canonical_target_count' => $this->aliasWithoutCanonicalTargetCount(
                $classification->positions,
                $positionsById,
            ),
            'duplicate_contexts' => $duplicateContexts,
        ];
    }

    /**
     * @param  list<LegacyUserPositionProjection>  $positions
     * @param  array<int, LegacyUserPositionProjection>  $positionsById
     */
    private function aliasWithoutCanonicalTargetCount(array $positions, array $positionsById): int
    {
        $invalidCount = 0;

        foreach ($positions as $position) {
            if ($position->isCanonical) {
                continue;
            }

            $canonical = $positionsById[$position->canonicalUserPositionId ?? 0] ?? null;

            if (
                ! $canonical instanceof LegacyUserPositionProjection
                || ! $canonical->isCanonical
                || $canonical->contextKey() !== $position->contextKey()
            ) {
                $invalidCount++;
            }
        }

        return $invalidCount;
    }

    private function isSourceActive(LegacyUserRow $row): bool
    {
        return $row->status === 1 && $row->deletedAt === null;
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $organization
     * @param  array<string, mixed>  $positions
     * @return list<string>
     */
    private function warnings(array $identity, array $organization, array $positions): array
    {
        $warnings = [];

        $this->addWarning($warnings, count($identity['invalid_nip_row_ids']), 'Ada NIP yang tidak berformat 18 digit.');
        $this->addWarning($warnings, count($identity['blank_email_row_ids']), 'Ada email kosong pada sumber legacy.');
        $this->addWarning($warnings, count($identity['invalid_email_row_ids']), 'Ada email dengan format tidak valid.');
        $this->addWarning($warnings, count($identity['profile_conflicts']), 'Ada profil berbeda untuk NIK yang sama.');
        $this->addWarning($warnings, count($identity['cross_nik_email_conflicts']), 'Ada email yang dipakai oleh lebih dari satu NIK.');
        $this->addWarning($warnings, $organization['inactive_reference_row_count'], 'Ada referensi master yang tidak aktif atau terhapus.');
        $this->addWarning($warnings, count($organization['allowed_corrections']), 'Ada mismatch instansi yang akan dikoreksi melalui allowlist.');
        $this->addWarning($warnings, count($positions['duplicate_contexts']), 'Ada posisi duplikat yang akan dipertahankan sebagai alias legacy.');

        return $warnings;
    }

    /** @param list<string> $warnings */
    private function addWarning(array &$warnings, int $count, string $message): void
    {
        if ($count > 0) {
            $warnings[] = $message;
        }
    }

    /**
     * @param  array<string, array<int, list<int>>>  $issues
     * @return array<string, array<int, list<int>>>
     */
    private function sortedReferenceIssues(array $issues): array
    {
        foreach ($issues as &$references) {
            ksort($references, SORT_NUMERIC);
        }
        unset($references);

        return $issues;
    }

    /** @param array<string, array<int, list<int>>> $issues */
    private function referenceIssueRowCount(array $issues): int
    {
        $rowIds = [];

        foreach ($issues as $references) {
            foreach ($references as $referenceRowIds) {
                foreach ($referenceRowIds as $rowId) {
                    $rowIds[$rowId] = true;
                }
            }
        }

        return count($rowIds);
    }

    private function normalizedValue(string $value): string
    {
        return Str::of($value)->squish()->lower()->toString();
    }

    private function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
