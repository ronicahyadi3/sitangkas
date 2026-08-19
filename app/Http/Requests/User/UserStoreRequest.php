<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserStoreRequest extends FormRequest
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
        $usersTable = (new User)->getTable();

        return [
            'nik' => ['required', 'string', 'max:25', Rule::unique($usersTable, 'nik')->withoutTrashed()],
            'nip' => ['nullable', 'string', 'max:30'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique($usersTable, 'email')->withoutTrashed()],
            'account_type' => ['required', 'string', Rule::in($this->allowedAccountTypes())],
            'status' => ['required', 'string', Rule::in($this->allowedStatuses())],
            'status_reason' => [
                Rule::requiredIf(fn (): bool => $this->requiresStatusReason()),
                'nullable',
                'string',
                'max:500',
            ],
            'tahun_aktif' => ['nullable', 'integer', 'between:2000,2100'],
            'password' => ['required', 'string', 'max:255', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => Str::of((string) $this->input('nik'))->replaceMatches('/\D+/', '')->toString(),
            'nip' => $this->nullableString('nip'),
            'nama' => $this->nullableString('nama'),
            'email' => $this->nullableString('email') !== null
                ? Str::of((string) $this->input('email'))->trim()->lower()->toString()
                : null,
            'account_type' => $this->nullableString('account_type') ?? User::ACCOUNT_TYPE_PERSONAL,
            'status' => $this->nullableString('status') ?? User::STATUS_ACTIVE,
            'status_reason' => $this->nullableString('status_reason'),
            'tahun_aktif' => $this->nullableInteger('tahun_aktif'),
        ]);
    }

    private function nullableString(string $key): ?string
    {
        $value = Str::of((string) $this->input($key))->trim()->toString();

        return $value === '' ? null : $value;
    }

    /**
     * @return list<string>
     */
    private function allowedAccountTypes(): array
    {
        return [
            User::ACCOUNT_TYPE_PERSONAL,
            User::ACCOUNT_TYPE_FUNCTIONAL,
            User::ACCOUNT_TYPE_SERVICE,
            User::ACCOUNT_TYPE_EMERGENCY,
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedStatuses(): array
    {
        return [
            User::STATUS_PENDING,
            User::STATUS_ACTIVE,
            User::STATUS_INACTIVE,
            User::STATUS_SUSPENDED,
        ];
    }

    private function requiresStatusReason(): bool
    {
        return $this->input('status') !== User::STATUS_ACTIVE;
    }

    private function nullableInteger(string $key): ?int
    {
        if (! $this->filled($key)) {
            return null;
        }

        return (int) $this->input($key);
    }
}
