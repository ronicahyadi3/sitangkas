<?php

namespace App\Services\User;

use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Builder;

class UserManagementAccessService
{
    /**
     * @var array<string, list<string>>
     */
    private const MANAGEABLE_CODES_BY_ADMIN_CODE = [
        'PA' => ['PPK_SKPD', 'PPTK', 'BP'],
        'KPA' => ['PPTK', 'BPP'],
    ];

    public function __construct(
        private PositionScopeOptionsService $positionScopeOptionsService
    ) {}

    public function canAccessModule(?UserPosition $actor): bool
    {
        return $this->isFullAdmin($actor) || $this->isScopedAdmin($actor);
    }

    public function isFullAdmin(?UserPosition $actor): bool
    {
        if (! $actor instanceof UserPosition) {
            return false;
        }

        $code = $this->jabatanCode((int) $actor->jabatan_id);

        return is_string($code)
            && in_array($code, config('position_rules.admin_super_jabatan_codes', []), true);
    }

    public function isScopedAdmin(?UserPosition $actor): bool
    {
        if (! $actor instanceof UserPosition || $this->isFullAdmin($actor)) {
            return false;
        }

        return array_key_exists((string) $this->jabatanCode((int) $actor->jabatan_id), self::MANAGEABLE_CODES_BY_ADMIN_CODE);
    }

