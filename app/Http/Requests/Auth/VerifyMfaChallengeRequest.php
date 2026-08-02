<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\MfaPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use LogicException;

class VerifyMfaChallengeRequest extends FormRequest
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
            'challenge_method' => [
                'bail',
                'required',
                'string',
                Rule::in([
                    MfaPolicy::METHOD_TOTP,
                    MfaPolicy::METHOD_RECOVERY_CODE,
                ]),
            ],
            'one_time_password' => [
                'bail',
                'required_if:challenge_method,'.MfaPolicy::METHOD_TOTP,
                'nullable',
                'string',
                'max:20',
                'regex:/^[0-9\-\s]+$/',
            ],
            'recovery_code' => [
                'bail',
                'required_if:challenge_method,'.MfaPolicy::METHOD_RECOVERY_CODE,
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9\-\s]+$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'challenge_method.in' => 'Metode verifikasi MFA tidak valid.',
            'challenge_method.required' => 'Metode verifikasi MFA wajib dipilih.',
            'one_time_password.max' => 'Kode authenticator tidak valid.',
            'one_time_password.regex' => 'Kode authenticator hanya boleh berisi angka.',
            'one_time_password.required' => 'Kode authenticator wajib diisi.',
            'one_time_password.required_if' => 'Kode authenticator wajib diisi.',
            'recovery_code.max' => 'Recovery code tidak valid.',
            'recovery_code.regex' => 'Recovery code tidak valid.',
            'recovery_code.required_if' => 'Recovery code wajib diisi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'challenge_method' => 'metode verifikasi MFA',
            'one_time_password' => 'kode authenticator',
            'recovery_code' => 'recovery code',
        ];
    }

    public function challengeMethod(): string
    {
        return $this->string('challenge_method')->trim()->toString();
    }

    public function oneTimePassword(): string
    {
        return $this->string('one_time_password')->trim()->toString();
    }

    public function recoveryCode(): string
    {
        return $this->string('recovery_code')->trim()->toString();
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
            'challenge_method' => Str::of((string) $this->input('challenge_method'))->trim()->lower()->toString(),
            'one_time_password' => $this->has('one_time_password')
                ? Str::of((string) $this->input('one_time_password'))->trim()->toString()
                : null,
            'recovery_code' => $this->has('recovery_code')
                ? Str::of((string) $this->input('recovery_code'))->trim()->toString()
                : null,
        ]);
    }
}
