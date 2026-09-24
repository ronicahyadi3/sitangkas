<?php

namespace App\Http\Requests\Esign;

use Illuminate\Foundation\Http\FormRequest;

class ResumeEsignAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'affirmed' => ['required', 'accepted'],
            'passphrase' => ['required', 'string', 'max:255'],
        ];
    }

    public function passphrase(): string
    {
        return (string) $this->validated('passphrase');
    }
}
