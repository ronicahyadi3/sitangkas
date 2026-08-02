<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

class RegenerateMfaRecoveryCodesRequest extends FormRequest
{
    protected $redirectRoute = 'profile.security';

    private ?UserPosition $realActiveUserPosition = null;

    public function authorize(CurrentUserContext $currentUserContext): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        if (! $this->routeIs('profile.security.mfa.recovery_codes.regenerate')) {
            return false;
        }

        $realActiveUserPosition = $currentUserContext->realActivePosition($this);

        if (! $realActiveUserPosition instanceof UserPosition) {
            return false;
        }

        $this->realActiveUserPosition = $realActiveUserPosition;

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new LogicException('Authenticated user is only available after authorization passes.');
        }

        return $user;
    }

    public function realActiveUserPosition(): UserPosition
    {
        if (! $this->realActiveUserPosition instanceof UserPosition) {
            throw new LogicException('Real active user position is only available after authorization passes.');
        }

        return $this->realActiveUserPosition;
    }
}
