<?php

namespace App\Http\Requests\User;

use App\Models\UserPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UserPositionDeactivateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ended_at' => ['nullable', 'date', 'before_or_equal:today'],
            'deactivation_reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('ended_at')) {
                    return;
                }

                $position = $this->route('position');

                if (! $position instanceof UserPosition || $position->started_at === null) {
                    return;
                }

                if (Carbon::parse($this->input('ended_at'))->lt($position->started_at)) {
                    $validator->errors()->add('ended_at', 'Tanggal selesai tidak boleh sebelum tanggal mulai posisi.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ended_at' => $this->nullableString('ended_at'),
            'deactivation_reason' => $this->nullableString('deactivation_reason'),
        ]);
    }

    private function nullableString(string $key): ?string
    {
        $value = Str::of((string) $this->input($key))->trim()->toString();

        return $value === '' ? null : $value;
    }
}
