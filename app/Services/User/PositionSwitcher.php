<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PositionSwitcher
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private Request $request
    ) {}

    public function setActive(User $user, int|string $positionId): UserPosition
    {
        $position = $user->userPositions()
            ->with(['jabatan', 'instansi', 'unitKerja'])
            ->whereKey($positionId)
            ->firstOrFail();

        if (! $position->is_active) {
            $position->forceFill([
                'is_active' => true,
                'activated_at' => now(),
                'activated_by_user_id' => $this->request->user()?->id,
                'deactivated_at' => null,
                'deactivated_by_user_id' => null,
                'deactivation_reason' => null,
            ])->save();
        }

        if ((int) ($this->request->user()?->id ?? 0) === (int) $user->id) {
            if (! $position->isEffective()) {
                throw ValidationException::withMessages([
                    'position_id' => 'Masa berlaku posisi ini belum aktif atau sudah berakhir.',
                ]);
            }

            $this->currentUserContext->activatePosition($this->request, $position);
            $position->markAsUsed();
        }

        return $position->fresh(['jabatan', 'instansi', 'unitKerja']) ?? $position;
    }
}
