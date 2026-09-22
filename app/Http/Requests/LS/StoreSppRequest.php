<?php

declare(strict_types=1);

namespace App\Http\Requests\LS;

use App\Models\AnggaranKegiatan;
use App\Models\AnggaranKegiatanTemp;
use App\Models\UnitKerja;
use App\Models\UserPosition;
use App\Services\User\ActivePositionService;
use App\Services\User\YearAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSppRequest extends FormRequest
{
    private const ALLOWED_EXPENDITURE_TYPES = [
        '1',
        '2',
        '3',
        '1,2',
        '1,3',
        '2,3',
        '1,2,3',
    ];

    private const MAX_PDF_SIZE_KILOBYTES = 5120;

    private const SETDA_SOURCE_UNIT_CODE = 'SKPD_SETDA';

    private const SETDA_TARGET_UNIT_CODE = 'SETDA_BAG_UMUM';

    private ?UserPosition $activeUserPosition = null;

    public function authorize(
        ActivePositionService $activePosition,
        YearAccessService $yearAccess,
    ): bool {
        $position = $activePosition->get();

        if (! $position instanceof UserPosition) {
            return false;
        }

        $position->loadMissing(['jabatan', 'unitKerja']);

        if (! $position->jabatan || ! $position->unitKerja) {
            return false;
        }

        if (! in_array((string) $position->jabatan->kode, ['BP', 'BPP'], true)) {
            return false;
        }

        if (
            ! $yearAccess->canWrite($position)
            || $yearAccess->selectedYear() > $yearAccess->currentYear()
        ) {
            return false;
        }

        $this->activeUserPosition = $position;

        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'nomor_spp' => ['bail', 'required', 'string', 'max:255'],
            'uraian' => ['bail', 'required', 'string', 'max:255'],
            'nominal' => ['bail', 'required', 'numeric', 'decimal:0,2', 'gt:0'],
            'belanja' => ['bail', 'required', 'string', Rule::in(self::ALLOWED_EXPENDITURE_TYPES)],
            'sub_kegiatan_id' => ['bail', 'required', 'string', 'max:50'],
            'file_spp' => ['bail', 'required', 'file', 'mimes:pdf', 'max:'.self::MAX_PDF_SIZE_KILOBYTES],
            'file_spj' => ['bail', 'required', 'file', 'mimes:pdf', 'max:'.self::MAX_PDF_SIZE_KILOBYTES],
            'file_billing' => ['bail', 'nullable', 'file', 'mimes:pdf', 'max:'.self::MAX_PDF_SIZE_KILOBYTES],
            'file_bmd' => [
                'bail',
                Rule::requiredIf(fn (): bool => $this->requiresBmdFile()),
                'nullable',
                'file',
                'mimes:pdf',
                'max:'.self::MAX_PDF_SIZE_KILOBYTES,
            ],
            'rekening' => ['bail', 'required', 'array', 'min:1', 'max:100'],
            'rekening.*.id' => ['bail', 'required', 'string', 'max:255'],
            'rekening.*.uraian' => ['nullable', 'string', 'max:255'],
            'rekening.*.rekening' => ['nullable', 'string', 'max:255'],
            'rekening.*.nominal' => ['bail', 'required', 'numeric', 'decimal:0,2', 'gt:0'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(YearAccessService $yearAccess): array
    {
        return [
            function (Validator $validator) use ($yearAccess): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $position = $this->activeUserPosition;

                if (! $position instanceof UserPosition || ! $position->unitKerja) {
                    $validator->errors()->add('rekening', 'Posisi aktif atau unit kerja tidak valid.');

                    return;
                }

                $rekeningInput = collect($this->input('rekening', []));
                $rekeningIds = $rekeningInput
                    ->pluck('id')
                    ->map(static fn (mixed $id): string => trim((string) $id));

                if ($rekeningIds->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('rekening', 'Rekening yang sama tidak boleh dipilih lebih dari satu kali.');

                    return;
                }

                $targetUnitId = $this->resolveBudgetUnitId((int) $position->unitKerja->id);
                $selectedYear = $yearAccess->selectedYear();
                $budgetRows = AnggaranKegiatanTemp::query()
                    ->forYear($selectedYear)
                    ->forUnit($targetUnitId)
                    ->where('kode_sub_kegiatan', $this->string('sub_kegiatan_id')->toString())
                    ->whereIn('id_rekening', $rekeningIds->all())
                    ->get()
                    ->keyBy(static fn (AnggaranKegiatanTemp $row): string => (string) $row->id_rekening);

                $this->validateSelectedAccounts($validator, $rekeningInput, $budgetRows);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateNominalTotal($validator, $rekeningInput);
                $this->validateAvailableBudgets(
                    $validator,
                    $rekeningInput,
                    $budgetRows,
                    $selectedYear,
                    $targetUnitId,
                );
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nomor_spp.required' => 'Nomor SPP wajib diisi.',
            'nomor_spp.max' => 'Nomor SPP maksimal 255 karakter.',
            'uraian.required' => 'Uraian pencairan wajib diisi.',
            'uraian.max' => 'Uraian pencairan maksimal 255 karakter.',
            'nominal.required' => 'Total nominal wajib diisi.',
            'nominal.numeric' => 'Total nominal harus berupa angka.',
            'nominal.decimal' => 'Total nominal maksimal menggunakan dua angka desimal.',
            'nominal.gt' => 'Total nominal harus lebih besar dari nol.',
            'belanja.required' => 'Jenis belanja wajib dipilih.',
            'belanja.in' => 'Jenis belanja tidak valid.',
            'sub_kegiatan_id.required' => 'Sub kegiatan wajib dipilih.',
            'file_spp.required' => 'File SPP wajib diunggah.',
            'file_spj.required' => 'File SPJ wajib diunggah.',
            'file_bmd.required' => 'File BMD wajib diunggah untuk Belanja Modal atau Belanja Persediaan.',
            'file_spp.file' => 'File SPP yang diunggah tidak valid.',
            'file_spp.mimes' => 'File SPP harus berformat PDF.',
            'file_spp.max' => 'Ukuran file SPP maksimal 5 MB.',
            'file_spj.file' => 'File SPJ yang diunggah tidak valid.',
            'file_spj.mimes' => 'File SPJ harus berformat PDF.',
            'file_spj.max' => 'Ukuran file SPJ maksimal 5 MB.',
            'file_billing.file' => 'File Billing yang diunggah tidak valid.',
            'file_billing.mimes' => 'File Billing harus berformat PDF.',
            'file_billing.max' => 'Ukuran file Billing maksimal 5 MB.',
            'file_bmd.file' => 'File BMD yang diunggah tidak valid.',
            'file_bmd.mimes' => 'File BMD harus berformat PDF.',
            'file_bmd.max' => 'Ukuran file BMD maksimal 5 MB.',
            'rekening.required' => 'Minimal satu rekening wajib dipilih.',
            'rekening.array' => 'Data rekening tidak valid.',
            'rekening.min' => 'Minimal satu rekening wajib dipilih.',
            'rekening.max' => 'Maksimal 100 rekening dapat dipilih.',
            'rekening.*.id.required' => 'Rekening wajib dipilih.',
            'rekening.*.nominal.required' => 'Nominal rekening wajib diisi.',
            'rekening.*.nominal.numeric' => 'Nominal rekening harus berupa angka.',
            'rekening.*.nominal.decimal' => 'Nominal rekening maksimal menggunakan dua angka desimal.',
            'rekening.*.nominal.gt' => 'Nominal rekening harus lebih besar dari nol.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nomor_spp' => $this->nullableTrimmedString('nomor_spp'),
            'uraian' => $this->nullableTrimmedString('uraian'),
            'belanja' => $this->normalizedExpenditureType(),
            'sub_kegiatan_id' => $this->nullableTrimmedString('sub_kegiatan_id'),
        ]);

        foreach (['file_billing', 'file_bmd'] as $field) {
            if ($this->hasFile($field)) {
                continue;
            }

            $value = Str::lower(trim((string) $this->input($field)));

            if (in_array($value, ['', 'null', 'undefined'], true)) {
                $this->merge([$field => null]);
            }
        }
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException(
            'Anda tidak berwenang mengunggah SPP LS pada tahun anggaran ini.',
        );
    }

    private function requiresBmdFile(): bool
    {
        $types = explode(',', (string) $this->input('belanja'));

        return in_array('1', $types, true) || in_array('2', $types, true);
    }

    private function normalizedExpenditureType(): ?string
    {
        $value = $this->input('belanja');
        $types = is_array($value) ? $value : explode(',', (string) $value);
        $types = array_values(array_unique(array_filter(
            array_map(static fn (mixed $type): string => trim((string) $type), $types),
            static fn (string $type): bool => $type !== '',
        )));
        sort($types, SORT_NUMERIC);

        return $types === [] ? null : implode(',', $types);
    }

    private function nullableTrimmedString(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }

    private function resolveBudgetUnitId(int $activeUnitId): int
    {
        $activeUnitCode = $this->activeUserPosition?->unitKerja?->kode;

        if ($activeUnitCode !== self::SETDA_SOURCE_UNIT_CODE) {
            return $activeUnitId;
        }

        return (int) (UnitKerja::query()
            ->where('kode', self::SETDA_TARGET_UNIT_CODE)
            ->value('id') ?? $activeUnitId);
    }

    /**
     * @param  Collection<int, mixed>  $rekeningInput
     * @param  Collection<string, AnggaranKegiatanTemp>  $budgetRows
     */
    private function validateSelectedAccounts(
        Validator $validator,
        Collection $rekeningInput,
        Collection $budgetRows,
    ): void {
        foreach ($rekeningInput as $index => $item) {
            $accountId = trim((string) ($item['id'] ?? ''));

            if (! $budgetRows->has($accountId)) {
                $validator->errors()->add(
                    "rekening.{$index}.id",
                    'Rekening tidak tersedia untuk sub kegiatan, unit kerja, dan tahun anggaran aktif.',
                );
            }
        }
    }

    /** @param Collection<int, mixed> $rekeningInput */
    private function validateNominalTotal(Validator $validator, Collection $rekeningInput): void
    {
        $rekeningTotal = $rekeningInput->sum(
            static fn (mixed $item): int => self::decimalToCents($item['nominal'] ?? 0),
        );
        $submittedTotal = self::decimalToCents($this->input('nominal'));

        if ($rekeningTotal !== $submittedTotal) {
            $validator->errors()->add(
                'nominal',
                'Total nominal harus sama dengan jumlah seluruh nominal rekening.',
            );
        }
    }

    /**
     * @param  Collection<int, mixed>  $rekeningInput
     * @param  Collection<string, AnggaranKegiatanTemp>  $budgetRows
     */
    private function validateAvailableBudgets(
        Validator $validator,
        Collection $rekeningInput,
        Collection $budgetRows,
        int $selectedYear,
        int $targetUnitId,
    ): void {
        $accountIds = $budgetRows->keys()->all();
        $realizedAmounts = AnggaranKegiatan::query()
            ->forYear($selectedYear)
            ->forUnit($targetUnitId)
            ->whereIn('id_rekening', $accountIds)
            ->selectRaw('id_rekening, SUM(nominal) as total_nominal')
            ->groupBy('id_rekening')
            ->pluck('total_nominal', 'id_rekening');

        foreach ($rekeningInput as $index => $item) {
            $accountId = trim((string) ($item['id'] ?? ''));
            $budget = $budgetRows->get($accountId);

            if (! $budget instanceof AnggaranKegiatanTemp) {
                continue;
            }

            $remainingBudget = self::decimalToCents($budget->pagu)
                - self::decimalToCents($realizedAmounts->get($accountId, 0));
            $requestedAmount = self::decimalToCents($item['nominal'] ?? 0);

            if ($requestedAmount > $remainingBudget) {
                $validator->errors()->add(
                    "rekening.{$index}.nominal",
                    "Nominal rekening {$budget->kode_rekening} melebihi sisa pagu yang tersedia.",
                );
            }
        }
    }

    private static function decimalToCents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }
}
