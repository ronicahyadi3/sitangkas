<?php

namespace App\Services\Auth;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminSuperPositionScope
{
    public const POLICY_FIXED_BKAD = 'fixed_bkad';

    public const POLICY_SKPD_PLUS_KECAMATAN_ROOTS = 'skpd_plus_kecamatan_roots';

    public const POLICY_KELURAHAN_ONLY_WHEN_KECAMATAN = 'kelurahan_only_when_kecamatan';

    public const POLICY_PPTK_MIXED_SCOPE = 'pptk_mixed_scope';

    public const POLICY_DEFAULT_INSTANSI_UNITS = 'default_instansi_units';

    public const SPECIAL_USER_BUD = 'bud';

    public const SPECIAL_USER_PPTK = 'pptk';

    /**
     * @return Collection<int, Jabatan>
     */
    public function jabatanOptions(): Collection
    {
        return Jabatan::query()
            ->select(['id', 'kode', 'nama', 'is_active', 'sort_order'])
            ->active()
            ->whereNotIn('kode', $this->excludedJabatanCodes())
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, Instansi>
     */
    public function instansiOptionsForJabatan(Jabatan $jabatan): Collection
    {
        $instansiCodes = $this->instansiCodesForJabatan($jabatan);

        if ($instansiCodes === []) {
            return collect();
        }

        return Instansi::query()
            ->select(['id', 'kode', 'nama', 'nama_singkat', 'is_active', 'sort_order'])
            ->active()
            ->whereIn('kode', $instansiCodes)
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, UnitKerja>
     */
    public function unitKerjaOptionsForJabatanAndInstansi(Jabatan $jabatan, Instansi $instansi): Collection
    {
        return $this->unitKerjaQueryForJabatanAndInstansi($jabatan, $instansi)->get();
    }

    public function unitKerjaQueryForJabatanAndInstansi(Jabatan $jabatan, Instansi $instansi): Builder
    {
        if (! $this->isInstansiAllowedForJabatan($jabatan, $instansi)) {
            return $this->emptyUnitKerjaQuery();
        }

        return match ($this->unitScopePolicyForJabatan($jabatan)) {
            self::POLICY_FIXED_BKAD => $this->fixedBkadUnitKerjaQuery($instansi),
            self::POLICY_SKPD_PLUS_KECAMATAN_ROOTS => $this->skpdPlusKecamatanRootsUnitKerjaQuery($instansi),
            self::POLICY_KELURAHAN_ONLY_WHEN_KECAMATAN => $this->kelurahanOnlyWhenKecamatanUnitKerjaQuery($instansi),
            self::POLICY_PPTK_MIXED_SCOPE => $this->pptkMixedScopeUnitKerjaQuery($instansi),
            default => $this->defaultInstansiUnitKerjaQuery($instansi),
        };
    }

    /**
     * @return Collection<int, UserPosition>
     */
    public function specialUserPositionOptionsForJabatanAndUnitKerja(Jabatan $jabatan, UnitKerja $unitKerja): Collection
    {
        if ($this->specialUserRequirementForJabatan($jabatan) === null) {
            return collect();
        }

        return $this->specialUserPositionQueryForJabatanAndUnitKerja($jabatan, $unitKerja)->get();
    }

    public function specialUserPositionQueryForJabatanAndUnitKerja(Jabatan $jabatan, UnitKerja $unitKerja): Builder
    {
        return UserPosition::query()
            ->select([
                'id',
                'user_id',
                'jabatan_id',
                'instansi_id',
                'unit_kerja_id',
                'is_active',
                'started_at',
                'ended_at',
                'last_used_at',
            ])
            ->with([
                'user:id,nik,nama',
                'jabatan:id,kode,nama',
                'instansi:id,kode,nama',
                'unitKerja:id,kode,nama',
            ])
            ->availableForSelection()
            ->withActiveReferences()
            ->whereBelongsTo($jabatan)
            ->whereBelongsTo($unitKerja, 'unitKerja')
            ->preferredFirst();
    }

    public function isAdminSuperJabatan(Jabatan $jabatan): bool
    {
        return in_array($jabatan->kode, $this->adminSuperJabatanCodes(), true);
    }

    public function isJabatanSelectable(Jabatan $jabatan): bool
    {
        return $jabatan->is_active === true
            && ! in_array($jabatan->kode, $this->excludedJabatanCodes(), true);
    }

    public function isInstansiAllowedForJabatan(Jabatan $jabatan, Instansi $instansi): bool
    {
        return $instansi->is_active === true
            && in_array($instansi->kode, $this->instansiCodesForJabatan($jabatan), true);
    }

    public function isUnitKerjaAllowedForJabatanAndInstansi(Jabatan $jabatan, Instansi $instansi, UnitKerja $unitKerja): bool
    {
        return $this->unitKerjaQueryForJabatanAndInstansi($jabatan, $instansi)
            ->whereKey($unitKerja->getKey())
            ->exists();
    }

    public function isSpecialUserPositionAllowed(Jabatan $jabatan, UnitKerja $unitKerja, UserPosition $userPosition): bool
    {
        return $this->specialUserPositionQueryForJabatanAndUnitKerja($jabatan, $unitKerja)
            ->whereKey($userPosition->getKey())
            ->exists();
    }

    /**
     * @return array<string, list<string>>
     */
    public function validateCombination(
        Jabatan $jabatan,
        Instansi $instansi,
        UnitKerja $unitKerja,
        ?UserPosition $pptkUserPosition = null,
        ?UserPosition $budUserPosition = null
    ): array {
        $errors = [];

        if (! $this->isJabatanSelectable($jabatan)) {
            $this->addError($errors, 'jabatan_id', 'Jabatan tidak tersedia untuk acting context Admin Super.');
        }

        if (! $this->isInstansiAllowedForJabatan($jabatan, $instansi)) {
            $this->addError($errors, 'instansi_id', $this->instansiErrorMessageForJabatan($jabatan));
        }

        if (! $this->isUnitKerjaAllowedForJabatanAndInstansi($jabatan, $instansi, $unitKerja)) {
            $this->addError($errors, 'unit_kerja_id', $this->unitKerjaErrorMessageForJabatan($jabatan));
        }

        $this->validateSpecialUserPositions($errors, $jabatan, $unitKerja, $pptkUserPosition, $budUserPosition);

        return $errors;
    }

    public function isCombinationAllowed(Jabatan $jabatan, Instansi $instansi, UnitKerja $unitKerja): bool
    {
        return $this->isJabatanSelectable($jabatan)
            && $this->isInstansiAllowedForJabatan($jabatan, $instansi)
            && $this->isUnitKerjaAllowedForJabatanAndInstansi($jabatan, $instansi, $unitKerja);
    }

    public function specialUserRequirementForJabatan(Jabatan $jabatan): ?string
    {
        $requirement = config("position_rules.special_user_by_jabatan_code.{$jabatan->kode}");

        return is_string($requirement) && $requirement !== '' ? $requirement : null;
    }

    /**
     * @return list<string>
     */
    public function instansiCodesForJabatan(Jabatan $jabatan): array
    {
        if ($this->isAdminSuperJabatan($jabatan)) {
            return [];
        }

        $codes = config("position_rules.jabatan_to_instansi_codes.{$jabatan->kode}", []);

        return $this->stringList($codes);
    }

    public function unitScopePolicyForJabatan(Jabatan $jabatan): string
    {
        $policy = config("position_rules.unit_scope_policy_by_jabatan_code.{$jabatan->kode}");

        return is_string($policy) && $policy !== '' ? $policy : self::POLICY_DEFAULT_INSTANSI_UNITS;
    }

    public function isFixedBkadJabatan(Jabatan $jabatan): bool
    {
        return $this->unitScopePolicyForJabatan($jabatan) === self::POLICY_FIXED_BKAD;
    }

    public function isSkpdInstansi(Instansi $instansi): bool
    {
        return $instansi->kode === $this->skpdInstansiCode();
    }

    public function isKecamatanInstansi(Instansi $instansi): bool
    {
        return in_array($instansi->kode, $this->kecamatanInstansiCodes(), true);
    }

    private function fixedBkadUnitKerjaQuery(Instansi $instansi): Builder
    {
        if (! $this->isSkpdInstansi($instansi)) {
            return $this->emptyUnitKerjaQuery();
        }

        return $this->defaultInstansiUnitKerjaQuery($instansi)
            ->where('kode', $this->bkadUnitKerjaCode());
    }

    private function skpdPlusKecamatanRootsUnitKerjaQuery(Instansi $instansi): Builder
    {
        if (! $this->isSkpdInstansi($instansi)) {
            return $this->emptyUnitKerjaQuery();
        }

        $kecamatanRootCodes = $this->kecamatanRootUnitKerjaCodes();

        return $this->baseUnitKerjaQuery()
            ->where(function (Builder $query) use ($instansi, $kecamatanRootCodes): void {
                $query->whereBelongsTo($instansi);

                if ($kecamatanRootCodes !== []) {
                    $query->orWhereIn('kode', $kecamatanRootCodes);
                }
            });
    }

    private function kelurahanOnlyWhenKecamatanUnitKerjaQuery(Instansi $instansi): Builder
    {
        $query = $this->defaultInstansiUnitKerjaQuery($instansi);

        if ($this->isKecamatanInstansi($instansi)) {
            $query->whereNotIn('kode', $this->kecamatanRootUnitKerjaCodes());
        }

        return $query;
    }

    private function pptkMixedScopeUnitKerjaQuery(Instansi $instansi): Builder
    {
        if ($this->isSkpdInstansi($instansi)) {
            return $this->skpdPlusKecamatanRootsUnitKerjaQuery($instansi);
        }

        return $this->kelurahanOnlyWhenKecamatanUnitKerjaQuery($instansi);
    }

    private function defaultInstansiUnitKerjaQuery(Instansi $instansi): Builder
    {
        return $this->baseUnitKerjaQuery()
            ->whereBelongsTo($instansi);
    }

    private function baseUnitKerjaQuery(): Builder
    {
        return UnitKerja::query()
            ->select(['id', 'instansi_id', 'parent_id', 'kode', 'nama', 'nama_singkat', 'jenis', 'is_active', 'sort_order'])
            ->active()
            ->effective()
            ->ordered();
    }

    private function emptyUnitKerjaQuery(): Builder
    {
        return $this->baseUnitKerjaQuery()
            ->whereKey([]);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function validateSpecialUserPositions(
        array &$errors,
        Jabatan $jabatan,
        UnitKerja $unitKerja,
        ?UserPosition $pptkUserPosition,
        ?UserPosition $budUserPosition
    ): void {
        $requirement = $this->specialUserRequirementForJabatan($jabatan);

        if ($requirement === self::SPECIAL_USER_PPTK) {
            $this->validateRequiredSpecialUserPosition(
                $errors,
                'pptk_user_position_id',
                'UserPosition PPTK wajib dipilih untuk konteks PPTK.',
                'UserPosition PPTK tidak sesuai dengan jabatan dan unit kerja yang dipilih.',
                $jabatan,
                $unitKerja,
                $pptkUserPosition
            );

            if ($budUserPosition instanceof UserPosition) {
                $this->addError($errors, 'bud_user_position_id', 'UserPosition BUD tidak diperlukan untuk konteks PPTK.');
            }

            return;
        }

        if ($requirement === self::SPECIAL_USER_BUD) {
            $this->validateRequiredSpecialUserPosition(
                $errors,
                'bud_user_position_id',
                'UserPosition BUD wajib dipilih untuk konteks BUD atau Kuasa BUD.',
                'UserPosition BUD tidak sesuai dengan jabatan dan unit kerja yang dipilih.',
                $jabatan,
                $unitKerja,
                $budUserPosition
            );

            if ($pptkUserPosition instanceof UserPosition) {
                $this->addError($errors, 'pptk_user_position_id', 'UserPosition PPTK tidak diperlukan untuk konteks BUD.');
            }

            return;
        }

        if ($pptkUserPosition instanceof UserPosition) {
            $this->addError($errors, 'pptk_user_position_id', 'UserPosition PPTK tidak diperlukan untuk jabatan yang dipilih.');
        }

        if ($budUserPosition instanceof UserPosition) {
            $this->addError($errors, 'bud_user_position_id', 'UserPosition BUD tidak diperlukan untuk jabatan yang dipilih.');
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function validateRequiredSpecialUserPosition(
        array &$errors,
        string $field,
        string $requiredMessage,
        string $invalidMessage,
        Jabatan $jabatan,
        UnitKerja $unitKerja,
        ?UserPosition $userPosition
    ): void {
        if (! $userPosition instanceof UserPosition) {
            $this->addError($errors, $field, $requiredMessage);

            return;
        }

        if (! $this->isSpecialUserPositionAllowed($jabatan, $unitKerja, $userPosition)) {
            $this->addError($errors, $field, $invalidMessage);
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function addError(array &$errors, string $field, string $message): void
    {
        $errors[$field] ??= [];
        $errors[$field][] = $message;
    }

    private function instansiErrorMessageForJabatan(Jabatan $jabatan): string
    {
        if ($this->isFixedBkadJabatan($jabatan)) {
            return 'Instansi untuk jabatan ini harus SKPD.';
        }

        return 'Instansi tidak diperbolehkan untuk jabatan yang dipilih.';
    }

    private function unitKerjaErrorMessageForJabatan(Jabatan $jabatan): string
    {
        if ($this->isFixedBkadJabatan($jabatan)) {
            return 'Unit kerja untuk jabatan ini harus BKAD.';
        }

        return 'Unit kerja tidak diperbolehkan untuk jabatan dan instansi yang dipilih.';
    }

    /**
     * @return list<string>
     */
    private function adminSuperJabatanCodes(): array
    {
        return $this->stringList(config('position_rules.admin_super_jabatan_codes', []));
    }

    /**
     * @return list<string>
     */
    private function excludedJabatanCodes(): array
    {
        return $this->stringList(config('position_rules.manual_context.excluded_jabatan_codes', []));
    }

    /**
     * @return list<string>
     */
    private function kecamatanInstansiCodes(): array
    {
        return $this->stringList(config('position_rules.instansi.kecamatan_codes', []));
    }

    /**
     * @return list<string>
     */
    private function kecamatanRootUnitKerjaCodes(): array
    {
        return $this->stringList(config('position_rules.unit_kerja.kecamatan_root_codes', []));
    }

    private function skpdInstansiCode(): string
    {
        $code = config('position_rules.instansi.skpd_code', 'SKPD');

        return is_string($code) && $code !== '' ? $code : 'SKPD';
    }

    private function bkadUnitKerjaCode(): string
    {
        $code = config('position_rules.unit_kerja.bkad_code', 'SKPD_BKAD');

        return is_string($code) && $code !== '' ? $code : 'SKPD_BKAD';
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
}
