<?php

namespace App\Http\Requests\User;

use App\Models\UserManagementAuditEvent;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use Illuminate\Support\Carbon;

class LockUserAccountRequest extends UserSecurityRequest
{
    public function authorize(
        UserManagementAccessService $userManagementAccessService,
        ActivePositionService $activePositionService
    ): bool {
        return $this->authorizeFullAdminTargetUser(
            $userManagementAccessService,
            $activePositionService,
            UserManagementAuditEvent::EVENT_SECURITY_LOCK
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'locked_until' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function lockedUntil(): ?Carbon
    {
        $lockedUntil = $this->input('locked_until');

        return is_string($lockedUntil) && trim($lockedUntil) !== ''
            ? Carbon::parse($lockedUntil)
            : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'locked_until' => 'batas waktu kunci akun',
            'reason' => 'alasan',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'locked_until' => $this->nullableString('locked_until'),
        ]);
    }
}
