<?php

namespace App\Http\Controllers\Payment\GU_UK;

use App\Http\Controllers\Controller;
use App\Models\AnggaranKegiatan;
use App\Models\AnggaranKegiatanTemp;
use App\Models\Document;
use App\Models\Payment\GU_UK;
use App\Models\UnitKerja;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class LPJ extends Controller
{
    private const PAYMENT_TYPE = 'GU_UK';

    private const SRC_TYPE = 'LPJ';

    private const LOG_CHANNEL = 'payment_gu_unit_kerja';

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
        Log::channel(self::LOG_CHANNEL)->debug('LPJ GU_UK index accessed');

        return view('Payment.GU_UK.lpj');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK json blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = $user->unitKerja?->id;
            $assignedExpr = "REPLACE(COALESCE(lpj.assigned_to,''), ' ', '')";
            $submitExpr = "REPLACE(COALESCE(lpj.submit,''), ' ', '')";

            Log::channel(self::LOG_CHANNEL)->debug('LPJ GU_UK json request');

            $query = GU_UK::rootQueryAlias('lpj')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'lpj.id_unit_kerja')
                ->leftJoin('document as spp', function ($join) {
                    $join->on('spp.reference_id', '=', 'lpj.id')
                        ->where('spp.src_type', 'SPP')
                        ->where('spp.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('spp.deleted_at');
                })
                ->leftJoin('document as bmd', function ($join) {
                    $join->on('bmd.reference_id', '=', 'lpj.id')
                        ->where('bmd.src_type', 'BMD')
                        ->where('bmd.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('bmd.deleted_at');
                })
                ->leftJoin('document as lpj_bpp', function ($join) {
                    $join->where('lpj_bpp.src_type', 'LPJ_BPP')
                        ->where('lpj_bpp.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('lpj_bpp.deleted_at')
                        ->whereRaw('(lpj_bpp.id = lpj.reference_id OR lpj_bpp.parent_id = lpj.id)');
                })
                ->where('lpj.src_type', self::SRC_TYPE)
                ->where('lpj.payment_type', self::PAYMENT_TYPE)
                ->where(function ($q) use ($jabatanId, $unitKerjaId, $assignedExpr, $submitExpr) {
                    if ($jabatanId === 13) {
                        return;
                    }

                    if (! in_array($jabatanId, [9, 5, 7], true)) {
                        $q->whereRaw('1 = 0');

                        return;
                    }

                    $q->where('lpj.id_unit_kerja', $unitKerjaId);

                    if ($jabatanId === 5) {
                        $q->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['9']);
                    }

                    if ($jabatanId === 7) {
                        $q->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['7'])
                            ->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['5'])
                            ->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['9']);
                    }
                })
                ->select([
                    'lpj.id',
                    'lpj.nomor as nomor_lpj',
                    'lpj.uraian',
                    'lpj.src_name as src_name_lpj',
                    'lpj.status',
                    'lpj.submit',
                    'lpj.rejected_by',
                    'lpj.notes',
                    'lpj.verify',
                    'lpj.created_at',
                    'uk.nama as unit_kerja',
                    'spp.nomor as nomor_spp',
                    'spp.src_name as src_name_spp',
                    'spp.status as status_spp',
                    'bmd.src_name as src_name_bmd',
                    'lpj_bpp.id as id_lpj_bpp',
                    'lpj_bpp.nomor as nomor_lpj_bpp',
                    'lpj_bpp.src_name as src_name_lpj_bpp',
                    'lpj_bpp.status as status_lpj_bpp',
                ])
                ->orderByDesc('lpj.created_at');

            $btn = static function (string $value, string $class, string $icon, string $title, string $extra = ''): string {
                return sprintf(
                    '<button value="%s" class="btn p-2 m-1 %s" title="%s" %s><i class="%s fa-lg"></i></button>',
                    $value,
                    $class,
                    $title,
                    $extra,
                    $icon
                );
            };

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_button', function ($row) use ($jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $lpjBppViewerContractUrl = ! empty($row->id_lpj_bpp)
                        ? route('document.pdf.viewer', [
                            'document' => EncryptedId::encode((int) $row->id_lpj_bpp),
                            'resource' => 'document',
                        ])
                        : null;
                    $lpjBppButton = ! empty($row->src_name_lpj_bpp)
                        && $lpjBppViewerContractUrl !== null
                        ? '<button type="button" class="btn btn-sm btn-info ms-1"'
                            .' data-document-pdf-action="contract"'
                            .' data-pdf-viewer-contract-url="'.e($lpjBppViewerContractUrl).'"'
                            .' data-wenk="Tampilkan LPJ BPP"'
                            .' data-wenk-color="blue"'
                            .'>'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan LPJ BPP</button>'
                        : '<span class="btn btn-sm btn-secondary ms-1"><i class="fa-solid fa-eye-slash"></i> Tampilkan LPJ BPP</span>';

                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row).$lpjBppButton;
                    }

                    $fileUrl = ! is_null($row->status)
                        ? '/File_LPJ/signs/'.$row->src_name_lpj
                        : '/File_LPJ/'.$row->src_name_lpj;

                    if (! is_null($row->rejected_by)) {
                        $label = match ((int) $row->rejected_by) {
                            5 => 'Ditolak PA',
                            7 => 'Ditolak PPK',
                            9 => 'Ditolak BP',
                            default => 'Ditolak',
                        };

                        return '<span type="button" class="btn btn-sm btn-danger show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="'.e((string) $row->notes).'"'
                            .' data-wenk-color="red"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="far fa-file-excel"></i> '.$label.'</span>'.$lpjBppButton;
                    }

                    if (! in_array($jabatanId, [9, 5, 7], true)) {
                        return '<span type="button" class="btn btn-sm btn-info show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Tampilkan Dokumen"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>'.$lpjBppButton;
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
                        9 => $alreadySigned && (
                            (($submitCount['9'] ?? 0) === 0)
                            || (($submitCount['9'] ?? 0) === 1 && ($submitCount['5'] ?? 0) >= 1 && ($submitCount['7'] ?? 0) === 0)
                        ),
                        5 => $alreadySigned
                            && (($submitCount['9'] ?? 0) >= 1)
                            && (($submitCount['5'] ?? 0) === 0),
                        default => false,
                    };

                    if ($jabatanId === 7 && ! is_null($row->verify)) {
                        return '<span type="button" class="btn btn-sm btn-success show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Telah Verifikasi"'
                            .' data-wenk-color="green"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-user-check"></i> Telah Verifikasi</span>'.$lpjBppButton;
                    }

                    if ($jabatanId === 7) {
                        return '<span type="button" class="btn btn-sm btn-info show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Tampilkan Dokumen"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>'.$lpjBppButton;
                    }

                    if ($submittedByViewer && ! $canResubmit) {
                        return '<span type="button" class="btn btn-sm btn-primary show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Telah Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-paper-plane"></i> Telah Submit</span>'.$lpjBppButton;
                    }

                    if ($canResubmit) {
                        return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Belum Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>'.$lpjBppButton;
                    }

                    if (! $alreadySigned) {
                        return '<span type="button" class="btn btn-sm btn-warning show-document"'
                            .' data-status="0"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Belum TTE"'
                            .' data-wenk-color="orange"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-signature"></i> Belum TTE</span>'.$lpjBppButton;
                    }

                    return '<span type="button" class="btn btn-sm btn-success show-document"'
                        .' data-status="1"'
                        .' data-id="'.$enc.'"'
                        .' data-wenk="Sudah TTE"'
                        .' data-wenk-color="green"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fas fa-file-contract"></i> Sudah TTE</span>'.$lpjBppButton;
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
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_UK" data-type="LPJ"'
                        );
                    }

                    if ($jabatanId === 5 && ! $isRejected && $bpSubmitCount >= 1 && $paSubmitCount === 0) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_UK" data-type="LPJ"'
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
                            'data-payment="GU_UK" data-type="LPJ"'
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
                            'data-payment="GU_UK" data-type="LPJ"'
                        );
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_UK" data-type="LPJ"'
                        );
                    }

                    if ($jabatanId === 9 && $bpSubmitCount === 0 && ! $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_UK" data-type="LPJ"'
                        );
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel(self::LOG_CHANNEL)->debug('LPJ GU_UK json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('LPJ GU_UK json failed', [
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
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK formJson blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            if (! $this->canManageCrud($user)) {
                Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK formJson blocked: forbidden role', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengakses pilihan LPJ BPP.'], 403);
            }

            $scopeUnitId = $this->resolveSkpdScopeUnitId((int) $user->unitKerja->id);
            if (! $scopeUnitId) {
                Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK formJson blocked: invalid active unit', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Scope unit kerja aktif tidak valid.'], 403);
            }

            $isEdited = $request->edited === 'true';
            $lpjId = null;
            $currentRef = null;

            Log::channel(self::LOG_CHANNEL)->debug('LPJ GU_UK formJson request', [
                'edited' => $isEdited,
                'hash' => $request->data,
            ]);

            if ($request->data) {
                try {
                    $lpjId = EncryptedId::decode($request->data);
                } catch (\Throwable) {
                    Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK formJson invalid hash', [
                        'hash' => $request->data,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
                }
            }

            if ($lpjId) {
                $currentRef = $this->resolveLinkedLpjBppId($lpjId);
            }

            $query = GU_UK::rootQueryAlias('document')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'document.id_unit_kerja')
                ->where('document.src_type', 'LPJ_BPP')
                ->where('document.payment_type', self::PAYMENT_TYPE)
                ->where(function ($q) {
                    $q->whereNull('document.rejected_by')
                        ->orWhere('document.rejected_by', '');
                })
                ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(document.submit,''), ' ', ''))", ['10'])
                ->where(function ($q) use ($scopeUnitId) {
                    $q->where('uk.id', $scopeUnitId)
                        ->orWhere('uk.skpd_id', $scopeUnitId);
                })
                ->where(function ($q) use ($isEdited, $currentRef) {
                    $q->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('document as lpj')
                            ->where('lpj.src_type', self::SRC_TYPE)
                            ->where('lpj.payment_type', self::PAYMENT_TYPE)
                            ->whereNull('lpj.deleted_at')
                            ->where(function ($link) {
                                $link->whereColumn('lpj.reference_id', 'document.id')
                                    ->orWhereColumn('document.parent_id', 'lpj.id');
                            });
                    });

                    if ($isEdited && $currentRef) {
                        $q->orWhere('document.id', $currentRef);
                    }
                })
                ->select([
                    'document.id',
                    'document.nomor',
                    'document.src_name',
                    'document.status',
                    'document.created_at',
                    'uk.nama as unit_kerja',
                ])
                ->orderByDesc('document.created_at');

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($data) {
                    $id = EncryptedId::encode($data->id);
                    $url = ! is_null($data->status)
                        ? '/File_LPJ_BPP/signs/'.$data->src_name
                        : '/File_LPJ_BPP/'.$data->src_name;

                    return '<span type="button" class="btn btn-sm btn-info show-document"'
                        .' data-id="'.$id.'"'
                        .' data-wenk="Klik untuk menampilkan dokumen"'
                        .' data-wenk-color="blue"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                })
                ->addColumn('action', function ($data) use ($currentRef, $isEdited) {
                    $id = EncryptedId::encode($data->id);
                    $checked = ($isEdited && (int) $currentRef === (int) $data->id) ? 'checked' : '';
                    $deniedButton = $checked ? '' : '<button
                        type="button"
                        class="btn btn-outline-danger denied"
                        value="'.$id.'"
                        data-wenk="Menolak data"
                        data-payment="GU_UK"
                        data-type="LPJ_BPP"
                        data-wenk-color="red">
                        <i class="fa-solid fa-ban"></i>
                    </button>';

                    return '<div class="d-flex justify-content-center">
                    <div class="btn-group btn-group-sm">
                        <input type="radio"
                            class="btn-check"
                            name="selected_lpj_bpp"
                            id="lpj_bpp_'.$id.'"
                            value="'.$id.'"
                            '.$checked.'>

                        <label class="btn btn-outline-primary"
                            for="lpj_bpp_'.$id.'"
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

            Log::channel(self::LOG_CHANNEL)->debug('LPJ GU_UK formJson success', [
                'lpj_id' => $lpjId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('LPJ GU_UK formJson failed', [
                'hash' => $request->data,
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            throw $e;
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK store request', [
            'selected_lpj_bpp' => $request->input('selected_lpj_bpp'),
            'nomor_lpj' => $request->input('nomor_lpj'),
            'nomor_spp' => $request->input('nomor_spp'),
            'has_file_lpj' => $request->hasFile('file_lpj'),
            'has_file_spp' => $request->hasFile('file_spp'),
            'has_file_bmd' => $request->hasFile('file_bmd'),
        ]);

        $request->validate([
            'selected_lpj_bpp' => ['required', 'string'],
            'nomor_lpj' => ['required', 'string', 'max:255'],
            'nomor_spp' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string'],
            'file_lpj' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_spp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_bmd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'belanja' => ['nullable', 'string'],
            'rekening' => ['required', 'array', 'min:1'],
            'rekening.*.id' => ['required'],
            'rekening.*.nominal' => ['required'],
        ]);

        try {
            $lpjBppId = EncryptedId::decode($request->selected_lpj_bpp);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK store invalid LPJ_BPP hash', [
                'selected_lpj_bpp' => $request->input('selected_lpj_bpp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Data LPJ BPP tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK store blocked: invalid active position', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        if (! $this->canManageCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK store blocked: forbidden role', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat LPJ.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        if ($this->requiresBmdFile($request->input('belanja')) && ! $request->hasFile('file_bmd')) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK store blocked: missing required BMD', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 422,
                'message' => 'File BMD wajib diunggah jika memilih Belanja Modal atau Persediaan.',
            ], 422);
        }

        try {
            DB::transaction(function () use (
                $request,
                $lpjBppId,
                $actorId,
                $unitKerjaId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $this->assertLpjBppAvailability($lpjBppId, null, $unitKerjaId);
                $nominal = collect($request->rekening)->sum(fn ($r) => (float) ($r['nominal'] ?? 0));

                $lpjFile = $this->storeFile($request->file('file_lpj'), '/File_LPJ', $storedFiles);
                $sppFile = $this->storeFile($request->file('file_spp'), '/File_SPP', $storedFiles);
                $bmdFile = $request->hasFile('file_bmd')
                    ? $this->storeFile($request->file('file_bmd'), '/File_BMD', $storedFiles)
                    : null;

                $lpj = Document::create([
                    'nomor' => $request->input('nomor_lpj'),
                    'uraian' => $request->input('uraian'),
                    'src_name' => $lpjFile,
                    'src_type' => self::SRC_TYPE,
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $lpjBppId,
                    'nominal' => $nominal,
                    'expenditure_type' => $request->input('belanja'),
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'users_to' => null,
                    'assigned_to' => '9',
                    'created_at' => now(),
                ]);

                $spp = Document::create([
                    'nomor' => $request->input('nomor_spp'),
                    'src_name' => $sppFile,
                    'src_type' => 'SPP',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $lpj->id,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'users_to' => null,
                    'assigned_to' => '9',
                    'created_at' => now(),
                ]);

                $bmd = null;
                if ($bmdFile) {
                    $bmd = Document::create([
                        'src_name' => $bmdFile,
                        'src_type' => 'BMD',
                        'payment_type' => self::PAYMENT_TYPE,
                        'reference_id' => $lpj->id,
                        'id_unit_kerja' => $unitKerjaId,
                        'uploaded_by' => $actorId,
                        'users_to' => null,
                        'assigned_to' => '9',
                        'created_at' => now(),
                    ]);
                }

                Document::where('id', $lpjBppId)->update([
                    'parent_id' => $lpj->id,
                    'updated_at' => now(),
                ]);

                $this->syncRekening($request->rekening, $lpj->id, $unitKerjaId);

                $documentHistoryService->upload($lpj->id, $lpjFile, $unitKerjaId);
                $documentHistoryService->upload($spp->id, $sppFile, $unitKerjaId);
                if ($bmd) {
                    $documentHistoryService->upload($bmd->id, $bmdFile, $unitKerjaId);
                }
            });

            Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK store success', [
                'selected_lpj_bpp' => $request->input('selected_lpj_bpp'),
                'nomor_lpj' => $request->input('nomor_lpj'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data LPJ berhasil disimpan']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK store blocked', [
                'message' => $e->getMessage(),
                'selected_lpj_bpp' => $request->input('selected_lpj_bpp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('LPJ GU_UK store failed', [
                'error' => $e->getMessage(),
                'selected_lpj_bpp' => $request->input('selected_lpj_bpp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal menyimpan data LPJ'], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->debug('LPJ GU_UK edit request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK edit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK edit blocked: invalid active position', [
                'lpj_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        if (! $this->canManageCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK edit blocked: forbidden role', [
                'lpj_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah LPJ.'], 403);
        }

        $lpj = Document::query()
            ->where('id', $id)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($jabatanId, $unitKerjaId) {
                if ($jabatanId !== 9) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                $q->where('id_unit_kerja', $unitKerjaId);
            })
            ->first();

        if (! $lpj) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK edit not found/forbidden', [
                'lpj_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 404, 'message' => 'Data LPJ tidak ditemukan.'], 404);
        }

        if (! $this->isEditableForCrud($lpj)) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK edit blocked: document not editable', [
                'lpj_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Data LPJ tidak dapat diubah pada status saat ini.',
            ], 409);
        }

        $spp = Document::query()
            ->where('src_type', 'SPP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $lpj->id)
            ->whereNull('deleted_at')
            ->first();

        $bmd = Document::query()
            ->where('src_type', 'BMD')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $lpj->id)
            ->whereNull('deleted_at')
            ->first();

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

        Log::channel(self::LOG_CHANNEL)->debug('LPJ GU_UK edit success', [
            'lpj_id' => $id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $selectedLpjBppId = $this->resolveLinkedLpjBppId((int) $lpj->id);

        return response()->json([
            'status' => 200,
            'data' => [
                'lpj' => $lpj,
                'spp' => $spp,
                'bmd' => $bmd,
                'selected_lpj_bpp' => $selectedLpjBppId ? EncryptedId::encode($selectedLpjBppId) : null,
            ],
            'rekening' => $rekening,
        ]);
    }

    public function update(
        Request $request,
        string $id,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK update request', [
            'hash' => $id,
            'selected_lpj_bpp' => $request->input('selected_lpj_bpp'),
            'nomor_lpj' => $request->input('nomor_lpj'),
            'nomor_spp' => $request->input('nomor_spp'),
            'has_file_lpj' => $request->hasFile('file_lpj'),
            'has_file_spp' => $request->hasFile('file_spp'),
            'has_file_bmd' => $request->hasFile('file_bmd'),
        ]);

        $request->validate([
            'selected_lpj_bpp' => ['required', 'string'],
            'nomor_lpj' => ['required', 'string', 'max:255'],
            'nomor_spp' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string'],
            'file_lpj' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_spp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_bmd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'belanja' => ['nullable', 'string'],
            'rekening' => ['required', 'array', 'min:1'],
            'rekening.*.id' => ['required'],
            'rekening.*.nominal' => ['required'],
        ]);

        try {
            $lpjId = EncryptedId::decode($id);
            $lpjBppId = EncryptedId::decode($request->selected_lpj_bpp);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK update invalid hash', [
                'hash' => $id,
                'selected_lpj_bpp' => $request->input('selected_lpj_bpp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK update blocked: invalid active position', [
                'lpj_id' => $lpjId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        if (! $this->canManageCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK update blocked: forbidden role', [
                'lpj_id' => $lpjId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah LPJ.'], 403);
        }

        $unitKerjaId = (int) $user->unitKerja->id;
        $actorId = (int) $user->id;
        $storedFiles = [];

        try {
            DB::transaction(function () use (
                $request,
                $lpjId,
                $lpjBppId,
                $unitKerjaId,
                $actorId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $lpj = Document::query()
                    ->where('id', $lpjId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('id_unit_kerja', $unitKerjaId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $lpj) {
                    throw new \RuntimeException('Data LPJ tidak ditemukan.');
                }

                if (! $this->isEditableForCrud($lpj)) {
                    throw new \RuntimeException('Data LPJ tidak dapat diubah pada status saat ini.');
                }

                $previousLpjBppId = (int) $lpj->reference_id;

                $spp = Document::query()
                    ->where('src_type', 'SPP')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('reference_id', $lpj->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spp) {
                    throw new \RuntimeException('Data SPP terkait tidak ditemukan.');
                }

                $bmd = Document::query()
                    ->where('src_type', 'BMD')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('reference_id', $lpj->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                $this->assertLpjBppAvailability($lpjBppId, $lpj->id, $unitKerjaId);
                if ($this->isTransitioningToRequiredBmd($lpj->expenditure_type, $request->input('belanja')) && ! $request->hasFile('file_bmd')) {
                    throw new \RuntimeException('File BMD wajib diunggah karena belanja diubah ke Belanja Modal atau Persediaan.');
                }

                if (
                    $this->requiresBmdFile($request->input('belanja'))
                    && ! $request->hasFile('file_bmd')
                    && ! $bmd
                ) {
                    throw new \RuntimeException('File BMD wajib diunggah jika memilih Belanja Modal atau Persediaan.');
                }

                $nominal = collect($request->rekening)->sum(fn ($r) => (float) ($r['nominal'] ?? 0));

                $lpjPayload = [
                    'reference_id' => $lpjBppId,
                    'nomor' => $request->input('nomor_lpj'),
                    'uraian' => $request->input('uraian'),
                    'nominal' => $nominal,
                    'expenditure_type' => $request->input('belanja'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'users_to' => null,
                    'assigned_to' => '9',
                    'verify' => null,
                    'updated_at' => now(),
                ];

                $sppPayload = [
                    'nomor' => $request->input('nomor_spp'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'users_to' => null,
                    'assigned_to' => '9',
                    'verify' => null,
                    'updated_at' => now(),
                ];

                $hasNewLpjFile = $request->hasFile('file_lpj');
                $hasNewSppFile = $request->hasFile('file_spp');

                if ($hasNewLpjFile) {
                    $lpjPayload = array_merge($lpjPayload, [
                        'status' => null,
                        'submit' => null,
                        'assigned_to' => '9',
                        'users_to' => null,
                        'rejected_by' => null,
                        'notes' => null,
                        'verify' => null,
                        'uploaded_by' => $actorId,
                    ]);
                    $lpjPayload['src_name'] = $this->storeFile($request->file('file_lpj'), '/File_LPJ', $storedFiles);
                }

                if ($hasNewSppFile) {
                    $sppPayload = array_merge($sppPayload, [
                        'status' => null,
                        'submit' => null,
                        'assigned_to' => '9',
                        'users_to' => null,
                        'rejected_by' => null,
                        'notes' => null,
                        'verify' => null,
                        'uploaded_by' => $actorId,
                    ]);
                    $sppPayload['src_name'] = $this->storeFile($request->file('file_spp'), '/File_SPP', $storedFiles);
                }

                $lpj->update($lpjPayload);
                $spp->update($sppPayload);

                if ($previousLpjBppId !== $lpjBppId) {
                    if ($previousLpjBppId > 0) {
                        Document::where('id', $previousLpjBppId)
                            ->where('src_type', 'LPJ_BPP')
                            ->where('payment_type', self::PAYMENT_TYPE)
                            ->update([
                                'parent_id' => null,
                                'updated_at' => now(),
                            ]);
                    }

                    Document::where('id', $lpjBppId)
                        ->where('src_type', 'LPJ_BPP')
                        ->where('payment_type', self::PAYMENT_TYPE)
                        ->update([
                            'parent_id' => $lpj->id,
                            'updated_at' => now(),
                        ]);
                } else {
                    Document::where('id', $lpjBppId)
                        ->where('src_type', 'LPJ_BPP')
                        ->where('payment_type', self::PAYMENT_TYPE)
                        ->update([
                            'parent_id' => $lpj->id,
                            'updated_at' => now(),
                        ]);
                }

                if ($request->hasFile('file_bmd')) {
                    $bmdFile = $this->storeFile($request->file('file_bmd'), '/File_BMD', $storedFiles);
                    $bmd = Document::updateOrCreate(
                        ['reference_id' => $lpj->id, 'src_type' => 'BMD', 'payment_type' => self::PAYMENT_TYPE],
                        [
                            'src_name' => $bmdFile,
                            'id_unit_kerja' => $lpj->id_unit_kerja,
                            'uploaded_by' => $actorId,
                            'users_to' => null,
                            'assigned_to' => '9',
                            'updated_at' => now(),
                        ]
                    );
                    $documentHistoryService->edited($bmd->id, $bmd->src_name, $bmd->id_unit_kerja);
                } elseif ($bmd) {
                    $bmd->touch();
                }

                $this->syncRekening($request->rekening, $lpj->id, $lpj->id_unit_kerja);

                $documentHistoryService->edited($lpj->id, $lpj->src_name, $lpj->id_unit_kerja);
                $documentHistoryService->edited($spp->id, $spp->src_name, $spp->id_unit_kerja);
            });

            Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK update success', [
                'lpj_id' => $lpjId,
                'selected_lpj_bpp' => $request->input('selected_lpj_bpp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data LPJ berhasil diperbarui']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK update blocked', [
                'lpj_id' => $lpjId ?? null,
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('LPJ GU_UK update failed', [
                'doc_id' => $lpjId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal memperbarui data LPJ'], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK submit request', [
            'hash' => $request->id,
        ]);

        try {
            $lpjId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK submit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK submit blocked: invalid active position', [
                'lpj_id' => $lpjId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = $user->unitKerja?->id;

        if (! in_array($jabatanId, [9, 5], true)) {
            Log::channel(self::LOG_CHANNEL)->warning('LPJ GU_UK submit blocked: forbidden role', [
                'lpj_id' => $lpjId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan submit LPJ.'], 403);
        }

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
                    throw new \RuntimeException('Dokumen LPJ tidak ditemukan.');
                }

                $lpj = $docs->firstWhere('id', $lpjId);
                if (! $lpj || $lpj->src_type !== 'LPJ') {
                    throw new \RuntimeException('Dokumen LPJ tidak ditemukan.');
                }

                $spp = $docs->firstWhere('src_type', 'SPP');
                if (! $spp) {
                    throw new \RuntimeException('Dokumen SPP tidak ditemukan.');
                }

                if ((int) $lpj->id_unit_kerja !== (int) $unitKerjaId && $jabatanId !== 1) {
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

            Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK submit success', [
                'lpj_id' => $lpjId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => match ($jabatanId) {
                    9 => 'LPJ berhasil disubmit.',
                    5 => 'LPJ berhasil disubmit kembali ke BP.',
                    default => 'LPJ berhasil disubmit.',
                },
            ]);
        } catch (\RuntimeException $e) {
            Log::channel(self::LOG_CHANNEL)->info('LPJ GU_UK submit blocked', [
                'lpj_id' => $lpjId ?? null,
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('LPJ GU_UK submit failed', [
                'doc_id' => $lpjId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 500, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    private function assertLpjBppAvailability(int $lpjBppId, ?int $currentLpjId, int $unitKerjaId): void
    {
        $scopeUnitId = $this->resolveSkpdScopeUnitId($unitKerjaId);

        $document = Document::query()
            ->join('unit_kerjas as uk', 'uk.id', '=', 'document.id_unit_kerja')
            ->where('document.id', $lpjBppId)
            ->where('document.src_type', 'LPJ_BPP')
            ->where('document.payment_type', self::PAYMENT_TYPE)
            ->where(function ($q) use ($scopeUnitId) {
                $q->where('uk.id', $scopeUnitId)
                    ->orWhere('uk.skpd_id', $scopeUnitId);
            })
            ->whereNull('document.deleted_at')
            ->lockForUpdate()
            ->select('document.*')
            ->first();

        if (! $document) {
            throw new \RuntimeException('Data LPJ BPP tidak ditemukan dalam SKPD yang sama.');
        }

        if (! is_null($document->rejected_by) && trim((string) $document->rejected_by) !== '') {
            throw new \RuntimeException('LPJ BPP yang ditolak tidak dapat dipilih.');
        }

        $submit = array_values(array_filter(explode(',', (string) $document->submit), fn ($v) => trim((string) $v) !== ''));
        if (! in_array('10', $submit, true)) {
            throw new \RuntimeException('LPJ BPP belum disubmit oleh BPP.');
        }

        $usedByOther = Document::query()
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $lpjBppId)
            ->when($currentLpjId, fn ($q) => $q->where('id', '!=', $currentLpjId))
            ->whereNull('deleted_at')
            ->exists();

        if ($usedByOther) {
            throw new \RuntimeException('LPJ BPP sudah terhubung ke LPJ lain.');
        }

        if (! is_null($document->parent_id) && (int) $document->parent_id !== (int) $currentLpjId) {
            throw new \RuntimeException('LPJ BPP sudah memiliki relasi parent ke LPJ lain.');
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

    private function resolveSkpdScopeUnitId(int $unitKerjaId): ?int
    {
        $unit = UnitKerja::query()
            ->select(['id', 'skpd_id'])
            ->whereKey($unitKerjaId)
            ->first();

        if (! $unit) {
            return null;
        }

        return (int) ($unit->skpd_id ?: $unit->id);
    }

    private function resolveLinkedLpjBppId(int $lpjId): ?int
    {
        $lpj = Document::query()
            ->select(['id', 'reference_id'])
            ->where('id', $lpjId)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->first();

        if (! $lpj) {
            return null;
        }

        $referenceId = (int) ($lpj->reference_id ?? 0);
        if ($referenceId > 0) {
            $isValidReference = Document::query()
                ->where('id', $referenceId)
                ->where('src_type', 'LPJ_BPP')
                ->where('payment_type', self::PAYMENT_TYPE)
                ->whereNull('deleted_at')
                ->exists();

            if ($isValidReference) {
                return $referenceId;
            }
        }

        $legacyLinkedId = Document::query()
            ->where('src_type', 'LPJ_BPP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('parent_id', $lpjId)
            ->whereNull('deleted_at')
            ->value('id');

        return $legacyLinkedId ? (int) $legacyLinkedId : null;
    }

    private function canManageCrud($user): bool
    {
        return (int) ($user?->jabatan?->id ?? 0) === 9;
    }

    private function hasStatusForJabatan(?string $status, int $jabatanId): bool
    {
        return in_array((string) $jabatanId, $this->csvToArray($status), true);
    }

    private function isEditableForCrud(Document $document): bool
    {
        return is_null($document->submit) || trim((string) $document->submit) === '' || ! is_null($document->rejected_by);
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

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== ''));
    }
}
