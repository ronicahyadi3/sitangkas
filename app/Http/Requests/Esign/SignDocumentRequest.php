<?php

namespace App\Http\Requests\Esign;

use Illuminate\Foundation\Http\FormRequest;

class SignDocumentRequest extends FormRequest
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
            'idempotency_key' => ['required', 'uuid'],
            'passphrase' => ['required', 'string', 'max:255'],
            'preview_sha256' => ['required', 'string', 'size:64', 'regex:/\A[a-fA-F0-9]{64}\z/'],
        ];
    }

    public function passphrase(): string
    {
        return (string) $this->validated('passphrase');
    }
}
