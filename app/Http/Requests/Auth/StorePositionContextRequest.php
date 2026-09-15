<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

class StorePositionContextRequest extends FormRequest
{
    private ?UserPosition $selectedUserPosition = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
                        $fail('Posisi kerja tidak tersedia atau sudah tidak aktif.');

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
            'user_position_id.required' => 'Posisi kerja wajib dipilih.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_position_id' => 'posisi kerja',
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
