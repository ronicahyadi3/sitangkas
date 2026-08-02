<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

class StoreLoginContextRequest extends FormRequest
{
    private ?UserPosition $selectedUserPosition = null;

    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(CurrentUserContext $currentUserContext): array
    {
        return [
            'user_position_id' => [
                'bail',
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($currentUserContext): void {
                $user = $this->user();

                if (! $user instanceof User) {
                    $fail('Session pengguna tidak valid.');

                    return;
                }

                    $userPosition = $currentUserContext->selectablePosition($user, $value);

                if (! $userPosition instanceof UserPosition) {
                    $fail('Konteks kerja tidak tersedia atau sudah tidak aktif.');

                    return;
                }

                $this->selectedUserPosition = $userPosition;
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_position_id.required' => 'Konteks kerja wajib dipilih.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_position_id' => 'konteks kerja',
        ];
    }

    public function selectedUserPosition(): UserPosition
    {
        if (! $this->selectedUserPosition instanceof UserPosition) {
            throw new LogicException('Selected user position is only available after validation passes.');
        }

        return $this->selectedUserPosition;
    }
}
