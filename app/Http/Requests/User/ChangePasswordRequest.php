<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'old_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                'different:old_password',
                'max:255',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }
}
