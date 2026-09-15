<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyUserAccount;
use App\Data\LegacyImport\LegacyUserAccountAggregation;
use App\Data\LegacyImport\LegacyUserRow;
use Illuminate\Support\Str;

final class LegacyUserAccountAggregator
{
    /**
     * @param  iterable<LegacyUserRow>  $rows
     */
    public function aggregate(iterable $rows): LegacyUserAccountAggregation
    {
        $rowsByNik = [];
        $invalidNikRowIds = [];

        foreach ($rows as $row) {
            $nik = trim((string) $row->nik);

            if (! preg_match('/^\d{16}$/', $nik)) {
                $invalidNikRowIds[] = $row->id;

                continue;
            }

            $rowsByNik[$nik][] = $row;
        }

        ksort($rowsByNik, SORT_STRING);
        sort($invalidNikRowIds, SORT_NUMERIC);

        $accounts = [];

        foreach ($rowsByNik as $nik => $accountRows) {
            $accounts[] = $this->createAccount($nik, $accountRows);
        }

        return new LegacyUserAccountAggregation(
            accounts: $accounts,
            invalidNikRowIds: $invalidNikRowIds,
        );
    }

    /**
     * @param  list<LegacyUserRow>  $rows
     */
    private function createAccount(string $nik, array $rows): LegacyUserAccount
    {
        usort($rows, fn (LegacyUserRow $left, LegacyUserRow $right): int => $this->compareRows($left, $right));

        $canonicalRow = $rows[0];
        $sourceRowIds = array_map(
            static fn (LegacyUserRow $row): int => $row->id,
            $rows,
        );
        sort($sourceRowIds, SORT_NUMERIC);

        return new LegacyUserAccount(
            id: $canonicalRow->id,
            nik: $nik,
            nip: $this->nullableTrimmed($canonicalRow->nip),
            name: Str::squish($canonicalRow->name),
            email: $this->normalizedEmail($canonicalRow->email),
            passwordHash: $canonicalRow->passwordHash(),
            sourceRowIds: $sourceRowIds,
            conflictingFields: $this->conflictingFields($rows),
            selectionTier: $this->selectionTier($canonicalRow),
            hasActiveSourceRow: $this->hasActiveSourceRow($rows),
            allSourceRowsDeleted: $this->allSourceRowsDeleted($rows),
            sourceCreatedAt: $canonicalRow->createdAt,
            sourceUpdatedAt: $canonicalRow->updatedAt,
        );
    }

    private function compareRows(LegacyUserRow $left, LegacyUserRow $right): int
    {
        $rankComparison = $this->selectionRank($right) <=> $this->selectionRank($left);

        if ($rankComparison !== 0) {
            return $rankComparison;
        }

        $createdAtComparison = ($right->createdAt?->getTimestamp() ?? PHP_INT_MIN)
            <=> ($left->createdAt?->getTimestamp() ?? PHP_INT_MIN);

        return $createdAtComparison !== 0
            ? $createdAtComparison
            : $right->id <=> $left->id;
    }

    private function selectionRank(LegacyUserRow $row): int
    {
        if ($this->isSourceActive($row)) {
            return 3;
        }

        return $row->deletedAt === null ? 2 : 1;
    }

    private function selectionTier(LegacyUserRow $row): string
    {
        if ($this->isSourceActive($row)) {
            return 'active';
        }

        return $row->deletedAt === null ? 'nondeleted_inactive' : 'deleted_history';
    }

    /** @param list<LegacyUserRow> $rows */
    private function conflictingFields(array $rows): array
    {
        $values = [
            'name' => [],
            'nip' => [],
            'email' => [],
            'password' => [],
        ];

        foreach ($rows as $row) {
            $values['name'][$this->normalizedName($row->name)] = true;

            if (($nip = $this->nullableTrimmed($row->nip)) !== null) {
                $values['nip'][$nip] = true;
            }

            if (($email = $this->normalizedEmail($row->email)) !== null) {
                $values['email'][$email] = true;
            }

            $values['password'][hash('sha256', $row->passwordHash())] = true;
        }

        return array_values(array_keys(array_filter(
            $values,
            static fn (array $fieldValues): bool => count($fieldValues) > 1,
        )));
    }

    /** @param list<LegacyUserRow> $rows */
    private function hasActiveSourceRow(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($this->isSourceActive($row)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<LegacyUserRow> $rows */
    private function allSourceRowsDeleted(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row->deletedAt === null) {
                return false;
            }
        }

        return true;
    }

    private function isSourceActive(LegacyUserRow $row): bool
    {
        return $row->status === 1 && $row->deletedAt === null;
    }

    private function normalizedName(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    private function normalizedEmail(string $email): ?string
    {
        $email = Str::lower(trim($email));

        return $email === '' ? null : $email;
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
