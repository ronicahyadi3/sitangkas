<?php

namespace App\Services\User;

use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Http\Request;

class ActivePositionService
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private Request $request
    ) {}

    public function get(): ?UserPosition
    {
        return $this->currentUserContext->activePosition($this->request);
    }

    public function real(): ?UserPosition
    {
        return $this->currentUserContext->realActivePosition($this->request);
    }

    public function managementActor(): ?UserPosition
    {
        $realActivePosition = $this->real();

        if (
            $realActivePosition instanceof UserPosition
            && $this->currentUserContext->isAdminSuperPosition($realActivePosition)
        ) {
            return $realActivePosition;
        }

        return $this->get();
    }

    public function selectedYear(): int
    {
        return $this->currentUserContext->activeYear($this->request)
            ?? (int) ($this->request->user()?->tahun_aktif ?: now()->year);
    }

    /**
     * @param  list<int>  $jabatanIds
     */
    public function hasJabatan(array $jabatanIds): bool
    {
        $activePosition = $this->get();

        return $activePosition instanceof UserPosition
            && in_array((int) $activePosition->jabatan_id, $jabatanIds, true);
    }
}
