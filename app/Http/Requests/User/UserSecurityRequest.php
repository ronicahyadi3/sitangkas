<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class UserSecurityRequest extends FormRequest
{
    protected function authorizeManagedTargetUser(
        UserManagementAccessService $userManagementAccessService,
        ActivePositionService $activePositionService
    ): bool {
        $targetUser = $this->targetUser();

        return $targetUser instanceof User
            && $this->user() instanceof User
            && $userManagementAccessService->canManageUser($targetUser, $activePositionService->get());
    }

    protected function authorizeFullAdminTargetUser(
        UserManagementAccessService $userManagementAccessService,
        ActivePositionService $activePositionService
    ): bool {
        $actor = $activePositionService->get();
        $targetUser = $this->targetUser();

        return $targetUser instanceof User
            && $this->user() instanceof User
            && $userManagementAccessService->isFullAdmin($actor)
            && $userManagementAccessService->canManageUser($targetUser, $actor);
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => $this->nullableString('reason'),
        ]);
    }

    protected function targetUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    protected function nullableString(string $key): ?string
    {
        $value = Str::of((string) $this->input($key))->trim()->toString();

        return $value === '' ? null : $value;
    }
}
