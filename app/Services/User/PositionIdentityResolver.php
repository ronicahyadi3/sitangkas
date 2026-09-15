<?php

namespace App\Services\User;

use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;
use LogicException;

final class PositionIdentityResolver
{
    /**
     * @var array<int, UserPosition>
     */
    private array $positionsById = [];

    /**
     * @var array<int, int>
     */
    private array $canonicalIdsByPositionId = [];

    /**
     * @var array<int, list<int>>
     */
    private array $equivalentIdsByCanonicalId = [];

    public function canonicalPosition(UserPosition|int $position): UserPosition
    {
        $position = $this->resolvePosition($position);
        $positionId = (int) $position->getKey();

        if ($position->is_canonical) {
            if ($position->canonical_user_position_id !== null) {
                throw new LogicException("Canonical position {$positionId} cannot reference another canonical position.");
            }

            $this->canonicalIdsByPositionId[$positionId] = $positionId;

            return $position;
        }

        $canonicalPositionId = (int) ($position->canonical_user_position_id ?? 0);

        if ($canonicalPositionId < 1 || $canonicalPositionId === $positionId) {
            throw new LogicException("Alias position {$positionId} has an invalid canonical reference.");
        }

        $canonical = $position->relationLoaded('canonicalPosition')
            ? $position->getRelation('canonicalPosition')
            : $this->resolvePosition($canonicalPositionId);

        if (! $canonical instanceof UserPosition) {
            throw new LogicException("Canonical position {$canonicalPositionId} for alias {$positionId} was not found.");
        }

        $this->validateAlias($position, $canonical);
        $this->rememberPosition($canonical);
        $this->canonicalIdsByPositionId[$positionId] = $canonicalPositionId;
        $this->canonicalIdsByPositionId[$canonicalPositionId] = $canonicalPositionId;

        return $canonical;
    }

    public function canonicalId(UserPosition|int $position): int
    {
        $positionId = $position instanceof UserPosition
            ? (int) $position->getKey()
            : $position;

        if (isset($this->canonicalIdsByPositionId[$positionId])) {
            return $this->canonicalIdsByPositionId[$positionId];
        }

        return (int) $this->canonicalPosition($position)->getKey();
    }

    /** @return list<int> */
    public function equivalentIds(UserPosition|int $position): array
    {
        $canonical = $this->canonicalPosition($position);
        $canonicalId = (int) $canonical->getKey();

        if (isset($this->equivalentIdsByCanonicalId[$canonicalId])) {
            return $this->equivalentIdsByCanonicalId[$canonicalId];
        }

        $positions = $canonical->relationLoaded('aliases')
            ? collect([$canonical])->concat($canonical->getRelation('aliases'))
            : UserPosition::withTrashed()
                ->where(function (EloquentBuilder $query) use ($canonicalId): void {
                    $query->whereKey($canonicalId)
                        ->orWhere('canonical_user_position_id', $canonicalId);
                })
                ->get();
        $equivalentIds = [];

        foreach ($positions as $equivalentPosition) {
            if (! $equivalentPosition instanceof UserPosition) {
                throw new LogicException("Equivalent positions for canonical position {$canonicalId} contain an invalid model.");
            }

            $this->rememberPosition($equivalentPosition);
            $equivalentPositionId = (int) $equivalentPosition->getKey();

            if ($equivalentPositionId === $canonicalId) {
                if (! $equivalentPosition->is_canonical) {
                    throw new LogicException("Canonical position {$canonicalId} is marked as an alias.");
                }
            } else {
                $this->validateAlias($equivalentPosition, $canonical);
            }

            $this->canonicalIdsByPositionId[$equivalentPositionId] = $canonicalId;
            $equivalentIds[] = $equivalentPositionId;
        }

        $equivalentIds = array_values(array_unique($equivalentIds));
        sort($equivalentIds, SORT_NUMERIC);

        if (! in_array($canonicalId, $equivalentIds, true)) {
            throw new LogicException("Equivalent positions do not contain canonical position {$canonicalId}.");
        }

        return $this->equivalentIdsByCanonicalId[$canonicalId] = $equivalentIds;
    }

    public function contains(UserPosition|int $position, int $recordedPositionId): bool
    {
        return in_array($recordedPositionId, $this->equivalentIds($position), true);
    }

    public function areEquivalent(UserPosition|int $left, UserPosition|int $right): bool
    {
        return $this->canonicalId($left) === $this->canonicalId($right);
    }

    public function pptkActorPosition(UserPosition $contextPosition): UserPosition
    {
        return $this->contextActorPosition($contextPosition, 'actingPptkUserPosition');
    }

    public function budActorPosition(UserPosition $contextPosition): UserPosition
    {
        return $this->contextActorPosition($contextPosition, 'actingBudUserPosition');
    }

    public function whereEquivalent(
        EloquentBuilder|QueryBuilder $query,
        string $column,
        UserPosition|int $position,
    ): EloquentBuilder|QueryBuilder {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $column)) {
            throw new InvalidArgumentException('Equivalent position query column is invalid.');
        }

        return $query->whereIn($column, $this->equivalentIds($position));
    }

    private function resolvePosition(UserPosition|int $position): UserPosition
    {
        if ($position instanceof UserPosition) {
            $this->rememberPosition($position);

            return $position;
        }

        if ($position < 1) {
            throw new InvalidArgumentException('Position ID must be greater than zero.');
        }

        if (isset($this->positionsById[$position])) {
            return $this->positionsById[$position];
        }

        $resolvedPosition = UserPosition::withTrashed()->findOrFail($position);
        $this->rememberPosition($resolvedPosition);

        return $resolvedPosition;
    }

    private function rememberPosition(UserPosition $position): void
    {
        $positionId = (int) $position->getKey();

        if ($positionId < 1) {
            throw new InvalidArgumentException('Position model must have a persisted primary key.');
        }

        $this->positionsById[$positionId] = $position;
    }

    private function contextActorPosition(UserPosition $contextPosition, string $relation): UserPosition
    {
        $actorPosition = $contextPosition->relationLoaded($relation)
            ? $contextPosition->getRelation($relation)
            : null;

        return $actorPosition instanceof UserPosition
            ? $this->canonicalPosition($actorPosition)
            : $this->canonicalPosition($contextPosition);
    }

    private function validateAlias(UserPosition $alias, UserPosition $canonical): void
    {
        $aliasId = (int) $alias->getKey();
        $canonicalId = (int) $canonical->getKey();

        if ($alias->is_canonical) {
            throw new LogicException("Position {$aliasId} is canonical and cannot be treated as an alias.");
        }

        if ((int) $alias->canonical_user_position_id !== $canonicalId || ! $canonical->is_canonical) {
            throw new LogicException("Alias position {$aliasId} does not reference canonical position {$canonicalId} correctly.");
        }

        foreach (['user_id', 'jabatan_id', 'instansi_id', 'unit_kerja_id'] as $attribute) {
            if ((int) $alias->getAttribute($attribute) !== (int) $canonical->getAttribute($attribute)) {
                throw new LogicException("Alias position {$aliasId} has a different {$attribute} from canonical position {$canonicalId}.");
            }
        }
    }
}
