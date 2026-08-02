<?php

namespace App\Services\Auth;

use App\Models\Jabatan;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Support\Carbon;

class RememberMePolicy
{
    public function __construct(private AdminSuperPositionScope $adminSuperPositionScope) {}

    public function enabled(): bool
    {
        return (bool) config('auth.remember_me.enabled', true);
    }

    public function singleDeviceEnabled(): bool
    {
        return (bool) config('auth.remember_me.single_device', true);
    }

    public function durationMinutes(): int
    {
        return max(1, (int) config('auth.remember_me.duration_minutes', 1440));
    }

    public function expiresAt(?Carbon $from = null): Carbon
    {
        return ($from ?? now())->copy()->addMinutes($this->durationMinutes());
    }

    public function tokenHasExpired(User $user): bool
    {
        return $user->remember_token_expires_at === null
            || $user->remember_token_expires_at->isPast();
    }

    public function restoreNonAdminContext(): bool
    {
        return (bool) config('auth.remember_me.restore_non_admin_context', true);
    }

    public function allows(UserPosition $realActiveUserPosition): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($this->adminSuperAllowed()) {
            return true;
        }

        return ! $this->isAdminSuperPosition($realActiveUserPosition);
    }

    public function shouldRemember(bool $requestedRemember, UserPosition $realActiveUserPosition): bool
    {
        return $requestedRemember && $this->allows($realActiveUserPosition);
    }

    public function isAdminSuperPosition(UserPosition $userPosition): bool
    {
        $userPosition->loadMissing('jabatan');

        return $userPosition->jabatan instanceof Jabatan
            && $this->adminSuperPositionScope->isAdminSuperJabatan($userPosition->jabatan);
    }

    private function adminSuperAllowed(): bool
    {
        return (bool) config('auth.remember_me.admin_super_allowed', false);
    }
}
