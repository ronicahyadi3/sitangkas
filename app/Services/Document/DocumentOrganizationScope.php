<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Instansi;
use App\Models\UnitKerja;
use App\Models\UserPosition;

final class DocumentOrganizationScope
{
    /** @var array<int, list<int>> */
    private array $accessibleUnitIdsByUnitId = [];

    /** @var array<int, int> */
    private array $scopeUnitIdsByUnitId = [];

    /** @return list<int> */
    public function accessibleUnitIds(UserPosition|int $position): array
    {
        $unitKerjaId = $position instanceof UserPosition
            ? (int) $position->unit_kerja_id
            : $position;

        return $this->accessibleUnitIdsForUnit($unitKerjaId);
    }

    /** @return list<int> */
    public function accessibleUnitIdsForUnit(int $unitKerjaId): array
    {
        if ($unitKerjaId < 1) {
            return [];
        }

        if (isset($this->accessibleUnitIdsByUnitId[$unitKerjaId])) {
            return $this->accessibleUnitIdsByUnitId[$unitKerjaId];
        }

        $unit = UnitKerja::withTrashed()->whereKey($unitKerjaId)->first();

        if (! $unit instanceof UnitKerja) {
            return $this->accessibleUnitIdsByUnitId[$unitKerjaId] = [];
        }

        $unitIds = [$unitKerjaId];
        $instansiCodes = array_keys(
            array_filter(
                $this->rootUnitCodeByInstansiCode(),
                static fn (string $rootUnitCode): bool => $rootUnitCode === $unit->kode,
            ),
        );

        if ($instansiCodes !== []) {
            $instansiIds = Instansi::withTrashed()
                ->whereIn('kode', $instansiCodes)
                ->pluck('id');

            $unitIds = array_merge(
                $unitIds,
                UnitKerja::withTrashed()
                    ->whereIn('instansi_id', $instansiIds)
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->all(),
            );
        }

        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));
        sort($unitIds, SORT_NUMERIC);

        return $this->accessibleUnitIdsByUnitId[$unitKerjaId] = $unitIds;
    }

    public function containsUnit(UserPosition|int $position, ?int $documentUnitId): bool
    {
        return $documentUnitId !== null
            && in_array($documentUnitId, $this->accessibleUnitIds($position), true);
    }

    public function scopeUnitIdForUnit(int $unitKerjaId): ?int
    {
        if ($unitKerjaId < 1) {
            return null;
        }

        if (isset($this->scopeUnitIdsByUnitId[$unitKerjaId])) {
            return $this->scopeUnitIdsByUnitId[$unitKerjaId];
        }

        $unit = UnitKerja::withTrashed()
            ->with('instansi')
            ->whereKey($unitKerjaId)
            ->first();

        if (! $unit instanceof UnitKerja || ! $unit->instansi) {
            return null;
        }

        $rootUnitCode = $this->rootUnitCodeByInstansiCode()[$unit->instansi->kode] ?? null;

        if ($rootUnitCode === null) {
            return $this->scopeUnitIdsByUnitId[$unitKerjaId] = (int) ($unit->parent_id ?: $unit->id);
        }

        $scopeUnitId = UnitKerja::withTrashed()
            ->where('kode', $rootUnitCode)
            ->value('id');

        return $this->scopeUnitIdsByUnitId[$unitKerjaId] = (int) ($scopeUnitId ?: $unit->id);
    }

    /** @return array<string, string> */
    private function rootUnitCodeByInstansiCode(): array
    {
        $mapping = config('position_rules.unit_kerja.document_scope_root_by_instansi_code', []);

        if (! is_array($mapping)) {
            return [];
        }

        return array_filter(
            $mapping,
            static fn (mixed $unitCode, mixed $instansiCode): bool => is_string($instansiCode)
                && $instansiCode !== ''
                && is_string($unitCode)
                && $unitCode !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
