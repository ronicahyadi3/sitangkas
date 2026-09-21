<?php

namespace App\Http\Requests\Esign;

use Illuminate\Foundation\Http\FormRequest;

class StoreSigningSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'step_public_id' => ['required', 'uuid'],
        ];
    }
}
