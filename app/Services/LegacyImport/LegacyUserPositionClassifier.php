<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyOrganizationResolution;
use App\Data\LegacyImport\LegacyUserAccountAggregation;
use App\Data\LegacyImport\LegacyUserPositionClassification;
use App\Data\LegacyImport\LegacyUserPositionProjection;
use App\Data\LegacyImport\LegacyUserRow;

final class LegacyUserPositionClassifier
{
    /**
     * @param  iterable<LegacyUserRow>  $rows
     * @param  array<int, LegacyOrganizationResolution>  $organizationResolutions
     */
    public function classify(
        iterable $rows,
        LegacyUserAccountAggregation $accountAggregation,
        array $organizationResolutions,
    ): LegacyUserPositionClassification {
        $accountIdsByNik = [];

        foreach ($accountAggregation->accounts as $account) {
            $accountIdsByNik[$account->nik] = $account->id;
        }

        $contextGroups = [];
        $unclassifiableRowIds = [];

        foreach ($rows as $row) {
            $nik = trim((string) $row->nik);
            $organization = $organizationResolutions[$row->id] ?? null;

            if (
                ! isset($accountIdsByNik[$nik])
                || ! $organization instanceof LegacyOrganizationResolution
                || ! $organization->isImportable()
                || $row->jabatanId === null
                || $organization->canonicalInstansiId === null
                || $row->unitKerjaId === null
            ) {
                $unclassifiableRowIds[] = $row->id;

                continue;
            }

            $contextKey = implode(':', [
                $accountIdsByNik[$nik],
                $row->jabatanId,
                $organization->canonicalInstansiId,
                $row->unitKerjaId,
            ]);
            $contextGroups[$contextKey][] = [
                'row' => $row,
                'user_id' => $accountIdsByNik[$nik],
                'instansi_id' => $organization->canonicalInstansiId,
            ];
        }

        ksort($contextGroups, SORT_STRING);
        sort($unclassifiableRowIds, SORT_NUMERIC);

        $positions = [];
        $missingReconciliationTimestampRowIds = [];

        foreach ($contextGroups as $candidates) {
            $canonicalRow = $this->canonicalCandidate(array_column($candidates, 'row'));

            foreach ($candidates as $candidate) {
                /** @var LegacyUserRow $row */
                $row = $candidate['row'];
                $isCanonical = $row->id === $canonicalRow->id;
                $reconciledAt = null;

                if (! $isCanonical && $row->deletedAt === null) {
                    $reconciledAt = $canonicalRow->createdAt
                        ?? $canonicalRow->updatedAt
                        ?? $row->updatedAt
                        ?? $row->createdAt;

                    if ($reconciledAt === null) {
                        $missingReconciliationTimestampRowIds[] = $row->id;
                    }
                }

                $positions[] = new LegacyUserPositionProjection(
                    id: $row->id,
                    userId: $candidate['user_id'],
                    jabatanId: $row->jabatanId,
                    instansiId: $candidate['instansi_id'],
                    unitKerjaId: $row->unitKerjaId,
                    isActive: $isCanonical && $this->isSourceActive($row),
                    isCanonical: $isCanonical,
                    canonicalUserPositionId: $isCanonical ? null : $canonicalRow->id,
                    legacyDuplicateReason: $isCanonical ? null : 'duplicate_context_older_record',
                    sourceStatus: $row->status,
                    wasSourceActive: $this->isSourceActive($row),
                    sourceCreatedAt: $row->createdAt,
                    sourceUpdatedAt: $row->updatedAt,
                    originalDeletedAt: $row->deletedAt,
                    resultDeletedAt: $row->deletedAt ?? $reconciledAt,
                    endedAt: $reconciledAt,
                    deactivatedAt: $reconciledAt,
                    deactivationReason: $reconciledAt === null ? null : 'superseded_by_newer_legacy_position',
                    softDeleteSynthesized: $reconciledAt !== null,
                );
            }
        }

        usort(
            $positions,
            static fn (LegacyUserPositionProjection $left, LegacyUserPositionProjection $right): int => $left->id <=> $right->id,
        );
        sort($missingReconciliationTimestampRowIds, SORT_NUMERIC);

        return new LegacyUserPositionClassification(
            positions: $positions,
            unclassifiableRowIds: $unclassifiableRowIds,
            missingReconciliationTimestampRowIds: $missingReconciliationTimestampRowIds,
        );
    }

    /** @param list<LegacyUserRow> $rows */
    private function canonicalCandidate(array $rows): LegacyUserRow
    {
        usort($rows, function (LegacyUserRow $left, LegacyUserRow $right): int {
            $rankComparison = $this->canonicalRank($right) <=> $this->canonicalRank($left);

            if ($rankComparison !== 0) {
                return $rankComparison;
            }

            $dateComparison = ($right->createdAt?->getTimestamp() ?? PHP_INT_MIN)
                <=> ($left->createdAt?->getTimestamp() ?? PHP_INT_MIN);

            return $dateComparison !== 0 ? $dateComparison : $right->id <=> $left->id;
        });

        return $rows[0];
    }

    private function canonicalRank(LegacyUserRow $row): int
    {
        if ($this->isSourceActive($row)) {
            return 3;
        }

        return $row->deletedAt === null ? 2 : 1;
    }

    private function isSourceActive(LegacyUserRow $row): bool
    {
        return $row->status === 1 && $row->deletedAt === null;
    }
}
