<?php

namespace App\Http\Requests\User;

use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;

class ResetUserMfaRequest extends UserSecurityRequest
{
    public function authorize(
        UserManagementAccessService $userManagementAccessService,
        ActivePositionService $activePositionService
    ): bool {
        return $this->authorizeFullAdminTargetUser($userManagementAccessService, $activePositionService);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => 'alasan',
        ];
    }
}
