<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Lunaweb\RecaptchaV3\Facades\RecaptchaV3;

class StoreAuthenticatedSessionRequest extends FormRequest
{
    private const CAPTCHA_ACTION = 'login';

    private const CAPTCHA_MINIMUM_SCORE = '0.5';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nik' => ['required', 'string', 'max:255'],
            'login_identifier' => ['required', 'string', 'max:255'],
            'login_identifier_type' => ['required', 'string', 'in:nik,email'],
            'password' => ['required', 'string', 'max:255'],
            'tahun' => ['required', 'integer', 'between:'.$this->minimumSelectableYear().','.$this->maximumSelectableYear()],
            'remember' => ['sometimes', 'boolean'],
            'client_timezone' => ['nullable', 'string', 'max:100'],
            'g-recaptcha-response' => $this->captchaRules(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function captchaRules(): array
    {
        if (! $this->captchaIsConfigured()) {
            return ['nullable', 'string'];
        }

        return [
            'required',
            'string',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->identifierType() === 'email' && ! filter_var($this->identifier(), FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('nik', 'NIK atau email tidak valid.');
                }

                if ($this->identifierType() === 'nik' && ! preg_match('/^\d{16}$/', $this->identifier())) {
                    $validator->errors()->add('nik', 'NIK harus terdiri dari 16 digit angka.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'g-recaptcha-response.required' => 'Verifikasi CAPTCHA wajib dilakukan.',
            'g-recaptcha-response.recaptchav3' => 'Verifikasi CAPTCHA tidak berhasil. Silakan coba lagi.',
            'nik.required' => 'NIK atau email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'tahun.between' => 'Tahun anggaran tidak tersedia untuk proses login.',
            'tahun.required' => 'Tahun anggaran wajib dipilih.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'g-recaptcha-response' => 'CAPTCHA',
            'nik' => 'NIK atau email',
            'password' => 'password',
            'tahun' => 'tahun anggaran',
            'client_timezone' => 'timezone perangkat',
        ];
    }

    public function identifier(): string
    {
        return (string) $this->input('login_identifier');
    }

    public function identifierType(): string
    {
        return (string) $this->input('login_identifier_type');
    }

    public function selectedYear(): int
    {
        return $this->integer('tahun');
    }

    public function remember(): bool
    {
        return $this->boolean('remember');
    }

    public function captchaToken(): ?string
    {
        $token = $this->string('g-recaptcha-response')->trim()->toString();

        return $token === '' ? null : $token;
    }

    public function clientTimezone(): ?string
    {
        $timezone = $this->string('client_timezone')->trim()->toString();

        return $timezone === '' ? null : $timezone;
    }

    public function captchaAction(): string
    {
        return self::CAPTCHA_ACTION;
    }

    public function captchaMinimumScore(): float
    {
        return (float) self::CAPTCHA_MINIMUM_SCORE;
    }

    public function throttleKey(): string
    {
        return 'login:'.hash('sha256', implode('|', [
            $this->identifierType(),
            $this->identifier(),
            $this->ip(),
        ]));
    }

    public function ipThrottleKey(): string
    {
        return 'login-ip:'.hash('sha256', (string) $this->ip());
    }

    public function captchaIsConfigured(): bool
    {
        return class_exists(RecaptchaV3::class)
            && filled(config('recaptchav3.sitekey'))
            && filled(config('recaptchav3.secret'));
    }

    protected function prepareForValidation(): void
    {
        $rawIdentifier = $this->string('nik')->trim()->toString();
        $identifierType = Str::contains($rawIdentifier, '@') ? 'email' : 'nik';

        $normalizedIdentifier = match ($identifierType) {
            'email' => Str::of($rawIdentifier)->lower()->toString(),
            default => Str::of($rawIdentifier)->replaceMatches('/\D+/', '')->toString(),
        };

        $this->merge([
            'nik' => $rawIdentifier,
            'login_identifier' => $normalizedIdentifier,
            'login_identifier_type' => $identifierType,
            'remember' => $this->boolean('remember'),
            'client_timezone' => $this->clientTimezone(),
        ]);
    }

    private function minimumSelectableYear(): int
    {
        return ((int) now()->year) - 5;
    }

    private function maximumSelectableYear(): int
    {
        return ((int) now()->year) + 1;
    }
}
