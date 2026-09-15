<?php

namespace App\Http\Requests\User;

use App\Models\UserManagementAuditEvent;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;

class ResetUserPasswordRequest extends UserSecurityRequest
{
    public function authorize(
        UserManagementAccessService $userManagementAccessService,
        ActivePositionService $activePositionService
    ): bool {
        return $this->authorizeFullAdminTargetUser(
            $userManagementAccessService,
            $activePositionService,
            UserManagementAuditEvent::EVENT_SECURITY_PASSWORD_RESET
        );
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
