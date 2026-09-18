<?php

namespace App\Data\LegacyImport;

final readonly class LegacyUserAccountStatusResolution
{
    /**
     * @param  array<int, LegacyUserAccountStatus>  $statusesByUserId
     */
    public function __construct(
        public string $strategy,
        private array $statusesByUserId,
    ) {}

    public function forUser(int $userId): ?LegacyUserAccountStatus
    {
        return $this->statusesByUserId[$userId] ?? null;
    }

    /** @return list<LegacyUserAccountStatus> */
    public function statuses(): array
    {
        return array_values($this->statusesByUserId);
    }

    /** @return array<string, mixed> */
    public function toAnalysis(): array
    {
        $activeAccountIds = [];
        $inactiveAccountIds = [];
        $inactiveNonDeletedAccountIds = [];
        $allSourceRowsDeletedAccountIds = [];
        $fingerprint = hash_init('sha256');

        foreach ($this->statusesByUserId as $status) {
            hash_update($fingerprint, implode('|', [
                $status->userId,
                $status->status,
                $status->reason,
                $status->hasActiveCanonicalPosition ? '1' : '0',
                $status->allSourceRowsDeleted ? '1' : '0',
            ])."\n");

            if ($status->status === 'active') {
                $activeAccountIds[] = $status->userId;
            } else {
                $inactiveAccountIds[] = $status->userId;
            }

            if ($status->status !== 'active' && ! $status->allSourceRowsDeleted) {
                $inactiveNonDeletedAccountIds[] = $status->userId;
            }

            if ($status->allSourceRowsDeleted) {
                $allSourceRowsDeletedAccountIds[] = $status->userId;
            }
        }

        return [
            'strategy' => $this->strategy,
            'account_count' => count($this->statusesByUserId),
            'active_account_count' => count($activeAccountIds),
            'inactive_account_count' => count($inactiveAccountIds),
            'inactive_non_deleted_account_count' => count($inactiveNonDeletedAccountIds),
            'all_source_rows_deleted_account_count' => count($allSourceRowsDeletedAccountIds),
            'inactive_account_ids' => $inactiveAccountIds,
            'inactive_non_deleted_account_ids' => $inactiveNonDeletedAccountIds,
            'all_source_rows_deleted_account_ids' => $allSourceRowsDeletedAccountIds,
            'resolution_sha256' => hash_final($fingerprint),
        ];
    }
}
