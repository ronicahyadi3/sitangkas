<?php

namespace App\Http\Controllers\Users;

use App\Actions\Auth\ResetUserMfa;
use App\Actions\UserSecurity\ForceUserPasswordChange;
use App\Actions\UserSecurity\LockUserAccount;
use App\Actions\UserSecurity\UnlockUserAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ForceUserPasswordChangeRequest;
use App\Http\Requests\User\LockUserAccountRequest;
use App\Http\Requests\User\ResetUserMfaRequest;
use App\Http\Requests\User\UnlockUserAccountRequest;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\MfaPolicy;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserSecurityController extends Controller
{
    public function __construct(
        private ActivePositionService $activePositionService,
        private UserManagementAccessService $userManagementAccessService,
        private MfaPolicy $mfaPolicy
    ) {}

    public function show(Request $request, User $user): JsonResponse
    {
        $actor = $this->activePositionService->get();

        if (! $this->userManagementAccessService->canViewUser($user, $actor)) {
            return $this->forbidden('User ini tidak termasuk scope pengelolaan jabatan aktif Anda.');
        }

        return response()->json([
            'ok' => true,
            'data' => $this->securityPayload($user, $actor),
        ]);
    }

    public function forcePasswordChange(
        ForceUserPasswordChangeRequest $request,
        User $user,
        ForceUserPasswordChange $forceUserPasswordChange
    ): JsonResponse {
        try {
            $actor = $this->authenticatedActor($request);

            $forceUserPasswordChange->handle($request, $user, $actor, $request->reason());

            return $this->success($user, 'User akan diminta mengganti password saat login berikutnya.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            return $this->failed($throwable, $user, 'force_password_change');
        }
    }

    public function lock(
        LockUserAccountRequest $request,
        User $user,
        LockUserAccount $lockUserAccount
    ): JsonResponse {
        try {
            $actor = $this->authenticatedActor($request);

            $lockUserAccount->handle(
                $request,
                $user,
                $actor,
                $request->reason(),
                $request->lockedUntil()
            );

            return $this->success($user, 'Akun user berhasil dikunci.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            return $this->failed($throwable, $user, 'lock_account');
        }
    }

    public function unlock(
        UnlockUserAccountRequest $request,
        User $user,
        UnlockUserAccount $unlockUserAccount
    ): JsonResponse {
        try {
            $actor = $this->authenticatedActor($request);

            $unlockUserAccount->handle($request, $user, $actor, $request->reason());

            return $this->success($user, 'Akun user berhasil dibuka kembali.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            return $this->failed($throwable, $user, 'unlock_account');
        }
    }

    public function resetMfa(
        ResetUserMfaRequest $request,
        User $user,
        ResetUserMfa $resetUserMfa
    ): JsonResponse {
        try {
            $actor = $this->authenticatedActor($request);

            if (! $this->mfaPolicy->isEnrolled($user) && ! $this->mfaPolicy->hasPendingEnrollment($user)) {
                throw ValidationException::withMessages([
                    'user' => 'Akun ini belum memiliki MFA yang perlu direset.',
                ]);
            }

            $result = $resetUserMfa->handle($user, $actor, $request->reason(), $request);

            Log::channel('module_users')->warning('User MFA reset via management users', [
                'actor_id' => $actor->getKey(),
                'target_user_id' => $user->getKey(),
                'reason' => $request->reason(),
                'mfa_was_enrolled' => $result['mfa_was_enrolled'],
                'pending_enrollment_was_present' => $result['pending_enrollment_was_present'],
                'revoked_database_session_count' => $result['revoked_database_session_count'],
            ]);

            return $this->success($user, 'MFA user berhasil direset.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            return $this->failed($throwable, $user, 'reset_mfa');
        }
    }

    private function success(User $user, string $message): JsonResponse
    {
        $user->refresh();

        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $this->securityPayload($user, $this->activePositionService->get()),
        ]);
    }

    private function authenticatedActor(Request $request): User
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
        ], 403);
    }

    private function failed(Throwable $throwable, User $user, string $action): JsonResponse
    {
        Log::channel('module_users')->error('User security action failed', [
            'actor_id' => auth()->id(),
            'target_user_id' => $user->getKey(),
            'action' => $action,
            'error' => $throwable->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Gagal memproses aksi keamanan akun.',
        ], 500);
    }

    /**
     * @return array<string, mixed>
     */
    private function securityPayload(User $user, ?UserPosition $actor): array
    {
        $user->loadMissing(['passwordResetBy', 'statusChangedBy']);

        $canManageSecurity = $this->userManagementAccessService->canManageUser($user, $actor);
        $canResetMfa = $this->userManagementAccessService->isFullAdmin($actor) && $canManageSecurity;
        $mfaIsEnrolled = $this->mfaPolicy->isEnrolled($user);
        $mfaIsPending = $this->mfaPolicy->hasPendingEnrollment($user);
        $hasMfaState = $mfaIsEnrolled || $mfaIsPending;

        return [
            'id' => $user->getRouteKey(),
            'nama' => $user->nama,
            'nik' => $user->nik,
            'email' => $user->email,
            'status' => [
                'value' => $user->status,
                'label' => $this->statusLabel($user->status),
                'badge_class' => $this->statusBadgeClass($user),
                'is_locked' => $user->isLocked(),
                'reason' => $user->status_reason,
                'changed_at' => $this->dateTimeLabel($user->status_changed_at),
                'changed_by' => $user->statusChangedBy?->nama,
                'locked_at' => $this->dateTimeLabel($user->locked_at),
                'locked_until' => $this->dateTimeLabel($user->locked_until, 'Tidak dibatasi waktu'),
                'lock_reason' => $user->lock_reason,
            ],
            'password' => [
                'must_change' => (bool) $user->must_change_password,
                'has_expired' => $user->hasExpiredPassword(),
                'requires_change' => $user->requiresPasswordChange(),
                'changed_at' => $this->dateTimeLabel($user->password_changed_at),
                'expires_at' => $this->dateTimeLabel($user->password_expires_at),
                'reset_at' => $this->dateTimeLabel($user->password_reset_at),
                'reset_by' => $user->passwordResetBy?->nama,
            ],
            'mfa' => [
                'label' => $this->mfaStatusLabel($mfaIsEnrolled, $mfaIsPending),
                'badge_class' => $this->mfaBadgeClass($mfaIsEnrolled, $mfaIsPending),
                'is_enrolled' => $mfaIsEnrolled,
                'has_pending_enrollment' => $mfaIsPending,
                'enabled_at' => $this->dateTimeLabel($user->mfa_enabled_at),
                'confirmed_at' => $this->dateTimeLabel($user->mfa_confirmed_at),
                'last_used_at' => $this->dateTimeLabel($user->mfa_last_used_at),
                'recovery_codes_remaining' => $this->recoveryCodeCount($user->mfa_recovery_codes),
            ],
            'activity' => [
                'last_login_at' => $this->dateTimeLabel($user->last_login_at),
                'failed_login_count' => (int) $user->consecutive_failed_login_count,
                'last_failed_login_at' => $this->dateTimeLabel($user->last_failed_login_at),
                'sessions_invalidated_at' => $this->dateTimeLabel($user->sessions_invalidated_at),
            ],
            'permissions' => [
                'can_manage_security' => $canManageSecurity,
                'can_force_password_change' => $canManageSecurity && ! $user->requiresPasswordChange(),
                'can_lock' => $canManageSecurity && $user->status === User::STATUS_ACTIVE && ! $user->isLocked(),
                'can_unlock' => $canManageSecurity && (
                    $user->status === User::STATUS_LOCKED
                    || ($user->status === User::STATUS_ACTIVE && $user->isLocked())
                ),
                'can_reset_mfa' => $canResetMfa && $hasMfaState,
            ],
            'urls' => [
                'force_password_change' => route('users.security.force-password-change', $user),
                'lock' => route('users.security.lock', $user),
                'unlock' => route('users.security.unlock', $user),
                'reset_mfa' => route('users.security.reset-mfa', $user),
            ],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            User::STATUS_ACTIVE => 'Aktif',
            User::STATUS_INACTIVE => 'Nonaktif',
            User::STATUS_LOCKED => 'Terkunci',
            User::STATUS_PENDING => 'Pending',
            User::STATUS_SUSPENDED => 'Suspended',
            default => str($status)->replace(['-', '_'], ' ')->title()->toString(),
        };
    }

    private function statusBadgeClass(User $user): string
    {
        if ($user->isLocked() || $user->status === User::STATUS_LOCKED) {
            return 'bg-gradient-danger';
        }

        return match ($user->status) {
            User::STATUS_ACTIVE => 'bg-gradient-success',
            User::STATUS_PENDING => 'bg-gradient-warning',
            User::STATUS_INACTIVE => 'bg-gradient-secondary',
            User::STATUS_SUSPENDED => 'bg-gradient-danger',
            default => 'bg-gradient-secondary',
        };
    }

    private function mfaStatusLabel(bool $isEnrolled, bool $isPending): string
    {
        if ($isEnrolled) {
            return 'Aktif';
        }

        if ($isPending) {
            return 'Setup Pending';
        }

        return 'Belum Aktif';
    }

    private function mfaBadgeClass(bool $isEnrolled, bool $isPending): string
    {
        if ($isEnrolled) {
            return 'bg-gradient-success';
        }

        if ($isPending) {
            return 'bg-gradient-warning';
        }

        return 'bg-gradient-secondary';
    }

    private function dateTimeLabel(?DateTimeInterface $dateTime, string $emptyLabel = '-'): string
    {
        if (! $dateTime instanceof DateTimeInterface) {
            return $emptyLabel;
        }

        return $dateTime->format('d/m/Y H:i');
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
