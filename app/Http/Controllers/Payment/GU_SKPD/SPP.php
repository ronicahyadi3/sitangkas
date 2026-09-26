<?php

namespace App\Http\Controllers\Payment\GU_SKPD;

use App\Http\Controllers\Controller;
use App\Models\AnggaranKegiatan;
use App\Models\AnggaranKegiatanTemp;
use App\Models\Document;
use App\Models\Payment\GU_SKPD as PaymentGU_SKPD;
use App\Models\UnitKerja;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SPP extends Controller
{
    private const PAYMENT_TYPE = 'GU_SKPD';

    protected function auditorStatusBadge(object $row): string
    {
        $badge = static function (string $class, string $icon, string $label): string {
            return sprintf(
                '<span class="btn btn-sm %s disabled" aria-disabled="true"><i class="%s"></i> %s</span>',
                $class,
                $icon,
                e($label)
            );
        };

        if (! is_null($row->rejected_by)) {
            $label = match ((int) $row->rejected_by) {
                5 => 'Ditolak PA',
                7 => 'Ditolak PPK',
                9 => 'Ditolak BP',
                default => 'Ditolak',
            };

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if (! is_null($row->verify)) {
            return $badge('btn-success', 'fas fa-user-check', 'Telah Verifikasi');
        }

        if (! empty($this->csvToArray($row->submit))) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if (! empty($this->csvToArray($row->status)) || ! empty($this->csvToArray($row->status_spp))) {
            return $badge('btn-success', 'fas fa-file-signature', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function index()
    {
        Log::channel('payment_gu_skpd')->debug('SPP GU_SKPD index accessed');

        return view('Payment.GU_SKPD.spp');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD json blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = $user->unitKerja?->id;
            $submitExpr = "REPLACE(COALESCE(lpj.submit,''), ' ', '')";

            Log::channel('payment_gu_skpd')->debug('SPP GU_SKPD json request');

            $query = PaymentGU_SKPD::rootQueryAlias('lpj')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'lpj.id_unit_kerja')
                ->leftJoin('document as spp', function ($join) {
                    $join->on('spp.reference_id', '=', 'lpj.id')
                        ->where('spp.src_type', 'SPP')
                        ->where('spp.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('spp.deleted_at');
                })
                ->leftJoinSub(
                    DB::table('document as tbp_idx')
                        ->selectRaw('tbp_idx.parent_id, MAX(tbp_idx.id) as tbp_id')
                        ->where('tbp_idx.src_type', 'TBP')
                        ->where('tbp_idx.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('tbp_idx.deleted_at')
                        ->groupBy('tbp_idx.parent_id'),
                    'tbp_last',
                    function ($join) {
                        $join->on('tbp_last.parent_id', '=', 'lpj.id');
                    }
                )
                ->leftJoin('document as tbp_show', function ($join) {
                    $join->on('tbp_show.id', '=', 'tbp_last.tbp_id');
                })
                ->where('lpj.src_type', 'LPJ')
                ->where('lpj.payment_type', self::PAYMENT_TYPE)
                ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($jabatanId, $unitKerjaId, $submitExpr) {
                    $q->where(function ($scope) use ($unitKerjaId) {
                        $scope->where('lpj.id_unit_kerja', $unitKerjaId)
                            ->orWhere('uk.skpd_id', $unitKerjaId);
                    });

                    if ($jabatanId === 5) {
                        $q->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['9']);
                    }

                    if ($jabatanId === 7) {
                        $q->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['5'])
                            ->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['9']);
                    }
                })
                ->select([
                    'lpj.id', 'lpj.nomor as nomor_lpj', 'lpj.src_name as src_name_lpj',
                    'lpj.status', 'lpj.submit', 'lpj.rejected_by', 'lpj.notes', 'lpj.verify',
                    'lpj.uraian', 'lpj.nominal', 'lpj.created_at', 'uk.nama as unit_kerja',
                    'spp.nomor as nomor_spp', 'spp.status as status_spp', 'spp.src_name as src_name_spp',
                    'tbp_show.src_name as src_name_tbp',
                    'tbp_show.status as status_tbp',
                ])
                ->orderByDesc('lpj.created_at');

            $btn = static function (string $value, string $class, string $icon, string $title, string $extra = ''): string {
                return sprintf('<button value="%s" class="btn p-2 m-1 %s" title="%s" %s><i class="%s fa-lg"></i></button>', $value, $class, $title, $extra, $icon);
            };

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_button', function ($row) use ($jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $tbpButton = ! empty($row->src_name_tbp)
                        ? '<button type="button" class="btn btn-sm btn-info detail_tbp ms-1"'
                            .' value="'.$enc.'"'
                            .' data-wenk="Tampilkan TBP"'
                            .' data-wenk-color="blue"'
                            .'>'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan TBP</button>'
                        : '<span class="btn btn-sm btn-secondary ms-1"><i class="fa-solid fa-eye-slash"></i> Tampilkan TBP</span>';

                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row).$tbpButton;
                    }

                    if (! is_null($row->rejected_by)) {
                        $label = match ((int) $row->rejected_by) {
                            5 => 'Ditolak PA',
                            7 => 'Ditolak PPK',
                            9 => 'Ditolak BP',
                            default => 'Ditolak',
                        };

                        return '<span type="button" class="btn btn-sm btn-danger show-document" data-id="'.$enc.'" data-wenk="'.e((string) $row->notes).'" data-wenk-color="red" data-toggle="modal" data-target="#FormTTE"><i class="far fa-file-excel"></i> '.$label.'</span>'.$tbpButton;
                    }
                    $fileUrl = ! is_null($row->status) ? '/File_LPJ/signs/'.$row->src_name_lpj : '/File_LPJ/'.$row->src_name_lpj;

                    if (! in_array($jabatanId, [9, 5, 7], true)) {
                        return '<span type="button" class="btn btn-sm btn-info show-document" data-id="'.$enc.'" data-wenk="Tampilkan Dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>'.$tbpButton;
                    }

                    $statusArr = $this->csvToArray($row->status);
                    $statusSppArr = $this->csvToArray($row->status_spp);
                    $submitArr = $this->csvToArray($row->submit);
                    $submitCount = array_count_values($submitArr);
                    $alreadySignedLpj = in_array((string) $jabatanId, $statusArr, true);
                    $alreadySignedSpp = in_array((string) $jabatanId, $statusSppArr, true);
                    $alreadySigned = $jabatanId === 5
                        ? $alreadySignedSpp
                        : ($alreadySignedLpj && $alreadySignedSpp);
                    $submittedByViewer = ($submitCount[(string) $jabatanId] ?? 0) > 0;
                    $canResubmit = match ($jabatanId) {
                        9 => $alreadySigned
                            && (
                                (($submitCount['9'] ?? 0) === 0)
                                || (($submitCount['9'] ?? 0) === 1 && ($submitCount['5'] ?? 0) >= 1 && ($submitCount['7'] ?? 0) === 0)
                            ),
                        5 => $alreadySigned
                            && (($submitCount['9'] ?? 0) >= 1)
                            && (($submitCount['5'] ?? 0) === 0),
                        default => false,
                    };

                    if ($jabatanId === 7 && ! is_null($row->verify)) {
                        return '<span type="button" class="btn btn-sm btn-success show-document" data-id="'.$enc.'" data-wenk="Telah Verifikasi" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-user-check"></i> Telah Verifikasi</span>'.$tbpButton;
                    }

                    if ($jabatanId === 7) {
                        return '<span type="button" class="btn btn-sm btn-info show-document" data-id="'.$enc.'" data-wenk="Tampilkan Dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>'.$tbpButton;
                    }

                    if ($submittedByViewer && ! $canResubmit) {
                        return '<span type="button" class="btn btn-sm btn-primary show-document" data-id="'.$enc.'" data-wenk="Telah Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-paper-plane"></i> Telah Submit</span>'.$tbpButton;
                    }

                    if ($canResubmit) {
                        return '<span type="button" class="btn btn-sm btn-secondary show-document" data-id="'.$enc.'" data-wenk="Belum Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-hourglass-half"></i> Belum Submit</span>'.$tbpButton;
                    }

                    if (! $alreadySigned) {
                        return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-id="'.$enc.'" data-wenk="Belum TTE" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum TTE</span>'.$tbpButton;
                    }

                    return '<span type="button" class="btn btn-sm btn-success show-document" data-status="1" data-id="'.$enc.'" data-wenk="Sudah TTE" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-contract"></i> Sudah TTE</span>'.$tbpButton;
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $actions = [];

                    if ($jabatanId === 13) {
                        $actions[] = $btn(
                            $enc,
                            'show-document',
                            'fa-solid fa-eye text-primary',
                            'Detail Dokumen',
                            'data-id="'.$enc.'"'
                        );
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (! in_array($jabatanId, [9, 5, 7], true)) {
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    $submitArr = $this->csvToArray($row->submit);
                    $submitCount = array_count_values($submitArr);
                    $statusArr = $this->csvToArray($row->status);
                    $statusSppArr = $this->csvToArray($row->status_spp);
                    $signedByBp = in_array('9', $statusArr, true) && in_array('9', $statusSppArr, true);
                    $signedByPa = in_array('5', $statusSppArr, true);
                    $bpSubmitCount = $submitCount['9'] ?? 0;
                    $paSubmitCount = $submitCount['5'] ?? 0;
                    $ppkSubmitCount = $submitCount['7'] ?? 0;
                    $isRejected = ! is_null($row->rejected_by);
                    $isVerified = ! is_null($row->verify);

                    if (
                        ! $isRejected &&
                        (
                            ($jabatanId === 9 && $signedByBp && $bpSubmitCount === 0)
                            || ($jabatanId === 5 && $signedByPa && $bpSubmitCount >= 1 && $paSubmitCount === 0)
                            || ($jabatanId === 9 && $signedByBp && $bpSubmitCount === 1 && $paSubmitCount >= 1 && $ppkSubmitCount === 0)
                        )
                    ) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if ($jabatanId === 9 && $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn($enc, 'delete', 'fas fa-trash text-danger', 'Hapus', 'data-payment="GU_SKPD" data-type="LPJ"');
                    }

                    if ($jabatanId === 5 && ! $isRejected && $bpSubmitCount >= 1 && $paSubmitCount === 0) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_SKPD" data-type="LPJ"'
                        );
                    }

                    if (
                        $jabatanId === 9 &&
                        ! $isRejected &&
                        $bpSubmitCount === 1 &&
                        $paSubmitCount >= 1 &&
                        $ppkSubmitCount === 0
                    ) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_SKPD" data-type="LPJ"'
                        );
                    }

                    if (
                        $jabatanId === 7 &&
                        ! $isRejected &&
                        ! $isVerified &&
                        $bpSubmitCount >= 2 &&
                        $paSubmitCount >= 1
                    ) {
                        $actions[] = $btn(
                            $enc,
                            'verify_data',
                            'fas fa-user-check text-success',
                            'Verifikasi',
                            'data-payment="GU_SKPD" data-type="LPJ"'
                        );
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_SKPD" data-type="LPJ"'
                        );
                    }

                    if ($jabatanId === 9 && $bpSubmitCount === 0 && ! $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn($enc, 'delete', 'fas fa-trash text-danger', 'Hapus', 'data-payment="GU_SKPD" data-type="LPJ"');
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel('payment_gu_skpd')->debug('SPP GU_SKPD json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_skpd')->error('SPP GU_SKPD json failed', [
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD formJson blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $unitKerja = $user->unitKerja?->id;
            $isEdited = $request->edited === 'true';
            $lpjId = null;
            if ($request->data) {
                try {
                    $lpjId = EncryptedId::decode($request->data);
                } catch (\Throwable) {
                    Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD formJson blocked: invalid data parameter', [
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
                }
            }

            Log::channel('payment_gu_skpd')->debug('SPP GU_SKPD formJson request', [
                'is_edited' => $isEdited,
                'lpj_id' => $lpjId,
            ]);

            $query = PaymentGU_SKPD::rootQueryAlias('tbp')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'tbp.id_unit_kerja')
                ->leftJoin('document as npd', function ($join) {
                    $join->on('npd.id', '=', 'tbp.reference_id')
                        ->where('npd.src_type', 'NPD')
                        ->where('npd.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('npd.deleted_at');
                })
                ->where('tbp.src_type', 'TBP')
                ->where('tbp.payment_type', self::PAYMENT_TYPE)
                ->where(function ($q) {
                    $q->whereNull('tbp.rejected_by')->orWhere('tbp.rejected_by', '');
                })
                ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(tbp.submit,''), ' ', ''))", ['5'])
                ->where(function ($q) use ($unitKerja) {
                    $q->where('tbp.id_unit_kerja', $unitKerja)
                        ->orWhere('uk.skpd_id', $unitKerja);
                })
                ->where(function ($q) use ($isEdited, $lpjId) {
                    $q->whereNull('tbp.parent_id');
                    if ($isEdited && $lpjId) {
                        $q->orWhere('tbp.parent_id', $lpjId);
                    }
                })
                ->select(['tbp.id', 'tbp.parent_id', 'tbp.nomor', 'tbp.src_name', 'tbp.status', 'uk.nama as unit_kerja', 'npd.nomor as nomor_npd', 'tbp.created_at'])
                ->orderByDesc('tbp.created_at');
            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($data) {
                    $viewerContractUrl = route('document.pdf.viewer', [
                        'document' => EncryptedId::encode((int) $data->id),
                        'resource' => 'document',
                    ]);

                    return '<button type="button" class="btn btn-sm btn-info"'
                        .' data-document-pdf-action="contract"'
                        .' data-pdf-viewer-contract-url="'.e($viewerContractUrl).'"'
                        .' data-wenk="Klik untuk menampilkan dokumen" data-wenk-color="blue">'
                        .'<i class="fa-solid fa-eye"></i> Tampilkan</button>';
                })
                ->addColumn('action', function ($data) use ($lpjId) {
                    $id = EncryptedId::encode($data->id);
                    $checked = ($lpjId && (int) $data->parent_id === (int) $lpjId) ? 'checked' : '';
                    $deniedButton = $checked ? '' : '<button
                        type="button"
                        class="btn btn-outline-danger denied"
                        value="'.$id.'"
                        data-wenk="Menolak data"
                        data-payment="GU_SKPD"
                        data-type="TBP"
                        data-wenk-color="red">
                        <i class="fa-solid fa-ban"></i>
                    </button>';

                    return '<div class="d-flex justify-content-center">
                    <div class="btn-group btn-group-sm">
                        <input type="checkbox"
                            class="btn-check"
                            name="selected_tbp[]"
                            id="tbp_'.$id.'"
                            value="'.$id.'"
                            '.$checked.'>

                        <label class="btn btn-outline-primary"
                            for="tbp_'.$id.'"
                            data-wenk="Pilih data"
                            data-wenk-color="green">
                            <i class="fa-solid fa-check"></i>
                        </label>
                        '.$deniedButton.'
                    </div>
                </div>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);

            Log::channel('payment_gu_skpd')->debug('SPP GU_SKPD formJson success', [
                'lpj_id' => $lpjId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_skpd')->error('SPP GU_SKPD formJson failed', [
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function store(Request $request, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $start = microtime(true);
        $storedFiles = [];

        try {
            $request->validate([
                'selected_tbp' => ['required', 'array', 'min:1'],
                'selected_tbp.*' => ['required', 'string'],
                'nomor_spp' => ['required', 'string', 'max:255'],
                'nomor_lpj' => ['required', 'string', 'max:255'],
                'uraian' => ['required', 'string'],
                'file_spp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
                'file_lpj' => ['required', 'file', 'mimes:pdf', 'max:5120'],
                'file_spj_fungsional' => ['required', 'file', 'mimes:pdf', 'max:5120'],
                'file_bmd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                'belanja' => ['nullable', 'string'],
                'rekening' => ['required', 'array', 'min:1'],
                'rekening.*.id' => ['required'],
                'rekening.*.nominal' => ['required'],
            ]);

            $tbpIds = $this->decodeEncryptedIds($request->selected_tbp ?? []);
            if ($tbpIds === null) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD store blocked: invalid TBP payload', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 400, 'message' => 'Data TBP tidak valid.'], 400);
            }

            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD store blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            if (! $this->canManageCrud($user)) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD store blocked: forbidden role', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat SPP.'], 403);
            }

            $actorId = (int) $user->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            $createdLpjId = null;

            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD store request', [
                'selected_tbp_count' => count($tbpIds),
            ]);

            if ($this->requiresBmdFile($request->input('belanja')) && ! $request->hasFile('file_bmd')) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD store blocked: missing required BMD', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 422,
                    'message' => 'File BMD wajib diunggah jika memilih Belanja Modal atau Persediaan.',
                ], 422);
            }

            DB::transaction(function () use ($request, $tbpIds, $actorId, $unitKerjaId, &$storedFiles, &$createdLpjId, $documentHistoryService) {
                $this->assertTbpAvailability($tbpIds, null, $unitKerjaId);
                $nominal = collect($request->rekening)->sum(fn ($r) => (float) ($r['nominal'] ?? 0));

                $lpjFile = $this->storeFile($request->file('file_lpj'), '/File_LPJ', $storedFiles);
                $sppFile = $this->storeFile($request->file('file_spp'), '/File_SPP', $storedFiles);
                $spjFungsionalFile = $this->storeFile($request->file('file_spj_fungsional'), '/File_spj_fungsional', $storedFiles);
                $bmdFile = $request->hasFile('file_bmd') ? $this->storeFile($request->file('file_bmd'), '/File_BMD', $storedFiles) : null;

                $lpj = Document::create([
                    'nomor' => $request->input('nomor_lpj'),
                    'src_name' => $lpjFile,
                    'src_type' => 'LPJ',
                    'payment_type' => self::PAYMENT_TYPE,
                    'spj_fungsional' => $spjFungsionalFile,
                    'uraian' => $request->input('uraian'),
                    'nominal' => $nominal,
                    'expenditure_type' => $request->input('belanja'),
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '9',
                    'created_at' => now(),
                ]);
                $createdLpjId = $lpj->id;

                $spp = Document::create([
                    'nomor' => $request->input('nomor_spp'),
                    'src_name' => $sppFile,
                    'src_type' => 'SPP',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $lpj->id,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '9',
                    'created_at' => now(),
                ]);

                if ($bmdFile) {
                    $bmd = Document::create([
                        'src_name' => $bmdFile,
                        'src_type' => 'BMD',
                        'payment_type' => self::PAYMENT_TYPE,
                        'reference_id' => $lpj->id,
                        'id_unit_kerja' => $unitKerjaId,
                        'uploaded_by' => $actorId,
                        'assigned_to' => '9',
                        'created_at' => now(),
                    ]);
                    $documentHistoryService->upload($bmd->id, $bmdFile, $unitKerjaId);
                }

                Document::whereIn('id', $tbpIds)->update(['parent_id' => $lpj->id, 'updated_at' => now()]);
                $this->syncRekening($request->rekening, $lpj->id, $unitKerjaId);

                $documentHistoryService->upload($lpj->id, $lpjFile, $unitKerjaId);
                $documentHistoryService->upload($spp->id, $sppFile, $unitKerjaId);
            });

            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD store success', [
                'lpj_id' => $createdLpjId,
                'selected_tbp_count' => count($tbpIds),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPP berhasil disimpan']);
        } catch (ValidationException $e) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD store blocked: validation failed', [
                'errors' => $e->errors(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD store blocked', [
                'message' => $e->getMessage(),
                'selected_tbp_count' => count($tbpIds),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->error('SPP GU_SKPD store failed', [
                'message' => $e->getMessage(),
                'selected_tbp_count' => count($tbpIds),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal menyimpan data SPP'], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD edit blocked: invalid parameter', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        Log::channel('payment_gu_skpd')->debug('SPP GU_SKPD edit request', [
            'lpj_id' => $id,
        ]);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD edit blocked: invalid active position', [
                'lpj_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $unitKerjaId = (int) $user->unitKerja->id;
        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD edit blocked: forbidden role', [
                'lpj_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SPP.'], 403);
        }

        $lpj = $this->findAccessibleLpjForCrud($id, $unitKerjaId);

        if (! $lpj) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD edit blocked: LPJ not found', [
                'lpj_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 404, 'message' => 'Data LPJ tidak ditemukan.'], 404);
        }

        if (! $this->isEditableForCrud($lpj)) {
            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD edit blocked: document not editable', [
                'lpj_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 409, 'message' => 'Data SPP tidak dapat diubah pada status saat ini.'], 409);
        }

        $spp = Document::where('src_type', 'SPP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $lpj->id)
            ->whereNull('deleted_at')
            ->first();

        $bmd = Document::where('src_type', 'BMD')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $lpj->id)
            ->whereNull('deleted_at')
            ->first();

        $selectedTbp = Document::where('src_type', 'TBP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('parent_id', $lpj->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($tbpId) => EncryptedId::encode((int) $tbpId))
            ->values();

        $rekening = AnggaranKegiatan::tahunAktif()
            ->select([
                'nama_sub_unit',
                'id_rekening as kode',
                'nama_rekening as uraian',
                'kode_rekening as rekening',
                'nominal as jumlah',
                'kode_sub_kegiatan as sub_kegiatan_id',
                'pagu',
                DB::raw('(
                    SELECT COALESCE(SUM(nominal), 0)
                    FROM anggaran_kegiatan ag
                    WHERE ag.id_rekening = anggaran_kegiatan.id_rekening
                      AND ag.id_spp != anggaran_kegiatan.id_spp
                      AND ag.deleted_at IS NULL
                ) as realisasi'),
            ])
            ->where('id_spp', $lpj->id)
            ->where('id_unit_kerja', $lpj->id_unit_kerja)
            ->get();

        Log::channel('payment_gu_skpd')->debug('SPP GU_SKPD edit success', [
            'lpj_id' => $id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return response()->json([
            'status' => 200,
            'data' => ['lpj' => $lpj, 'spp' => $spp, 'bmd' => $bmd, 'selected_tbp' => $selectedTbp],
            'rekening' => $rekening,
        ]);
    }

    public function update(Request $request, string $id, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $start = microtime(true);
        $storedFiles = [];
        $lpjId = null;
        $tbpIds = [];

        try {
            $request->validate([
                'selected_tbp' => ['required', 'array', 'min:1'],
                'selected_tbp.*' => ['required', 'string'],
                'nomor_spp' => ['required', 'string', 'max:255'],
                'nomor_lpj' => ['required', 'string', 'max:255'],
                'uraian' => ['required', 'string'],
                'file_spp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                'file_lpj' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                'file_spj_fungsional' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                'file_bmd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                'belanja' => ['nullable', 'string'],
                'rekening' => ['required', 'array', 'min:1'],
                'rekening.*.id' => ['required'],
                'rekening.*.nominal' => ['required'],
            ]);

            $tbpIds = $this->decodeEncryptedIds($request->selected_tbp ?? []);
            if ($tbpIds === null) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD update blocked: invalid TBP payload', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
            }

            try {
                $lpjId = EncryptedId::decode($id);
            } catch (\Throwable) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD update blocked: invalid parameter', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
            }

            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD update blocked: invalid active position', [
                    'lpj_id' => $lpjId,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            if (! $this->canManageCrud($user)) {
                Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD update blocked: forbidden role', [
                    'lpj_id' => $lpjId,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SPP.'], 403);
            }

            $actorId = (int) $user->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD update request', [
                'lpj_id' => $lpjId,
                'selected_tbp_count' => count($tbpIds),
            ]);

            DB::transaction(function () use ($request, $lpjId, $tbpIds, $actorId, $unitKerjaId, &$storedFiles, $documentHistoryService) {
                $lpj = Document::where('id', $lpjId)
                    ->where('src_type', 'LPJ')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $lpj || ! $this->isDocumentInUserScope((int) $lpj->id_unit_kerja, $unitKerjaId)) {
                    throw new \RuntimeException('Data LPJ tidak ditemukan.');
                }

                if (! $this->isEditableForCrud($lpj)) {
                    throw new \RuntimeException('Data SPP tidak dapat diubah pada status saat ini.');
                }

                $spp = Document::where('src_type', 'SPP')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('reference_id', $lpj->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spp) {
                    throw new \RuntimeException('Data SPP terkait tidak ditemukan.');
                }

                $existingBmd = Document::where('src_type', 'BMD')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('reference_id', $lpj->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                $this->assertTbpAvailability($tbpIds, $lpj->id, $unitKerjaId);
                $nominal = collect($request->rekening)->sum(fn ($r) => (float) ($r['nominal'] ?? 0));

                if ($this->isTransitioningToRequiredBmd($lpj->expenditure_type, $request->input('belanja')) && ! $request->hasFile('file_bmd')) {
                    throw new \RuntimeException('File BMD wajib diunggah karena belanja diubah ke Belanja Modal atau Persediaan.');
                }

                $lpjPayload = [
                    'nomor' => $request->input('nomor_lpj'),
                    'uraian' => $request->input('uraian'),
                    'nominal' => $nominal,
                    'expenditure_type' => $request->input('belanja'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'users_to' => null,
                    'assigned_to' => '9',
                    'updated_at' => now(),
                ];

                $sppPayload = [
                    'nomor' => $request->input('nomor_spp'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'users_to' => null,
                    'assigned_to' => '9',
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_lpj')) {
                    $lpjPayload['src_name'] = $this->storeFile($request->file('file_lpj'), '/File_LPJ', $storedFiles);
                    $lpjPayload['uploaded_by'] = $actorId;
                    $lpjPayload['status'] = null;
                }
                if ($request->hasFile('file_spj_fungsional')) {
                    $lpjPayload['spj_fungsional'] = $this->storeFile($request->file('file_spj_fungsional'), '/File_spj_fungsional', $storedFiles);
                    $lpjPayload['uploaded_by'] = $actorId;
                    $lpjPayload['status'] = null;
                }
                if ($request->hasFile('file_spp')) {
                    $sppPayload['src_name'] = $this->storeFile($request->file('file_spp'), '/File_SPP', $storedFiles);
                    $sppPayload['uploaded_by'] = $actorId;
                    $sppPayload['status'] = null;
                }

                $lpj->update($lpjPayload);
                $spp->update($sppPayload);

                if ($this->requiresBmdFile($request->input('belanja')) && ! $request->hasFile('file_bmd') && ! $existingBmd) {
                    throw new \RuntimeException('File BMD wajib diunggah jika memilih Belanja Modal atau Persediaan.');
                }

                if ($request->hasFile('file_bmd')) {
                    $bmdFile = $this->storeFile($request->file('file_bmd'), '/File_BMD', $storedFiles);
                    $bmd = Document::updateOrCreate(
                        ['reference_id' => $lpj->id, 'src_type' => 'BMD', 'payment_type' => self::PAYMENT_TYPE],
                        [
                            'src_name' => $bmdFile,
                            'id_unit_kerja' => $lpj->id_unit_kerja,
                            'uploaded_by' => $actorId,
                            'assigned_to' => '9',
                            'rejected_by' => null,
                            'notes' => null,
                            'submit' => null,
                            'users_to' => null,
                            'status' => null,
                            'updated_at' => now(),
                        ]
                    );
                    $documentHistoryService->edited($bmd->id, $bmd->src_name, $bmd->id_unit_kerja);
                } elseif ($existingBmd) {
                    $existingBmd->update([
                        'id_unit_kerja' => $lpj->id_unit_kerja,
                        'assigned_to' => '9',
                        'rejected_by' => null,
                        'notes' => null,
                        'submit' => null,
                        'users_to' => null,
                        'updated_at' => now(),
                    ]);
                }

                $existingTbpIds = Document::where('src_type', 'TBP')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('parent_id', $lpj->id)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn ($tbpId) => (int) $tbpId)
                    ->all();

                $toDetach = array_values(array_diff($existingTbpIds, $tbpIds));
                if (! empty($toDetach)) {
                    Document::whereIn('id', $toDetach)->update(['parent_id' => null, 'updated_at' => now()]);
                }
                Document::whereIn('id', $tbpIds)->update(['parent_id' => $lpj->id, 'updated_at' => now()]);

                $this->syncRekening($request->rekening, $lpj->id, $lpj->id_unit_kerja);

                $documentHistoryService->edited($lpj->id, $lpj->src_name, $lpj->id_unit_kerja);
                $documentHistoryService->edited($spp->id, $spp->src_name, $spp->id_unit_kerja);
            });

            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD update success', [
                'lpj_id' => $lpjId,
                'selected_tbp_count' => count($tbpIds),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPP berhasil diperbarui']);
        } catch (ValidationException $e) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD update blocked: validation failed', [
                'errors' => $e->errors(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD update blocked', [
                'lpj_id' => $lpjId,
                'message' => $e->getMessage(),
                'selected_tbp_count' => count($tbpIds),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->error('SPP GU_SKPD update failed', [
                'lpj_id' => $lpjId,
                'message' => $e->getMessage(),
                'selected_tbp_count' => count($tbpIds),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal memperbarui data SPP'], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        try {
            $lpjId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD submit blocked: invalid parameter', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD submit blocked: invalid active position', [
                'lpj_id' => $lpjId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = $user->unitKerja?->id;

        if (! in_array($jabatanId, [9, 5], true)) {
            Log::channel('payment_gu_skpd')->warning('SPP GU_SKPD submit blocked: forbidden role', [
                'lpj_id' => $lpjId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan submit SPP.'], 403);
        }

        Log::channel('payment_gu_skpd')->info('SPP GU_SKPD submit request', [
            'lpj_id' => $lpjId,
        ]);

        try {
            DB::transaction(function () use ($lpjId, $jabatanId, $unitKerjaId, $documentHistoryService) {
                $docs = Document::query()
                    ->where(function ($q) use ($lpjId) {
                        $q->where('id', $lpjId)
                            ->orWhere('reference_id', $lpjId);
                    })
                    ->whereIn('src_type', ['LPJ', 'SPP', 'BMD'])
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->get();

                if ($docs->isEmpty()) {
                    throw new \RuntimeException('Dokumen SPP tidak ditemukan.');
                }

                $lpj = $docs->firstWhere('id', $lpjId);
                if (! $lpj || $lpj->src_type !== 'LPJ') {
                    throw new \RuntimeException('Dokumen LPJ tidak ditemukan.');
                }

                $spp = $docs->firstWhere('src_type', 'SPP');
                if (! $spp) {
                    throw new \RuntimeException('Dokumen SPP tidak ditemukan.');
                }

                if (! $this->canAccessForSubmit($lpj, $jabatanId, $unitKerjaId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini.');
                }

                if (! is_null($lpj->rejected_by)) {
                    throw new \RuntimeException('Dokumen sudah ditolak.');
                }

                if ($jabatanId === 9) {
                    if (! $this->hasStatusForJabatan($lpj->status, $jabatanId)) {
                        throw new \RuntimeException('Dokumen LPJ belum TTE oleh BP.');
                    }

                    if (! $this->hasStatusForJabatan($spp->status, $jabatanId)) {
                        throw new \RuntimeException('Dokumen SPP belum TTE oleh BP.');
                    }
                }

                if ($jabatanId === 5 && ! $this->hasStatusForJabatan($spp->status, $jabatanId)) {
                    throw new \RuntimeException('Dokumen SPP belum TTE oleh PA.');
                }

                $submitArr = $this->csvToArray($lpj->submit);
                $submitCount = array_count_values($submitArr);

                $assignedTo = match ($jabatanId) {
                    9 => match (true) {
                        ($submitCount['9'] ?? 0) === 0 => '5',
                        ($submitCount['9'] ?? 0) === 1 && ($submitCount['5'] ?? 0) >= 1 && ($submitCount['7'] ?? 0) === 0 => '7',
                        default => throw new \RuntimeException('Urutan submit BP tidak valid.'),
                    },
                    5 => (($submitCount['9'] ?? 0) >= 1 && ($submitCount['5'] ?? 0) === 0)
                        ? '9'
                        : throw new \RuntimeException('Dokumen belum dapat disubmit oleh PA.'),
                    default => throw new \RuntimeException('Anda tidak berwenang melakukan submit.'),
                };

                $maxAllowed = $jabatanId === 9 ? 2 : 1;
                if (($submitCount[(string) $jabatanId] ?? 0) >= $maxAllowed) {
                    throw new \RuntimeException(
                        $jabatanId === 9
                            ? 'Dokumen sudah disubmit maksimal 2 kali oleh BP.'
                            : 'Dokumen sudah disubmit oleh PA.'
                    );
                }

                $newSubmit = $lpj->submit
                    ? $lpj->submit.','.$jabatanId
                    : (string) $jabatanId;

                Document::query()
                    ->whereIn('id', $docs->pluck('id')->all())
                    ->update([
                        'submit' => $newSubmit,
                        'assigned_to' => $assignedTo,
                        'updated_at' => now(),
                    ]);

                foreach ($docs as $doc) {
                    $documentHistoryService->submit(
                        $doc->id,
                        (string) $doc->src_name,
                        $doc->id_unit_kerja
                    );
                }
            });

            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD submit success', [
                'lpj_id' => $lpjId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => match ($jabatanId) {
                    9 => 'SPP berhasil disubmit.',
                    5 => 'SPP berhasil disubmit kembali ke BP.',
                    default => 'SPP berhasil disubmit.',
                },
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_gu_skpd')->info('SPP GU_SKPD submit blocked', [
                'lpj_id' => $lpjId,
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_gu_skpd')->error('SPP GU_SKPD submit failed', [
                'lpj_id' => $lpjId,
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 500, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    private function decodeEncryptedIds(array $raw): ?array
    {
        try {
            $ids = [];
            foreach ($raw as $item) {
                $ids[] = (int) EncryptedId::decode($item);
            }

            return array_values(array_unique($ids));
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertTbpAvailability(array $tbpIds, ?int $lpjId, int $unitKerjaId): void
    {
        $count = DB::table('document as tbp')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'tbp.id_unit_kerja')
            ->whereIn('tbp.id', $tbpIds)
            ->where('tbp.src_type', 'TBP')
            ->where('tbp.payment_type', self::PAYMENT_TYPE)
            ->whereNull('tbp.deleted_at')
            ->whereNull('tbp.rejected_by')
            ->where(function ($q) use ($unitKerjaId) {
                $q->where('tbp.id_unit_kerja', $unitKerjaId)
                    ->orWhere('uk.skpd_id', $unitKerjaId);
            })
            ->where(function ($q) use ($lpjId) {
                $q->whereNull('tbp.parent_id');
                if ($lpjId) {
                    $q->orWhere('tbp.parent_id', $lpjId);
                }
            })
            ->lockForUpdate()
            ->count();

        if ($count !== count($tbpIds)) {
            throw new \RuntimeException('Sebagian data TBP tidak valid atau sudah terpakai.');
        }
    }

    private function syncRekening(array $rekening, int $lpjId, int $unitKerjaId): void
    {
        AnggaranKegiatan::where('tahun', session('tahun_aktif'))
            ->where('id_spp', $lpjId)
            ->delete();

        $rekeningInput = collect($rekening)->keyBy('id');
        $tempData = AnggaranKegiatanTemp::where('tahun', session('tahun_aktif'))
            ->whereIn('id_rekening', $rekeningInput->keys())
            ->get()
            ->keyBy('id_rekening');

        $insertData = [];
        foreach ($rekeningInput as $idRekening => $item) {
            if (! isset($tempData[$idRekening])) {
                throw new \RuntimeException('Data rekening tidak ditemukan pada data referensi.');
            }
            $temp = $tempData[$idRekening];
            $insertData[] = [
                'tahun' => $temp->tahun,
                'kode_urusan' => $temp->kode_urusan,
                'nama_urusan' => $temp->nama_urusan,
                'kode_skpd' => $temp->kode_skpd,
                'nama_skpd' => $temp->nama_skpd,
                'kode_sub_unit' => $temp->kode_sub_unit,
                'nama_sub_unit' => $temp->nama_sub_unit,
                'kode_bidang_urusan' => $temp->kode_bidang_urusan,
                'nama_bidang_urusan' => $temp->nama_bidang_urusan,
                'kode_program' => $temp->kode_program,
                'nama_program' => $temp->nama_program,
                'kode_kegiatan' => $temp->kode_kegiatan,
                'nama_kegiatan' => $temp->nama_kegiatan,
                'kode_sub_kegiatan' => $temp->kode_sub_kegiatan,
                'nama_sub_kegiatan' => $temp->nama_sub_kegiatan,
                'kode_sumber_dana' => $temp->kode_sumber_dana,
                'nama_sumber_dana' => $temp->nama_sumber_dana,
                'kode_rekening' => $temp->kode_rekening,
                'nama_rekening' => $temp->nama_rekening,
                'id_rekening' => $idRekening,
                'nominal' => (float) ($item['nominal'] ?? 0),
                'pagu' => $temp->pagu,
                'id_spp' => $lpjId,
                'id_unit_kerja' => $unitKerjaId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($insertData)) {
            AnggaranKegiatan::insert($insertData);
        }
    }

    protected function storeFile($file, string $directory, ?array &$storedFiles = null): string
    {
        $filename = Str::uuid()->toString().'.pdf';
        $targetDir = public_path($directory);
        if (! is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $file->move($targetDir, $filename);
        if (is_array($storedFiles)) {
            $storedFiles[] = $targetDir.DIRECTORY_SEPARATOR.$filename;
        }

        return $filename;
    }

    protected function cleanupStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $path) {
            if ($path && is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function hasStatusForJabatan(?string $status, int $jabatanId): bool
    {
        if (! $status) {
            return false;
        }

        return in_array((string) $jabatanId, $this->csvToArray($status), true);
    }

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== ''));
    }

    private function canManageCrud($user): bool
    {
        return (int) ($user?->jabatan?->id ?? 0) === 9;
    }

    private function findAccessibleLpjForCrud(int $lpjId, int $unitKerjaId): ?Document
    {
        $lpj = Document::where('id', $lpjId)
            ->where('src_type', 'LPJ')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->first();

        if (! $lpj) {
            return null;
        }

        return $this->isDocumentInUserScope((int) $lpj->id_unit_kerja, $unitKerjaId)
            ? $lpj
            : null;
    }

    private function isDocumentInUserScope(int $documentUnitKerjaId, int $unitKerjaId): bool
    {
        if ($documentUnitKerjaId === $unitKerjaId) {
            return true;
        }

        $skpdId = UnitKerja::query()
            ->whereKey($documentUnitKerjaId)
            ->value('skpd_id');

        return (int) $skpdId === $unitKerjaId;
    }

    private function requiresBmdFile(?string $belanja): bool
    {
        if (! $belanja) {
            return false;
        }

        $selected = array_map(
            'intval',
            array_filter(explode(',', (string) $belanja), fn ($v) => trim((string) $v) !== '')
        );

        return count(array_intersect($selected, [1, 2])) > 0;
    }

    private function isTransitioningToRequiredBmd(?string $currentBelanja, ?string $newBelanja): bool
    {
        return ! $this->requiresBmdFile($currentBelanja) && $this->requiresBmdFile($newBelanja);
    }

    private function isEditableForCrud(Document $document): bool
    {
        return is_null($document->submit) || ! is_null($document->rejected_by);
    }

    private function canAccessForSubmit(Document $document, int $jabatanId, ?int $unitKerjaId): bool
    {
        if ($jabatanId === 1) {
            return true;
        }

        if (! $unitKerjaId) {
            return false;
        }

        if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
            return true;
        }

        $skpdId = UnitKerja::query()
            ->whereKey($document->id_unit_kerja)
            ->value('skpd_id');

        if ((int) $skpdId === (int) $unitKerjaId) {
            return true;
        }

        if (
            $document->payment_type === self::PAYMENT_TYPE &&
            $document->src_type === 'LPJ'
        ) {
            return false;
        }

        $assigned = $this->csvToArray($document->assigned_to);

        return in_array((string) $jabatanId, $assigned, true);
    }
}
