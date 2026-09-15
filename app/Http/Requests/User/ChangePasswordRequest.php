<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'old_password.current_password' => 'Password lama yang Anda masukkan tidak sesuai.',
            'old_password.required' => 'Password lama wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
            'password.different' => 'Password baru harus berbeda dari password lama.',
            'password.max' => 'Password baru maksimal :max karakter.',
            'password.min' => 'Password baru minimal :min karakter.',
            'password.mixed' => 'Password baru harus berisi huruf besar dan huruf kecil.',
            'password.numbers' => 'Password baru harus berisi angka.',
            'password.required' => 'Password baru wajib diisi.',
            'password.symbols' => 'Password baru harus berisi simbol.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'old_password' => 'password lama',
            'password' => 'password baru',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('password.change')
                ->withErrors($validator)
                ->with('password_change_validation_failed', true)
        );
    }
}
