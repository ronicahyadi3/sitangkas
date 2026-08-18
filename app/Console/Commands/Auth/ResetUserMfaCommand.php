<?php

namespace App\Console\Commands\Auth;

use App\Actions\Auth\ResetUserMfa;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Signature('auth:mfa-reset {--nik= : Target user NIK} {--user-id= : Target user ID} {--actor-user-id= : Operator user ID for audit} {--reason= : Reset reason stored in audit metadata} {--force : Required in production and skips interactive confirmation}')]
#[Description('Reset a user MFA enrollment, revoke sessions, and write an authentication audit event.')]
class ResetUserMfaCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ResetUserMfa $resetUserMfa): int
    {
        $target = $this->targetUser();

        if (! $target instanceof User) {
            return self::INVALID;
        }

        $actor = $this->actorUser();

        if ($this->optionString('actor-user-id') !== null && ! $actor instanceof User) {
            return self::INVALID;
        }

        $this->displayTarget($target, $actor);

        if (! $this->resetIsConfirmed($target)) {
            $this->warn('Reset MFA dibatalkan.');

            return self::FAILURE;
        }

        try {
            $result = $resetUserMfa->handle(
                user: $target,
                actor: $actor,
                reason: $this->optionString('reason')
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            $this->error((string) $message);

            return self::FAILURE;
        }

        $this->info('MFA user berhasil direset.');
        $this->table(
            ['Item', 'Nilai'],
            [
                ['Target user ID', (string) $target->getKey()],
                ['NIK', $target->nik ?? '-'],
                ['Nama', $target->nama ?? '-'],
                ['MFA sebelumnya aktif', $result['mfa_was_enrolled'] ? 'ya' : 'tidak'],
                ['Pending setup sebelumnya', $result['pending_enrollment_was_present'] ? 'ya' : 'tidak'],
                ['Recovery code lama', (string) $result['previous_recovery_code_count']],
                ['Session database direvoke', (string) $result['revoked_database_session_count']],
                ['Remember token dirotasi', $result['remember_token_rotated'] ? 'ya' : 'tidak'],
                ['Audit event ID', (string) $result['audit_event']->getKey()],
                ['Reset at', $result['reset_at']->timezone(config('app.timezone'))->toDateTimeString()],
            ]
        );

        return self::SUCCESS;
    }

    private function targetUser(): ?User
    {
        $nik = $this->normalizedNik();
        $userId = $this->optionString('user-id');

        if (($nik === null && $userId === null) || ($nik !== null && $userId !== null)) {
            $this->error('Gunakan tepat salah satu opsi: --nik atau --user-id.');

            return null;
        }

        $user = $nik !== null
            ? User::query()->where('nik', $nik)->first()
            : User::query()->whereKey($userId)->first();

        if (! $user instanceof User) {
            $this->error('User target tidak ditemukan.');

            return null;
        }

        return $user;
    }

    private function actorUser(): ?User
    {
        $actorUserId = $this->optionString('actor-user-id');

        if ($actorUserId === null) {
            return null;
        }

        $actor = User::query()->whereKey($actorUserId)->first();

        if (! $actor instanceof User) {
            $this->error('Actor user tidak ditemukan.');

            return null;
        }

        return $actor;
    }

    private function displayTarget(User $target, ?User $actor): void
    {
        $this->table(
            ['Field', 'Value'],
            [
                ['Target user ID', (string) $target->getKey()],
                ['NIK', $target->nik ?? '-'],
                ['Nama', $target->nama ?? '-'],
                ['Email', $target->email ?? '-'],
                ['Status', $target->status ?? '-'],
                ['MFA aktif', $target->mfa_enabled_at ? 'ya' : 'tidak'],
                ['MFA terakhir dipakai', $target->mfa_last_used_at?->timezone(config('app.timezone'))->toDateTimeString() ?? '-'],
                ['Recovery code tersimpan', (string) $this->recoveryCodeCount($target->mfa_recovery_codes)],
                ['Actor user ID', $actor instanceof User ? (string) $actor->getKey() : '-'],
                ['Reason', $this->optionString('reason') ?? '-'],
            ]
        );
    }

    private function resetIsConfirmed(User $target): bool
    {
        if (app()->isProduction() && ! $this->force()) {
            $this->error('Production wajib memakai opsi --force untuk reset MFA.');

            return false;
        }

        if ($this->force()) {
            return true;
        }

        return $this->confirm(
            "Reset MFA untuk {$target->nama} ({$target->nik}) dan revoke session aktif?",
            false
        );
    }

    private function normalizedNik(): ?string
    {
        $nik = $this->optionString('nik');

        if ($nik === null) {
            return null;
        }

        $digitsOnlyNik = Str::of($nik)->replaceMatches('/\D+/', '')->toString();

        return $digitsOnlyNik !== '' ? $digitsOnlyNik : $nik;
    }

    private function optionString(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_string($value)) {
            return null;
        }

        $value = Str::of($value)->trim()->toString();

        return $value !== '' ? $value : null;
    }

    private function force(): bool
    {
        return (bool) $this->option('force');
    }

    private function recoveryCodeCount(mixed $recoveryCodes): int
    {
        if (! is_array($recoveryCodes)) {
            return 0;
        }

        return count(array_filter(
            $recoveryCodes,
            static fn (mixed $recoveryCode): bool => is_string($recoveryCode) && $recoveryCode !== ''
        ));
    }
}
