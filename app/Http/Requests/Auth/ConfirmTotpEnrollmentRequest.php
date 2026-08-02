<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use LogicException;

class ConfirmTotpEnrollmentRequest extends FormRequest
{
    private ?UserPosition $realActiveUserPosition = null;

    public function authorize(CurrentUserContext $currentUserContext): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'one_time_password' => [
                'bail',
                'required',
                'string',
                'max:20',
                'regex:/^[0-9\-\s]+$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'one_time_password.max' => 'Kode authenticator tidak valid.',
            'one_time_password.regex' => 'Kode authenticator hanya boleh berisi angka.',
            'one_time_password.required' => 'Kode authenticator wajib diisi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'one_time_password' => 'kode authenticator',
        ];
    }

    public function oneTimePassword(): string
    {
        return $this->string('one_time_password')->trim()->toString();
    }

    public function realActiveUserPosition(): UserPosition
    {
        if (! $this->realActiveUserPosition instanceof UserPosition) {
            throw new LogicException('Real active user position is only available after authorization passes.');
        }

        return $this->realActiveUserPosition;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'one_time_password' => Str::of((string) $this->input('one_time_password'))->trim()->toString(),
        ]);
    }
}
