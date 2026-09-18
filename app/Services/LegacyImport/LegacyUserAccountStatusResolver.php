<?php

namespace App\Services\LegacyImport;

use App\Data\LegacyImport\LegacyUserAccount;
use App\Data\LegacyImport\LegacyUserAccountAggregation;
use App\Data\LegacyImport\LegacyUserAccountStatus;
use App\Data\LegacyImport\LegacyUserAccountStatusResolution;
use App\Data\LegacyImport\LegacyUserPositionClassification;
use App\Data\LegacyImport\LegacyUserPositionProjection;
use App\Exceptions\LegacyImport\LegacyUserImportBlockedException;
use App\Models\User;

final class LegacyUserAccountStatusResolver
{
    public const string Strategy = 'active_if_any_active_position_else_inactive';

    public const string ActiveReason = 'legacy_import:has_active_canonical_position';

    public const string InactiveReason = 'legacy_import:no_active_canonical_position';

    public const string AllPositionsDeletedReason = 'legacy_import:all_positions_deleted';

    public function resolveConfigured(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
    ): LegacyUserAccountStatusResolution {
        return $this->resolve(
            $accountAggregation,
            $positionClassification,
            (string) config('legacy_import.execution.decisions.account_status_strategy', ''),
        );
    }

    public function resolve(
        LegacyUserAccountAggregation $accountAggregation,
        LegacyUserPositionClassification $positionClassification,
        string $strategy,
    ): LegacyUserAccountStatusResolution {
        if ($strategy !== self::Strategy) {
            throw new LegacyUserImportBlockedException('account_status_configuration', [
                'legacy_import.execution.decisions.account_status_strategy belum valid.',
            ]);
        }

        $activeCanonicalPositionByUserId = [];

        foreach ($positionClassification->positions as $position) {
            if ($this->isActiveCanonicalPosition($position)) {
                $activeCanonicalPositionByUserId[$position->userId] = true;
            }
        }

        $statusesByUserId = [];

        foreach ($accountAggregation->accounts as $account) {
            $hasActiveCanonicalPosition = isset($activeCanonicalPositionByUserId[$account->id]);
            $statusesByUserId[$account->id] = $this->accountStatus(
                $account,
                $hasActiveCanonicalPosition,
            );
        }

        ksort($statusesByUserId, SORT_NUMERIC);

        return new LegacyUserAccountStatusResolution($strategy, $statusesByUserId);
    }

    private function accountStatus(
        LegacyUserAccount $account,
        bool $hasActiveCanonicalPosition,
    ): LegacyUserAccountStatus {
        if ($hasActiveCanonicalPosition) {
            return new LegacyUserAccountStatus(
                userId: $account->id,
                status: User::STATUS_ACTIVE,
                reason: self::ActiveReason,
                hasActiveCanonicalPosition: true,
                allSourceRowsDeleted: $account->allSourceRowsDeleted,
            );
        }

        return new LegacyUserAccountStatus(
            userId: $account->id,
            status: User::STATUS_INACTIVE,
            reason: $account->allSourceRowsDeleted
                ? self::AllPositionsDeletedReason
                : self::InactiveReason,
            hasActiveCanonicalPosition: false,
            allSourceRowsDeleted: $account->allSourceRowsDeleted,
        );
    }

    private function isActiveCanonicalPosition(LegacyUserPositionProjection $position): bool
    {
        return $position->isCanonical
            && $position->isActive
            && $position->resultDeletedAt === null;
    }
}