    /**
     * @return list<int>
     */
    public function allowedJabatanIds(?UserPosition $actor): array
    {
        if ($this->isFullAdmin($actor)) {
            return Jabatan::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('nama')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        $managedCodes = $this->manageableJabatanCodes($actor);

        if ($managedCodes === []) {
            return [];
        }

        return Jabatan::query()
            ->active()
            ->whereIn('kode', $managedCodes)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function isJabatanManageable(int $jabatanId, ?UserPosition $actor): bool
    {
        return in_array($jabatanId, $this->allowedJabatanIds($actor), true);
    }

    public function applyVisibleUsersScope(Builder $query, ?UserPosition $actor): Builder
    {
        if ($this->isFullAdmin($actor)) {
            return $query;
        }

        if (! $this->isScopedAdmin($actor)) {
            return $query->whereKey([]);
        }

        return $query->where(function (Builder $query) use ($actor): void {
            $query
                ->whereHas('userPositions', function (Builder $positions) use ($actor): void {
                    $this->applyManageablePositionsScope($positions, $actor);
                })
                ->orWhere(function (Builder $query): void {
                    $query
                        ->where('created_by_user_id', auth()->id())
                        ->whereDoesntHave('userPositions');
                });
        });
    }

    public function applyVisiblePositionsScope(Builder $query, ?UserPosition $actor): Builder
    {
        if ($this->isFullAdmin($actor)) {
            return $query;
        }

        if (! $this->isScopedAdmin($actor)) {
            return $query->whereKey([]);
        }

        return $this->applyManageablePositionsScope($query, $actor);
    }

    public function canViewUser(User $user, ?UserPosition $actor): bool
    {
        if ($this->isFullAdmin($actor)) {
            return true;
        }

        if (! $this->isScopedAdmin($actor)) {
            return false;
        }

        if ($this->canManagePendingInitialUser($user, $actor)) {
            return true;
        }

        return $user->userPositions()
            ->where(function (Builder $positions) use ($actor): void {
                $this->applyManageablePositionsScope($positions, $actor);
            })
            ->exists();
    }

    public function canEditUserProfile(User $user, ?UserPosition $actor): bool
    {
        return $this->canManageUser($user, $actor);
    }

    public function canManageUser(User $user, ?UserPosition $actor): bool
    {
        if ($this->isFullAdmin($actor)) {
            return (int) $user->id !== (int) auth()->id();
        }

        if (! $this->isScopedAdmin($actor)) {
            return false;
        }

        $totalPositions = $user->userPositions()->count();

        if ($totalPositions === 0) {
            return $this->canManagePendingInitialUser($user, $actor);
        }

        $manageablePositions = $user->userPositions()
            ->where(function (Builder $positions) use ($actor): void {
                $this->applyManageablePositionsScope($positions, $actor);
            })
            ->count();

        return $totalPositions === $manageablePositions;
    }

    public function canAttachPositionToUser(
        User $user,
        ?UserPosition $actor,
        int $jabatanId,
        ?int $instansiId,
        ?int $unitKerjaId
    ): bool {
        if ($this->isFullAdmin($actor)) {
            return true;
        }

        if (! $this->isScopedAdmin($actor)) {
            return false;
        }

        if (! $this->isPositionWithinScope($jabatanId, $instansiId, $unitKerjaId, $actor)) {
            return false;
        }

        if (! $user->userPositions()->exists()) {
            return $this->canManagePendingInitialUser($user, $actor);
        }

        return $this->canViewUser($user, $actor);
    }

    public function canManagePosition(UserPosition $position, ?UserPosition $actor): bool
    {
        if ($this->isFullAdmin($actor)) {
            return true;
        }

        return $this->isPositionWithinScope(
            (int) $position->jabatan_id,
            (int) $position->instansi_id,
            (int) $position->unit_kerja_id,
            $actor
        );
    }

    public function isPositionWithinScope(
        int $jabatanId,
        ?int $instansiId,
        ?int $unitKerjaId,
        ?UserPosition $actor
    ): bool {
        if ($instansiId === null || $unitKerjaId === null) {
            return false;
        }

        if (
            ! $this->positionScopeOptionsService->isInstansiAllowedForRole($jabatanId, $instansiId)
            || ! $this->positionScopeOptionsService->isUnitAllowedForRoleAndInstansi($jabatanId, $instansiId, $unitKerjaId)
        ) {
            return false;
        }

        if ($this->isFullAdmin($actor)) {
            return true;
        }

        if (! $this->isJabatanManageable($jabatanId, $actor)) {
            return false;
        }

        return $actor instanceof UserPosition
            && (int) $actor->instansi_id === $instansiId
            && in_array($unitKerjaId, $this->managedUnitIds($actor), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(?UserPosition $actor): array
    {
        return [
            'is_full_admin' => $this->isFullAdmin($actor),
            'is_scoped_admin' => $this->isScopedAdmin($actor),
            'scope_label' => $this->scopeLabel($actor),
            'allowed_jabatan_ids' => $this->allowedJabatanIds($actor),
            'managed_unit_ids' => $this->managedUnitIds($actor),
        ];
    }

    private function applyManageablePositionsScope(Builder $positions, ?UserPosition $actor): Builder
    {
        $allowedJabatanIds = $this->allowedJabatanIds($actor);
        $managedUnitIds = $this->managedUnitIds($actor);

        if (! $actor instanceof UserPosition || $allowedJabatanIds === [] || $managedUnitIds === []) {
            return $positions->whereKey([]);
        }

        return $positions
            ->whereIn('jabatan_id', $allowedJabatanIds)
            ->where('instansi_id', (int) $actor->instansi_id)
            ->whereIn('unit_kerja_id', $managedUnitIds);
    }

    private function canManagePendingInitialUser(User $user, ?UserPosition $actor): bool
    {
        return $this->isScopedAdmin($actor)
            && (int) ($user->created_by_user_id ?? 0) === (int) auth()->id()
            && ! $user->userPositions()->exists();
    }

    /**
     * @return list<string>
     */
    private function manageableJabatanCodes(?UserPosition $actor): array
    {
        if (! $actor instanceof UserPosition) {
            return [];
        }

        $actorCode = $this->jabatanCode((int) $actor->jabatan_id);

        return is_string($actorCode)
            ? self::MANAGEABLE_CODES_BY_ADMIN_CODE[$actorCode] ?? []
            : [];
    }

    /**
     * @return list<int>
     */
    public function managedUnitIds(?UserPosition $actor): array
    {
        if (! $actor instanceof UserPosition || $this->isFullAdmin($actor)) {
            return [];
        }

        $actorUnitId = (int) $actor->unit_kerja_id;

        if ($actorUnitId <= 0) {
            return [];
        }

        $actorCode = $this->jabatanCode((int) $actor->jabatan_id);

        if ($actorCode === 'PA') {
            return UnitKerja::query()
                ->where('instansi_id', (int) $actor->instansi_id)
                ->where(function (Builder $query) use ($actorUnitId): void {
                    $query->whereKey($actorUnitId)
                        ->orWhere('parent_id', $actorUnitId);
                })
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        if ($actorCode === 'KPA') {
            return [$actorUnitId];
        }

        return [];
    }

    private function scopeLabel(?UserPosition $actor): string
    {
        if ($this->isFullAdmin($actor)) {
            return 'Admin Super';
        }

        return $actor?->jabatan?->nama
            ? 'jabatan '.$actor->jabatan->nama
            : 'jabatan aktif Anda';
    }

    private function jabatanCode(int $jabatanId): ?string
    {
        $code = Jabatan::query()->whereKey($jabatanId)->value('kode');

        if (is_string($code) && $code !== '') {
            return $code;
        }

        $legacy = config('position_rules.legacy.jabatan_id_to_code', []);

        return is_array($legacy) && is_string($legacy[$jabatanId] ?? null)
            ? $legacy[$jabatanId]
            : null;
    }
}
