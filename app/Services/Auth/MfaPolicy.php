<?php

namespace App\Services\Auth;

use App\Models\Jabatan;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Support\Carbon;

class MfaPolicy
{
    public const METHOD_TOTP = 'totp';

    public const METHOD_RECOVERY_CODE = 'recovery_code';

    public function __construct(private AdminSuperPositionScope $adminSuperPositionScope) {}

    public function enabled(): bool
    {
        return (bool) config('auth.mfa.enabled', true);
    }

    public function method(): string
    {
        $method = config('auth.mfa.method', self::METHOD_TOTP);

        return is_string($method) && $method !== '' ? $method : self::METHOD_TOTP;
    }

    public function usesTotp(): bool
    {
        return $this->method() === self::METHOD_TOTP;
    }

    public function supportsConfiguredMethod(): bool
    {
        return $this->usesTotp();
    }

    public function isEnrolled(User $user): bool
    {
        return filled($user->mfa_secret)
            && $user->mfa_enabled_at instanceof Carbon
            && $user->mfa_confirmed_at instanceof Carbon;
    }

    public function hasPendingEnrollment(User $user): bool
    {
        return filled($user->mfa_pending_secret)
            && $user->mfa_pending_secret_created_at instanceof Carbon;
    }

    public function availableFor(UserPosition $realActiveUserPosition): bool
    {
        if (! $this->enabled() || ! $this->supportsConfiguredMethod()) {
            return false;
        }

        if ($this->isAdminSuperPosition($realActiveUserPosition)) {
            return true;
        }

        return (bool) config('auth.mfa.non_admin.available', true);
    }

    public function requiredFor(UserPosition $realActiveUserPosition, ?User $user = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        if ($this->isAdminSuperPosition($realActiveUserPosition)) {
            return (bool) config('auth.mfa.admin_super.required', true);
        }

        if (! $this->availableFor($realActiveUserPosition)) {
            return false;
        }

        if ((bool) config('auth.mfa.non_admin.required', false)) {
            return true;
        }

        return $user instanceof User
            && $this->isEnrolled($user)
            && (bool) config('auth.mfa.non_admin.enforce_when_enabled', true);
    }

    public function setupRequiredFor(User $user, UserPosition $realActiveUserPosition): bool
    {
        return $this->requiredFor($realActiveUserPosition, $user)
            && ! $this->isEnrolled($user);
    }

    public function challengeRequiredFor(User $user, UserPosition $realActiveUserPosition): bool
    {
        return $this->requiredFor($realActiveUserPosition, $user)
            && $this->isEnrolled($user);
    }

    public function verifiedTtlMinutes(UserPosition $realActiveUserPosition): int
    {
        if ($this->isAdminSuperPosition($realActiveUserPosition)) {
            return max(1, (int) config('auth.mfa.admin_super.verified_ttl_minutes', 30));
        }

        return max(1, (int) config('auth.mfa.non_admin.verified_ttl_minutes', config('auth.mfa.admin_super.verified_ttl_minutes', 30)));
    }

    public function verifiedExpiresAt(UserPosition $realActiveUserPosition, ?Carbon $verifiedAt = null): Carbon
    {
        return ($verifiedAt ?? now())->copy()->addMinutes($this->verifiedTtlMinutes($realActiveUserPosition));
    }

    public function trustedDeviceAllowed(UserPosition $realActiveUserPosition): bool
    {
        if ($this->isAdminSuperPosition($realActiveUserPosition)) {
            return (bool) config('auth.mfa.admin_super.allow_trusted_device', false);
        }

        return (bool) config('auth.mfa.non_admin.allow_trusted_device', true);
    }

    public function totpDigits(): int
    {
        return max(6, (int) config('auth.mfa.totp.digits', 6));
    }

    public function totpPeriodSeconds(): int
    {
        return max(1, (int) config('auth.mfa.totp.period_seconds', 30));
    }

    public function totpWindow(): int
    {
        return max(0, (int) config('auth.mfa.totp.window', 1));
    }

    public function recoveryCodeCount(): int
    {
        return max(1, (int) config('auth.mfa.recovery_codes.count', 8));
    }

    public function isAdminSuperPosition(UserPosition $userPosition): bool
    {
        $userPosition->loadMissing('jabatan');

        return $userPosition->jabatan instanceof Jabatan
            && $this->adminSuperPositionScope->isAdminSuperJabatan($userPosition->jabatan);
    }
}
