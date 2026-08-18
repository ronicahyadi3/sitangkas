<?php

namespace App\Services\User;

use App\Models\Jabatan;
use App\Models\UserPosition;
use App\Models\UserPositionYearPermission;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Http\Request;

class YearAccessService
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private Request $request
    ) {}

    public function selectedYear(): int
    {
        return $this->currentUserContext->activeYear($this->request)
            ?? (int) ($this->request->user()?->tahun_aktif ?: now()->year);
    }

    public function currentYear(): int
    {
        return (int) now()->year;
    }

    public function isCurrentYear(?int $year = null): bool
    {
        return ($year ?? $this->selectedYear()) === $this->currentYear();
    }

    public function isHistoricalYear(?int $year = null): bool
    {
        return ($year ?? $this->selectedYear()) < $this->currentYear();
    }

    public function canWrite(?UserPosition $position = null, ?int $year = null): bool
    {
        $position ??= $this->currentUserContext->activePosition($this->request);
        $targetYear = $year ?? $this->selectedYear();

        if (! $position instanceof UserPosition) {
            return false;
        }

        if ($this->isReadOnlyJabatan($position)) {
            return false;
        }

        if (! $this->isHistoricalYear($targetYear)) {
            return true;
        }

        return $this->isOriginalSuperAdmin($position)
            || $this->hasHistoricalWriteOverride($position, $targetYear);
    }

    public function isReadOnly(?UserPosition $position = null, ?int $year = null): bool
    {
        return ! $this->canWrite($position, $year);
    }

    public function mode(?UserPosition $position = null, ?int $year = null): string
    {
        return $this->canWrite($position, $year) ? 'write' : 'read';
    }

    public function isOriginalSuperAdmin(?UserPosition $position = null): bool
    {
        $position ??= $this->currentUserContext->activePosition($this->request);

        if (! $position instanceof UserPosition) {
            return false;
        }

        $originalJabatanId = (int) ($position->getOriginal('jabatan_id') ?: $position->jabatan_id);
        $code = $this->jabatanCode($originalJabatanId);

        return in_array($code, $this->adminSuperJabatanCodes(), true);
    }

    public function canManageHistoricalAccess(?UserPosition $position = null): bool
    {
        return $this->isOriginalSuperAdmin($position);
    }

    public function hasHistoricalWriteOverride(?UserPosition $position = null, ?int $year = null): bool
    {
        $position ??= $this->currentUserContext->activePosition($this->request);

        if (! $position instanceof UserPosition) {
            return false;
        }

        return UserPositionYearPermission::query()
            ->historicalWrite()
            ->active()
            ->currentlyEffective()
            ->where('user_position_id', $position->getKey())
            ->where('tahun', $year ?? $this->selectedYear())
            ->exists();
    }

    public function recordHistoricalWriteUsage(?UserPosition $position = null, ?int $year = null): void
    {
        $position ??= $this->currentUserContext->activePosition($this->request);
        $targetYear = $year ?? $this->selectedYear();

        if (! $position instanceof UserPosition || ! $this->isHistoricalYear($targetYear)) {
            return;
        }

        UserPositionYearPermission::query()
            ->historicalWrite()
            ->active()
            ->currentlyEffective()
            ->where('user_position_id', $position->getKey())
            ->where('tahun', $targetYear)
            ->increment('usage_count', 1, [
                'last_used_at' => now(),
                'last_used_by_user_id' => $this->request->user()?->id,
                'last_used_by_position_id' => $position->getKey(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(?UserPosition $position = null, ?int $year = null): array
    {
        $position ??= $this->currentUserContext->activePosition($this->request);
        $targetYear = $year ?? $this->selectedYear();

        return [
            'tahun_aktif' => $targetYear,
            'tahun_sekarang' => $this->currentYear(),
            'is_current' => $this->isCurrentYear($targetYear),
            'is_historical' => $this->isHistoricalYear($targetYear),
            'can_write' => $this->canWrite($position, $targetYear),
            'mode' => $this->mode($position, $targetYear),
            'can_manage_historical_access' => $this->canManageHistoricalAccess($position),
        ];
    }

    private function isReadOnlyJabatan(UserPosition $position): bool
    {
        return in_array($this->jabatanCode((int) $position->jabatan_id), ['PIMPINAN', 'AUDITOR'], true);
    }

    private function jabatanCode(int $jabatanId): ?string
    {
        $positionJabatan = Jabatan::query()->whereKey($jabatanId)->value('kode');

        if (is_string($positionJabatan) && $positionJabatan !== '') {
            return $positionJabatan;
        }

        $legacy = config('position_rules.legacy.jabatan_id_to_code', []);

        return is_array($legacy) && is_string($legacy[$jabatanId] ?? null)
            ? $legacy[$jabatanId]
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
}
