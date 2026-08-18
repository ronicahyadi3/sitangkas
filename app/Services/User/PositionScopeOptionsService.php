<?php

namespace App\Services\User;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Services\Auth\AdminSuperPositionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PositionScopeOptionsService
{
    public function __construct(private AdminSuperPositionScope $positionScope) {}

    /**
     * @return list<string>
     */
    public function allowedInstansiNamesForRole(int $jabatanId, ?string $jabatanName = null): array
    {
        $jabatan = $this->jabatan($jabatanId, $jabatanName);

        if (! $jabatan instanceof Jabatan) {
            return [];
        }

        if ($this->isAdminSuperRole($jabatan)) {
            return Instansi::query()
                ->active()
                ->where('kode', $this->defaultRealAdminSuperInstansiCode())
                ->pluck('nama')
                ->values()
                ->all();
        }

        return $this->positionScope
            ->instansiOptionsForJabatan($jabatan)
            ->pluck('nama')
            ->values()
            ->all();
    }

    public function isInstansiAllowedForRole(int $jabatanId, int $instansiId, ?string $jabatanName = null): bool
    {
        $jabatan = $this->jabatan($jabatanId, $jabatanName);
        $instansi = Instansi::query()->active()->whereKey($instansiId)->first();

        if ($jabatan instanceof Jabatan && $instansi instanceof Instansi && $this->isAdminSuperRole($jabatan)) {
            return $instansi->kode === $this->defaultRealAdminSuperInstansiCode();
        }

        return $jabatan instanceof Jabatan
            && $instansi instanceof Instansi
            && $this->positionScope->isInstansiAllowedForJabatan($jabatan, $instansi);
    }

    public function unitQueryForRoleAndInstansi(int $jabatanId, int $instansiId): Builder
    {
        $jabatan = $this->jabatan($jabatanId);
        $instansi = Instansi::query()->active()->whereKey($instansiId)->first();

        if (! $jabatan instanceof Jabatan || ! $instansi instanceof Instansi) {
            return $this->emptyUnitKerjaQuery();
        }

        if ($this->isAdminSuperRole($jabatan)) {
            if ($instansi->kode !== $this->defaultRealAdminSuperInstansiCode()) {
                return $this->emptyUnitKerjaQuery();
            }

            return $this->baseUnitKerjaQuery()
                ->whereBelongsTo($instansi);
        }

        return $this->positionScope->unitKerjaQueryForJabatanAndInstansi($jabatan, $instansi);
    }

    public function isUnitAllowedForRoleAndInstansi(int $jabatanId, int $instansiId, int $unitKerjaId): bool
    {
        $jabatan = $this->jabatan($jabatanId);
        $instansi = Instansi::query()->active()->whereKey($instansiId)->first();
        $unitKerja = UnitKerja::query()
            ->active()
            ->effective()
            ->whereKey($unitKerjaId)
            ->where('instansi_id', $instansiId)
            ->first();

        if ($jabatan instanceof Jabatan && $instansi instanceof Instansi && $this->isAdminSuperRole($jabatan)) {
            return $instansi->kode === $this->defaultRealAdminSuperInstansiCode()
                && $unitKerja instanceof UnitKerja;
        }

        return $jabatan instanceof Jabatan
            && $instansi instanceof Instansi
            && $unitKerja instanceof UnitKerja
            && $this->positionScope->isUnitKerjaAllowedForJabatanAndInstansi($jabatan, $instansi, $unitKerja);
    }

    public function isAdminSuperRole(Jabatan $jabatan): bool
    {
        return in_array($jabatan->kode, $this->adminSuperJabatanCodes(), true)
            || in_array($this->legacyJabatanCode($jabatan->nama), $this->adminSuperJabatanCodes(), true);
    }

    private function jabatan(int $jabatanId, ?string $jabatanName = null): ?Jabatan
    {
        $jabatan = Jabatan::query()->active()->whereKey($jabatanId)->first();

        if ($jabatan instanceof Jabatan || ! filled($jabatanName)) {
            return $jabatan;
        }

        $legacyCode = $this->legacyJabatanCode($jabatanName);

        if ($legacyCode === null) {
            return null;
        }

        return Jabatan::query()->active()->where('kode', $legacyCode)->first();
    }

    private function legacyJabatanCode(?string $jabatanName): ?string
    {
        $normalizedName = Str::of((string) $jabatanName)->trim()->upper()->toString();

        if ($normalizedName === '') {
            return null;
        }

        $legacyMap = config('position_rules.legacy.jabatan_name_to_code', []);

        return is_array($legacyMap) && is_string($legacyMap[$normalizedName] ?? null)
            ? $legacyMap[$normalizedName]
            : null;
    }

    /**
     * @return list<string>
     */
    private function adminSuperJabatanCodes(): array
    {
        $codes = config('position_rules.admin_super_jabatan_codes', []);

        if (! is_array($codes)) {
            return [];
        }

        return array_values(array_filter($codes, static fn (mixed $code): bool => is_string($code) && $code !== ''));
    }

    private function emptyUnitKerjaQuery(): Builder
    {
        return $this->baseUnitKerjaQuery()
            ->whereKey([]);
    }

    private function baseUnitKerjaQuery(): Builder
    {
        return UnitKerja::query()
            ->active()
            ->effective()
            ->ordered();
    }

    private function defaultRealAdminSuperInstansiCode(): string
    {
        $code = config('position_rules.instansi.skpd_code', 'SKPD');

        return is_string($code) && $code !== '' ? $code : 'SKPD';
    }
}
