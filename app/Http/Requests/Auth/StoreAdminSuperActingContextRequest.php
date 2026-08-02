<?php

namespace App\Http\Requests\Auth;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\AdminSuperPositionScope;
use App\Services\Auth\CurrentUserContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class StoreAdminSuperActingContextRequest extends FormRequest
{
    private ?UserPosition $realActiveUserPosition = null;

    private ?Jabatan $selectedJabatan = null;

    private ?Instansi $selectedInstansi = null;

    private ?UnitKerja $selectedUnitKerja = null;

    private ?UserPosition $selectedPptkUserPosition = null;

    private ?UserPosition $selectedBudUserPosition = null;

    public function authorize(CurrentUserContext $currentUserContext, AdminSuperPositionScope $positionScope): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $activeUserPosition = $currentUserContext->realActivePosition($this);

        if (! $activeUserPosition instanceof UserPosition) {
            return false;
        }

        $activeUserPosition->loadMissing('jabatan');

        if (! $activeUserPosition->jabatan instanceof Jabatan) {
            return false;
        }

        if (! $positionScope->isAdminSuperJabatan($activeUserPosition->jabatan)) {
            return false;
        }

        $this->realActiveUserPosition = $activeUserPosition;

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jabatan_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists(Jabatan::class, 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'instansi_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists(Instansi::class, 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'unit_kerja_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists(UnitKerja::class, 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'pptk_user_position_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists(UserPosition::class, 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'bud_user_position_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists(UserPosition::class, 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(AdminSuperPositionScope $positionScope): array
    {
        return [
            function (Validator $validator) use ($positionScope): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->hydrateSelectedModels();

                if (
                    ! $this->selectedJabatan instanceof Jabatan
                    || ! $this->selectedInstansi instanceof Instansi
                    || ! $this->selectedUnitKerja instanceof UnitKerja
                ) {
                    $this->addMissingModelErrors($validator);

                    return;
                }

                $errors = $positionScope->validateCombination(
                    $this->selectedJabatan,
                    $this->selectedInstansi,
                    $this->selectedUnitKerja,
                    $this->selectedPptkUserPosition,
                    $this->selectedBudUserPosition,
                );

                foreach ($errors as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
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
            'bud_user_position_id.exists' => 'UserPosition BUD tidak ditemukan.',
            'bud_user_position_id.integer' => 'UserPosition BUD tidak valid.',
            'instansi_id.exists' => 'Instansi tidak ditemukan.',
            'instansi_id.integer' => 'Instansi tidak valid.',
            'instansi_id.required' => 'Instansi wajib dipilih.',
            'jabatan_id.exists' => 'Jabatan tidak ditemukan.',
            'jabatan_id.integer' => 'Jabatan tidak valid.',
            'jabatan_id.required' => 'Jabatan wajib dipilih.',
            'pptk_user_position_id.exists' => 'UserPosition PPTK tidak ditemukan.',
            'pptk_user_position_id.integer' => 'UserPosition PPTK tidak valid.',
            'unit_kerja_id.exists' => 'Unit kerja tidak ditemukan.',
            'unit_kerja_id.integer' => 'Unit kerja tidak valid.',
            'unit_kerja_id.required' => 'Unit kerja wajib dipilih.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'bud_user_position_id' => 'UserPosition BUD',
            'instansi_id' => 'instansi',
            'jabatan_id' => 'jabatan',
            'pptk_user_position_id' => 'UserPosition PPTK',
            'unit_kerja_id' => 'unit kerja',
        ];
    }

    public function realActiveUserPosition(): UserPosition
    {
        if (! $this->realActiveUserPosition instanceof UserPosition) {
            throw new LogicException('Real active user position is only available after authorization passes.');
        }

        return $this->realActiveUserPosition;
    }

    public function selectedJabatan(): Jabatan
    {
        if (! $this->selectedJabatan instanceof Jabatan) {
            throw new LogicException('Selected jabatan is only available after validation passes.');
        }

        return $this->selectedJabatan;
    }

    public function selectedInstansi(): Instansi
    {
        if (! $this->selectedInstansi instanceof Instansi) {
            throw new LogicException('Selected instansi is only available after validation passes.');
        }

        return $this->selectedInstansi;
    }

    public function selectedUnitKerja(): UnitKerja
    {
        if (! $this->selectedUnitKerja instanceof UnitKerja) {
            throw new LogicException('Selected unit kerja is only available after validation passes.');
        }

        return $this->selectedUnitKerja;
    }

    public function selectedPptkUserPosition(): ?UserPosition
    {
        return $this->selectedPptkUserPosition;
    }

    public function selectedBudUserPosition(): ?UserPosition
    {
        return $this->selectedBudUserPosition;
    }

    /**
     * @return array<string, mixed>
     */
    public function actingContextData(): array
    {
        return [
            'jabatan_id' => $this->selectedJabatan()->getKey(),
            'jabatan_name' => $this->selectedJabatan()->nama,
            'instansi_id' => $this->selectedInstansi()->getKey(),
            'unit_kerja_id' => $this->selectedUnitKerja()->getKey(),
            'pptk_user_position_id' => $this->selectedPptkUserPosition()?->getKey(),
            'bud_user_position_id' => $this->selectedBudUserPosition()?->getKey(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function actingSessionData(): array
    {
        $contextData = $this->actingContextData();

        return [
            $this->sessionKey('jabatan_id', 'acting_jabatan_id') => $contextData['jabatan_id'],
            $this->sessionKey('jabatan_name', 'acting_jabatan_name') => $contextData['jabatan_name'],
            $this->sessionKey('instansi_id', 'acting_instansi_id') => $contextData['instansi_id'],
            $this->sessionKey('unit_kerja_id', 'acting_unit_kerja_id') => $contextData['unit_kerja_id'],
            $this->sessionKey('pptk_user_position_id', 'acting_pptk_user_position_id') => $contextData['pptk_user_position_id'],
            $this->sessionKey('bud_user_position_id', 'acting_bud_user_position_id') => $contextData['bud_user_position_id'],
            $this->sessionKey('selected_at', 'acting_selected_at') => now()->toDateTimeString(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bud_user_position_id' => $this->normalizeIntegerInput('bud_user_position_id'),
            'instansi_id' => $this->normalizeIntegerInput('instansi_id'),
            'jabatan_id' => $this->normalizeIntegerInput('jabatan_id'),
            'pptk_user_position_id' => $this->normalizeIntegerInput('pptk_user_position_id'),
            'unit_kerja_id' => $this->normalizeIntegerInput('unit_kerja_id'),
        ]);
    }

    private function hydrateSelectedModels(): void
    {
        $this->selectedJabatan = Jabatan::query()
            ->whereKey($this->integer('jabatan_id'))
            ->first();

        $this->selectedInstansi = Instansi::query()
            ->whereKey($this->integer('instansi_id'))
            ->first();

        $this->selectedUnitKerja = UnitKerja::query()
            ->whereKey($this->integer('unit_kerja_id'))
            ->first();

        if ($this->filled('pptk_user_position_id')) {
            $this->selectedPptkUserPosition = UserPosition::query()
                ->with(['user', 'jabatan', 'instansi', 'unitKerja'])
                ->whereKey($this->integer('pptk_user_position_id'))
                ->first();
        }

        if ($this->filled('bud_user_position_id')) {
            $this->selectedBudUserPosition = UserPosition::query()
                ->with(['user', 'jabatan', 'instansi', 'unitKerja'])
                ->whereKey($this->integer('bud_user_position_id'))
                ->first();
        }
    }

    private function addMissingModelErrors(Validator $validator): void
    {
        if (! $this->selectedJabatan instanceof Jabatan) {
            $validator->errors()->add('jabatan_id', 'Jabatan tidak ditemukan.');
        }

        if (! $this->selectedInstansi instanceof Instansi) {
            $validator->errors()->add('instansi_id', 'Instansi tidak ditemukan.');
        }

        if (! $this->selectedUnitKerja instanceof UnitKerja) {
            $validator->errors()->add('unit_kerja_id', 'Unit kerja tidak ditemukan.');
        }
    }

    private function normalizeIntegerInput(string $key): int|string|null
    {
        $value = $this->input($key);

        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $value = Str::of((string) $value)->trim()->toString();

        if ($value === '') {
            return null;
        }

        return ctype_digit($value) ? (int) $value : $value;
    }

    private function sessionKey(string $key, string $fallback): string
    {
        $sessionKey = config("position_rules.manual_context.session_keys.{$key}", $fallback);

        return is_string($sessionKey) && $sessionKey !== '' ? $sessionKey : $fallback;
    }
}
