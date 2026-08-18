<?php

namespace App\Http\Requests\User;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use App\Models\UserPositionDocument;
use App\Services\User\PositionScopeOptionsService;
use App\Support\EncryptedId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserPositionStoreRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private array $invalidEncryptedFields = [];

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
            'jabatan_id' => [
                'required',
                'integer',
                Rule::exists((new Jabatan)->getTable(), 'id')->whereNull('deleted_at'),
            ],
            'instansi_id' => [
                'required',
                'integer',
                Rule::exists((new Instansi)->getTable(), 'id')->whereNull('deleted_at'),
            ],
            'unit_kerja_id' => [
                'required',
                'integer',
                Rule::exists((new UnitKerja)->getTable(), 'id')->whereNull('deleted_at'),
            ],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'file_sk' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:2048'],
            'document_type' => ['nullable', 'string', Rule::in(array_keys(UserPositionDocument::typeOptions()))],
            'document_number' => ['nullable', 'string', 'max:150'],
            'document_date' => ['nullable', 'date'],
            'issued_by' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->invalidEncryptedFields as $field) {
                    $validator->errors()->add($field, $this->invalidEncryptedIdMessage($field));
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $jabatanId = $this->integer('jabatan_id');
                $instansiId = $this->integer('instansi_id');
                $unitKerjaId = $this->integer('unit_kerja_id');

                $unitBelongsToInstansi = UnitKerja::query()
                    ->active()
                    ->effective()
                    ->whereKey($unitKerjaId)
                    ->where('instansi_id', $instansiId)
                    ->exists();

                if (! $unitBelongsToInstansi) {
                    $validator->errors()->add('unit_kerja_id', 'Unit kerja tidak sesuai dengan instansi yang dipilih.');

                    return;
                }

                if (! app(PositionScopeOptionsService::class)->isUnitAllowedForRoleAndInstansi($jabatanId, $instansiId, $unitKerjaId)) {
                    $validator->errors()->add('unit_kerja_id', 'Unit kerja tidak diperbolehkan untuk jabatan dan instansi yang dipilih.');

                    return;
                }

                $this->validateDocumentMetadata($validator);
                $this->validateDuplicatePosition($validator, $jabatanId, $instansiId, $unitKerjaId);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $decoded = [];

        foreach (['jabatan_id', 'instansi_id', 'unit_kerja_id'] as $field) {
            $decoded[$field] = $this->decodedInput($field);
        }

        $this->merge([
            ...$decoded,
            'document_type' => $this->nullableString('document_type') ?? UserPositionDocument::TYPE_APPOINTMENT_SK,
            'document_number' => $this->nullableString('document_number'),
            'issued_by' => $this->nullableString('issued_by'),
            'notes' => $this->nullableString('notes'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    private function nullableString(string $key): ?string
    {
        $value = Str::of((string) $this->input($key))->trim()->toString();

        return $value === '' ? null : $value;
    }

    private function decodedInput(string $field): ?int
    {
        $rawValue = $this->input($field);

        if (! filled($rawValue)) {
            return null;
        }

        $decoded = EncryptedId::tryDecode($rawValue);

        if ($decoded === null) {
            $this->invalidEncryptedFields[] = $field;
        }

        return $decoded;
    }

    private function validateDuplicatePosition(
        Validator $validator,
        int $jabatanId,
        int $instansiId,
        int $unitKerjaId
    ): void {
        $user = $this->routeUser();

        if (! $user instanceof User) {
            return;
        }

        $position = $this->routePosition();

        $duplicateExists = UserPosition::withTrashed()
            ->where('user_id', $user->id)
            ->where('jabatan_id', $jabatanId)
            ->where('instansi_id', $instansiId)
            ->where('unit_kerja_id', $unitKerjaId)
            ->when($position instanceof UserPosition, function ($query) use ($position): void {
                $query->whereKeyNot($position->getKey());
            })
            ->exists();

        if ($duplicateExists) {
            $validator->errors()->add('jabatan_id', 'Kombinasi user, jabatan, instansi, dan unit kerja sudah pernah dibuat.');
        }
    }

    private function validateDocumentMetadata(Validator $validator): void
    {
        if ($this->hasFile('file_sk')) {
            return;
        }

        $hasDocumentMetadata = $this->input('document_type') !== UserPositionDocument::TYPE_APPOINTMENT_SK
            || $this->filled('document_number')
            || $this->filled('document_date')
            || $this->filled('issued_by');

        if (! $hasDocumentMetadata) {
            return;
        }

        $position = $this->routePosition();
        $hasExistingDocument = $position instanceof UserPosition
            && $position->documents()->primary()->exists();

        if (! $hasExistingDocument) {
            $validator->errors()->add('file_sk', 'Upload file SK terlebih dahulu untuk menyimpan metadata dokumen.');
        }
    }

    private function routeUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    private function routePosition(): ?UserPosition
    {
        $position = $this->route('position');

        return $position instanceof UserPosition ? $position : null;
    }

    private function invalidEncryptedIdMessage(string $field): string
    {
        return match ($field) {
            'jabatan_id' => 'Jabatan tidak valid.',
            'instansi_id' => 'Instansi tidak valid.',
            'unit_kerja_id' => 'Unit kerja tidak valid.',
            default => 'Pilihan tidak valid.',
        };
    }
}
