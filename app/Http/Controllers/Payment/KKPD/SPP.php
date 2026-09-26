<?php

namespace App\Http\Controllers\Payment\KKPD;

use App\Http\Controllers\Controller;
use App\Models\AnggaranKegiatan;
use App\Models\AnggaranKegiatanTemp;
use App\Models\Document;
use App\Models\Payment\KKPD;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SPP extends Controller
{
    private const SRC_TYPE_SPP = 'SPP';

    private const SRC_TYPE_DPR = 'DPR';

    private const SRC_TYPE_BMD = 'BMD';

    private const FILE_DIR_SPP = '/File_SPP';

    private const FILE_DIR_BMD = '/File_BMD';

    private const LOG_CHANNEL = 'payment_gu_kkpd';

    private function auditorStatusBadge($row): string
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
            return $badge('btn-danger', 'far fa-file-excel', $this->rejectedLabel((int) $row->rejected_by));
        }

        if (! is_null($row->verify)) {
            return $badge('btn-success', 'fas fa-user-check', 'Telah Verifikasi');
        }

        if (! empty($this->csvToArray($row->submit))) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if (! empty($this->csvToArray($row->status))) {
            return $badge('btn-success', 'fas fa-file-contract', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function index()
    {
        Log::channel(self::LOG_CHANNEL)->debug('SPP KKPD index accessed');

        return view('Payment.KKPD.spp');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD json blocked: invalid active position', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;

            if (! in_array($jabatanId, [5, 7, 9, 13], true)) {
                Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD json blocked: forbidden role', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return DataTables::of(collect())->make(true);
            }

            Log::channel(self::LOG_CHANNEL)->debug('SPP KKPD json request');

            $dprAggregate = DB::table('document as dpr_ref')
                ->selectRaw("dpr_ref.reference_id, GROUP_CONCAT(dpr_ref.nomor ORDER BY dpr_ref.created_at SEPARATOR ', ') as nomor_dpr")
                ->where('dpr_ref.src_type', self::SRC_TYPE_DPR)
                ->where('dpr_ref.payment_type', KKPD::PAYMENT_TYPE)
                ->whereNull('dpr_ref.deleted_at')
                ->groupBy('dpr_ref.reference_id');

            $query = KKPD::rootQueryAlias('spp')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'spp.id_unit_kerja')
                ->leftJoinSub($dprAggregate, 'dpr_agg', function ($join) {
                    $join->on('dpr_agg.reference_id', '=', 'spp.id');
                })
                ->where('spp.src_type', self::SRC_TYPE_SPP)
                ->where('spp.payment_type', KKPD::PAYMENT_TYPE)
                ->when(true, function ($q) use ($jabatanId, $unitKerjaId) {
                    if ($jabatanId === 13) {
                        return;
                    }

                    if ($jabatanId === 9) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where(function ($unitScope) use ($unitKerjaId) {
                                $unitScope->where('spp.id_unit_kerja', $unitKerjaId)
                                    ->orWhere('uk.skpd_id', $unitKerjaId);
                            })->where(function ($flowScope) {
                                $flowScope->whereRaw("FIND_IN_SET('9', REPLACE(COALESCE(spp.assigned_to,''), ' ', ''))")
                                    ->orWhereRaw("FIND_IN_SET('9', REPLACE(COALESCE(spp.submit,''), ' ', ''))");
                            });
                        });

                        return;
                    }

                    if ($jabatanId === 5) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where(function ($unitScope) use ($unitKerjaId) {
                                $unitScope->where('spp.id_unit_kerja', $unitKerjaId)
                                    ->orWhere('uk.skpd_id', $unitKerjaId);
                            })->where(function ($flowScope) {
                                $flowScope->whereRaw("FIND_IN_SET('5', REPLACE(COALESCE(spp.assigned_to,''), ' ', ''))")
                                    ->orWhereRaw("FIND_IN_SET('5', REPLACE(COALESCE(spp.submit,''), ' ', ''))");
                            });
                        });

                        return;
                    }

                    if ($jabatanId === 7) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where(function ($unitScope) use ($unitKerjaId) {
                                $unitScope->where('spp.id_unit_kerja', $unitKerjaId)
                                    ->orWhere('uk.skpd_id', $unitKerjaId);
                            })->where(function ($flowScope) {
                                $flowScope->whereRaw("FIND_IN_SET('7', REPLACE(COALESCE(spp.assigned_to,''), ' ', ''))")
                                    ->orWhereRaw("FIND_IN_SET('7', REPLACE(COALESCE(spp.submit,''), ' ', ''))");
                            });
                        });

                        return;
                    }

                    $q->whereRaw('1 = 0');
                })
                ->select([
                    'spp.id',
                    'spp.reference_id',
                    'spp.nomor as nomor_spp',
                    'spp.uraian',
                    'spp.src_name as src_name_spp',
                    'spp.status',
                    'spp.submit',
                    'spp.assigned_to',
                    'spp.rejected_by',
                    'spp.verify',
                    'spp.notes',
                    'spp.nominal',
                    'spp.expenditure_type',
                    'spp.id_unit_kerja',
                    'spp.created_at',
                    'uk.nama as unit_kerja',
                    'dpr_agg.nomor_dpr',
                ])
                ->orderByDesc('spp.created_at');

            $btn = static function (string $value, string $class, string $icon, string $title, string $extra = ''): string {
                return sprintf(
                    '<button value="%s" data-id="%s" class="btn p-2 m-1 %s" title="%s" %s><i class="%s fa-lg"></i></button>',
                    $value,
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
                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row);
                    }

                    $enc = EncryptedId::encode((int) $row->id);
                    $fileUrl = ! is_null($row->status)
                        ? self::FILE_DIR_SPP.'/signs/'.$row->src_name_spp
                        : self::FILE_DIR_SPP.'/'.$row->src_name_spp;
                    $submitArr = $this->csvToArray($row->submit);
                    $assignedArr = $this->csvToArray($row->assigned_to);
                    $signedArr = $this->csvToArray($row->status);
                    $submitCount = array_count_values($submitArr);

                    if (! is_null($row->rejected_by)) {
                        $label = $this->rejectedLabel((int) $row->rejected_by);

                        return '<span type="button" class="btn btn-sm btn-danger show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="'.e((string) ($row->notes ?: 'Dokumen ditolak')).'"'
                            .' data-wenk-color="red"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="far fa-file-excel"></i> '.$label.'</span>';
                    }

                    if ($jabatanId === 7 && ! is_null($row->verify)) {
                        return '<span type="button" class="btn btn-sm btn-success show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Telah Verifikasi"'
                            .' data-wenk-color="green"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-user-check"></i> Telah Verifikasi</span>';
                    }

                    if ($jabatanId === 7) {
                        return '<span type="button" class="btn btn-sm btn-info show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Tampilkan Dokumen"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                    }

                    $alreadySigned = in_array((string) $jabatanId, $signedArr, true);
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

                    if ($submittedByViewer && ! $canResubmit) {
                        return '<span type="button" class="btn btn-sm btn-primary show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Telah Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
                    }

                    if ($canResubmit) {
                        return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Belum Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                    }

                    if ($alreadySigned) {
                        return '<span type="button" class="btn btn-sm btn-success show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-status="1"'
                            .' data-wenk="Sudah TTE"'
                            .' data-wenk-color="green"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-contract"></i> Sudah TTE</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-warning show-document"'
                        .' data-id="'.$enc.'"'
                        .' data-status="0"'
                        .' data-wenk="Belum TTE"'
                        .' data-wenk-color="orange"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode((int) $row->id);
                    $actions = [];
                    $isRejected = ! is_null($row->rejected_by);
                    $isVerified = ! is_null($row->verify);
                    $submitArr = $this->csvToArray($row->submit);
                    $submitCount = array_count_values($submitArr);
                    $assignedArr = $this->csvToArray($row->assigned_to);
                    $signedArr = $this->csvToArray($row->status);
                    $bpSubmitCount = $submitCount['9'] ?? 0;
                    $paSubmitCount = $submitCount['5'] ?? 0;
                    $ppkSubmitCount = $submitCount['7'] ?? 0;
                    $signedByBp = in_array('9', $signedArr, true);
                    $signedByPa = in_array('5', $signedArr, true);

                    if ($jabatanId === 13) {
                        $actions[] = $btn($enc, 'show-document', 'fa-solid fa-eye text-primary', 'Detail Dokumen', 'data-id="'.$enc.'"');
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if ($isVerified) {
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    $bpCanDenied = in_array('9', $assignedArr, true)
                        && $signedByBp
                        && $bpSubmitCount === 1
                        && $paSubmitCount >= 1
                        && $ppkSubmitCount === 0;

                    $canSubmit = match ($jabatanId) {
                        9 => $signedByBp && (
                            $bpSubmitCount === 0
                            || ($bpSubmitCount === 1 && $paSubmitCount >= 1 && $ppkSubmitCount === 0)
                        ),
                        5 => in_array('5', $assignedArr, true)
                            && $signedByPa
                            && $bpSubmitCount >= 1
                            && $paSubmitCount === 0,
                        7 => false,
                        default => false,
                    };

                    if ($jabatanId === 9 && $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="KKPD" data-type="SPP"'
                        );
                    }

                    if ($jabatanId === 5 && ! $isRejected && $bpSubmitCount >= 1 && $paSubmitCount === 0) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="KKPD" data-type="SPP"'
                        );
                    }

                    if ($jabatanId === 9 && ! $isRejected && $bpCanDenied) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="KKPD" data-type="SPP"'
                        );
                    }

                    if (
                        $jabatanId === 7
                        && ! $isRejected
                        && ! $isVerified
                        && $bpSubmitCount >= 2
                        && $paSubmitCount >= 1
                    ) {
                        $actions[] = $btn(
                            $enc,
                            'verify_data',
                            'fas fa-user-check text-success',
                            'Verifikasi',
                            'data-payment="KKPD" data-type="SPP"'
                        );
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="KKPD" data-type="SPP"'
                        );
                    }

                    if ($jabatanId === 9 && $bpSubmitCount === 0 && ! $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="KKPD" data-type="SPP"'
                        );
                    }

                    if (! $isRejected && $canSubmit) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel(self::LOG_CHANNEL)->debug('SPP KKPD json success', [
                'duration_ms' => $this->durationMs($start),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('SPP KKPD json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data SPP.',
            ], 500);
        }
    }

    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD formJson blocked: invalid active position', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            $submitExpr = "REPLACE(COALESCE(dpr.submit,''), ' ', '')";

            if ($jabatanId !== 9) {
                Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD formJson blocked: forbidden role', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return DataTables::of(collect())->make(true);
            }

            $currentSppId = null;
            if ($request->data) {
                try {
                    $currentSppId = EncryptedId::decode($request->data);
                } catch (\Throwable) {
                    Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD formJson invalid hash', [
                        'hash' => $request->data,
                        'duration_ms' => $this->durationMs($start),
                    ]);

                    return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
                }
            }

            Log::channel(self::LOG_CHANNEL)->debug('SPP KKPD formJson request', [
                'hash' => $request->data,
                'current_spp_id' => $currentSppId,
            ]);

            $query = KKPD::rootQueryAlias('dpr')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'dpr.id_unit_kerja')
                ->where('dpr.src_type', self::SRC_TYPE_DPR)
                ->where('dpr.payment_type', KKPD::PAYMENT_TYPE)
                ->whereNull('dpr.rejected_by')
                ->where(function ($q) use ($currentSppId) {
                    $q->whereNull('dpr.reference_id');

                    if ($currentSppId) {
                        $q->orWhere('dpr.reference_id', $currentSppId);
                    }
                })
                ->when(true, function ($q) use ($jabatanId, $unitKerjaId, $submitExpr) {
                    if ($jabatanId === 9) {
                        $q->where(function ($scope) use ($submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('5', {$submitExpr})")
                                ->orWhereRaw("FIND_IN_SET('6', {$submitExpr})");
                        })->where(function ($scope) use ($unitKerjaId) {
                            $scope->where('dpr.id_unit_kerja', $unitKerjaId)
                                ->orWhere('uk.skpd_id', $unitKerjaId);
                        });

                        return;
                    }

                    $q->whereRaw('1 = 0');
                })
                ->select([
                    'dpr.id',
                    'dpr.reference_id',
                    'dpr.nomor',
                    'dpr.src_name',
                    'dpr.status',
                    'dpr.created_at',
                    'uk.nama as unit_kerja',
                ])
                ->orderByDesc('dpr.created_at');

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($row) {
                    $viewerContractUrl = route('document.pdf.viewer', [
                        'document' => EncryptedId::encode((int) $row->id),
                        'resource' => 'document',
                    ]);

                    return '<button type="button" class="btn btn-sm btn-info"'
                        .' data-document-pdf-action="contract"'
                        .' data-pdf-viewer-contract-url="'.e($viewerContractUrl).'"'
                        .' data-wenk="Klik untuk menampilkan dokumen"'
                        .' data-wenk-color="blue">'
                        .'<i class="fa-solid fa-eye"></i> Tampilkan</button>';
                })
                ->addColumn('action', function ($row) use ($currentSppId) {
                    $enc = EncryptedId::encode((int) $row->id);
                    $checked = ($currentSppId && (int) $row->reference_id === (int) $currentSppId) ? 'checked' : '';
                    $deniedButton = $checked ? '' : '<button type="button"'
                        .' class="btn btn-outline-danger denied"'
                        .' value="'.$enc.'"'
                        .' data-wenk="Menolak data"'
                        .' data-payment="KKPD"'
                        .' data-type="DPR"'
                        .' data-wenk-color="red">'
                        .'<i class="fa-solid fa-ban"></i></button>';

                    return '<div class="d-flex justify-content-center"><div class="btn-group btn-group-sm">'
                        .'<input type="checkbox" class="btn-check" name="selected_dpr[]" id="dpr_'.$enc.'" value="'.$enc.'" '.$checked.'>'
                        .'<label class="btn btn-outline-primary" for="dpr_'.$enc.'" data-wenk="Pilih data" data-wenk-color="green">'
                        .'<i class="fa-solid fa-check"></i></label>'
                        .$deniedButton
                        .'</div></div>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);

            Log::channel(self::LOG_CHANNEL)->debug('SPP KKPD formJson success', [
                'current_spp_id' => $currentSppId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('SPP KKPD formJson failed', [
                'error' => $e->getMessage(),
                'hash' => $request->data,
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat pilihan DPR untuk SPP.',
            ], 500);
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('SPP KKPD store request', [
            'nomor_spp' => $request->input('nomor_spp'),
            'selected_dpr_count' => count($request->input('selected_dpr', [])),
            'has_file_spp' => $request->hasFile('file_spp'),
            'has_file_bmd' => $request->hasFile('file_bmd'),
            'belanja' => $request->input('belanja'),
        ]);

        $request->validate([
            'uraian' => ['required', 'string'],
            'nomor_spp' => ['required', 'string', 'max:255'],
            'file_spp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_bmd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'belanja' => ['nullable', 'string'],
            'selected_dpr' => ['required', 'array', 'min:1'],
            'selected_dpr.*' => ['required', 'string'],
            'rekening' => ['required', 'array', 'min:1'],
            'rekening.*.id' => ['required'],
            'rekening.*.nominal' => ['required', 'numeric', 'gt:0'],
        ]);

        $dprIds = $this->decodeEncryptedIds($request->selected_dpr ?? []);
        if (is_null($dprIds) || empty($dprIds)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD store blocked: invalid selected DPR', [
                'selected_dpr' => $request->input('selected_dpr'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'DPR yang dipilih tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD store blocked: forbidden role', [
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat data SPP.'], 403);
        }

        if ($this->requiresBmdFile($request->input('belanja')) && ! $request->hasFile('file_bmd')) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD store blocked: missing required BMD', [
                'belanja' => $request->input('belanja'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 422,
                'message' => 'File BMD wajib diunggah jika memilih Belanja Modal atau Persediaan.',
            ], 422);
        }

        $actorId = (int) $user->id;
        $storedFiles = [];

        try {
            DB::transaction(function () use ($request, $user, $actorId, $dprIds, &$storedFiles, $documentHistoryService) {
                $dprs = $this->findAvailableDprsForForm($dprIds, $user, null, true);
                if ($dprs->count() !== count($dprIds)) {
                    throw new \RuntimeException('DPR yang dipilih tidak tersedia untuk SPP.');
                }

                if ($dprs->pluck('id_unit_kerja')->unique()->count() !== 1) {
                    throw new \RuntimeException('Semua DPR yang dipilih harus berasal dari unit kerja yang sama.');
                }

                $unitKerjaId = (int) $dprs->pluck('id_unit_kerja')->unique()->first();
                $nominal = collect($request->rekening)->sum(fn ($item) => (float) ($item['nominal'] ?? 0));
                $sppFile = $this->storeFile($request->file('file_spp'), self::FILE_DIR_SPP, $storedFiles);

                $spp = Document::create([
                    'nomor' => $request->input('nomor_spp'),
                    'uraian' => $request->input('uraian'),
                    'nominal' => $nominal,
                    'src_name' => $sppFile,
                    'src_type' => self::SRC_TYPE_SPP,
                    'payment_type' => KKPD::PAYMENT_TYPE,
                    'reference_id' => null,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'assigned_to' => $this->resolveAssignedTo($user),
                    'users_to' => $actorId,
                    'expenditure_type' => $request->input('belanja'),
                    'created_at' => now(),
                ]);

                if ($request->hasFile('file_bmd')) {
                    $bmdFile = $this->storeFile($request->file('file_bmd'), self::FILE_DIR_BMD, $storedFiles);

                    $bmd = Document::create([
                        'src_name' => $bmdFile,
                        'src_type' => self::SRC_TYPE_BMD,
                        'payment_type' => KKPD::PAYMENT_TYPE,
                        'reference_id' => $spp->id,
                        'parent_id' => $spp->id,
                        'id_unit_kerja' => $unitKerjaId,
                        'uploaded_by' => $actorId,
                        'assigned_to' => $this->resolveAssignedTo($user),
                        'users_to' => $actorId,
                        'created_at' => now(),
                    ]);

                    $documentHistoryService->upload($bmd->id, $bmd->src_name, $bmd->id_unit_kerja);
                }

                Document::query()
                    ->whereIn('id', $dprs->pluck('id')->all())
                    ->update([
                        'reference_id' => $spp->id,
                        'updated_at' => now(),
                    ]);

                $this->syncRekening($request->rekening, $spp->id, $unitKerjaId);
                $documentHistoryService->upload($spp->id, $spp->src_name, $spp->id_unit_kerja);
            });

            Log::channel(self::LOG_CHANNEL)->info('SPP KKPD store success', [
                'nomor_spp' => $request->input('nomor_spp'),
                'selected_dpr_count' => count($dprIds),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPP berhasil disimpan.']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->info('SPP KKPD store blocked', [
                'message' => $e->getMessage(),
                'nomor_spp' => $request->input('nomor_spp'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('SPP KKPD store failed', [
                'error' => $e->getMessage(),
                'nomor_spp' => $request->input('nomor_spp'),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal menyimpan data SPP.'], 400);
        }
    }

    public function edit(string $id, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->debug('SPP KKPD edit request', [
            'hash' => $id,
        ]);

        try {
            $sppId = EncryptedId::decode($id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD edit invalid hash', [
                'hash' => $id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD edit blocked: forbidden role', [
                'spp_id' => $sppId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah data SPP.'], 403);
        }

        $spp = $this->findSppForCrud($sppId, $user);
        if (! $spp) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD edit not found/forbidden', [
                'spp_id' => $sppId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 404, 'message' => 'Data SPP tidak ditemukan.'], 404);
        }

        if (! $this->canEditDraftOrRejected($spp)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD edit blocked: submitted document', [
                'spp_id' => $sppId,
                'submit' => $spp->submit,
                'rejected_by' => $spp->rejected_by,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Dokumen SPP yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.',
            ], 409);
        }

        $dpr = Document::query()
            ->where('src_type', self::SRC_TYPE_DPR)
            ->where('payment_type', KKPD::PAYMENT_TYPE)
            ->where('reference_id', $spp->id)
            ->whereNull('deleted_at')
            ->get();

        $bmd = Document::query()
            ->where('src_type', self::SRC_TYPE_BMD)
            ->where('payment_type', KKPD::PAYMENT_TYPE)
            ->where('reference_id', $spp->id)
            ->whereNull('deleted_at')
            ->first();

        $rekening = AnggaranKegiatan::query()
            ->tahunAktif()
            ->where('id_spp', $spp->id)
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($item) {
                return [
                    'kode' => $item->id_rekening,
                    'uraian' => $item->nama_rekening,
                    'rekening' => $item->kode_rekening,
                    'sub_kegiatan_id' => $item->kode_sub_kegiatan,
                    'nama_sub_unit' => $item->nama_sub_unit,
                    'unit_kerja_id' => $item->id_unit_kerja,
                    'jumlah' => $item->nominal,
                    'pagu' => $item->pagu,
                    'realisasi' => 0,
                ];
            })
            ->values();

        Log::channel(self::LOG_CHANNEL)->debug('SPP KKPD edit success', [
            'spp_id' => $sppId,
            'duration_ms' => $this->durationMs($start),
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'spp' => $spp,
                'dpr' => $dpr,
                'bmd' => $bmd,
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

        Log::channel(self::LOG_CHANNEL)->info('SPP KKPD update request', [
            'hash' => $id,
            'nomor_spp' => $request->input('nomor_spp'),
            'selected_dpr_count' => count($request->input('selected_dpr', [])),
            'has_file_spp' => $request->hasFile('file_spp'),
            'has_file_bmd' => $request->hasFile('file_bmd'),
        ]);

        $request->validate([
            'uraian' => ['required', 'string'],
            'nomor_spp' => ['required', 'string', 'max:255'],
            'file_spp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_bmd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'belanja' => ['nullable', 'string'],
            'selected_dpr' => ['required', 'array', 'min:1'],
            'selected_dpr.*' => ['required', 'string'],
            'rekening' => ['required', 'array', 'min:1'],
            'rekening.*.id' => ['required'],
            'rekening.*.nominal' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $sppId = EncryptedId::decode($id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD update invalid hash', [
                'hash' => $id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $dprIds = $this->decodeEncryptedIds($request->selected_dpr ?? []);
        if (is_null($dprIds) || empty($dprIds)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD update blocked: invalid selected DPR', [
                'spp_id' => $sppId ?? null,
                'selected_dpr' => $request->input('selected_dpr'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'DPR yang dipilih tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD update blocked: forbidden role', [
                'spp_id' => $sppId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah data SPP.'], 403);
        }

        $actorId = (int) $user->id;
        $storedFiles = [];

        try {
            DB::transaction(function () use ($request, $user, $actorId, $sppId, $dprIds, &$storedFiles, $documentHistoryService) {
                $spp = $this->findSppForCrud($sppId, $user, true);
                if (! $spp) {
                    throw new \RuntimeException('Data SPP tidak ditemukan.');
                }

                if (! $this->canEditDraftOrRejected($spp)) {
                    throw new \RuntimeException('Dokumen SPP yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.');
                }

                $oldDprs = Document::query()
                    ->where('src_type', self::SRC_TYPE_DPR)
                    ->where('payment_type', KKPD::PAYMENT_TYPE)
                    ->where('reference_id', $spp->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->get();

                $newDprs = $this->findAvailableDprsForForm($dprIds, $user, $spp->id, true);
                if ($newDprs->count() !== count($dprIds)) {
                    throw new \RuntimeException('DPR yang dipilih tidak tersedia untuk SPP.');
                }

                $bmd = Document::query()
                    ->where('src_type', self::SRC_TYPE_BMD)
                    ->where('payment_type', KKPD::PAYMENT_TYPE)
                    ->where('reference_id', $spp->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if ($newDprs->pluck('id_unit_kerja')->unique()->count() !== 1) {
                    throw new \RuntimeException('Semua DPR yang dipilih harus berasal dari unit kerja yang sama.');
                }

                if ($this->isTransitioningToRequiredBmd($spp->expenditure_type, $request->input('belanja')) && ! $request->hasFile('file_bmd')) {
                    throw new \RuntimeException('File BMD wajib diunggah karena belanja diubah ke Belanja Modal atau Persediaan.');
                }

                if (
                    $this->requiresBmdFile($request->input('belanja'))
                    && ! $request->hasFile('file_bmd')
                    && ! $bmd
                ) {
                    throw new \RuntimeException('File BMD wajib diunggah jika memilih Belanja Modal atau Persediaan.');
                }

                $unitKerjaId = (int) $newDprs->pluck('id_unit_kerja')->unique()->first();
                $nominal = collect($request->rekening)->sum(fn ($item) => (float) ($item['nominal'] ?? 0));

                $payload = [
                    'nomor' => $request->input('nomor_spp'),
                    'uraian' => $request->input('uraian'),
                    'nominal' => $nominal,
                    'reference_id' => null,
                    'id_unit_kerja' => $unitKerjaId,
                    'expenditure_type' => $request->input('belanja'),
                    'assigned_to' => $this->resolveAssignedTo($user),
                    'users_to' => $actorId,
                    'submit' => null,
                    'rejected_by' => null,
                    'notes' => null,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_spp')) {
                    $payload['src_name'] = $this->storeFile($request->file('file_spp'), self::FILE_DIR_SPP, $storedFiles);
                    $payload['uploaded_by'] = $actorId;
                    $payload['status'] = null;
                }

                $spp->update($payload);

                $oldIds = $oldDprs->pluck('id')->all();
                $newIds = $newDprs->pluck('id')->all();
                $detachIds = array_values(array_diff($oldIds, $newIds));

                if (! empty($detachIds)) {
                    Document::query()
                        ->whereIn('id', $detachIds)
                        ->update([
                            'reference_id' => null,
                            'updated_at' => now(),
                        ]);
                }

                Document::query()
                    ->whereIn('id', $newIds)
                    ->update([
                        'reference_id' => $spp->id,
                        'updated_at' => now(),
                    ]);

                if ($request->hasFile('file_bmd')) {
                    $bmdFile = $this->storeFile($request->file('file_bmd'), self::FILE_DIR_BMD, $storedFiles);

                    $bmd = Document::updateOrCreate(
                        [
                            'reference_id' => $spp->id,
                            'src_type' => self::SRC_TYPE_BMD,
                            'payment_type' => KKPD::PAYMENT_TYPE,
                        ],
                        [
                            'src_name' => $bmdFile,
                            'parent_id' => $spp->id,
                            'id_unit_kerja' => $unitKerjaId,
                            'uploaded_by' => $actorId,
                            'assigned_to' => $this->resolveAssignedTo($user),
                            'users_to' => $actorId,
                            'status' => null,
                            'submit' => null,
                            'rejected_by' => null,
                            'notes' => null,
                            'updated_at' => now(),
                        ]
                    );

                    $documentHistoryService->edited($bmd->id, $bmd->src_name, $bmd->id_unit_kerja);
                } elseif ($bmd) {
                    $bmd->update([
                        'parent_id' => $spp->id,
                        'id_unit_kerja' => $unitKerjaId,
                        'assigned_to' => $this->resolveAssignedTo($user),
                        'users_to' => $actorId,
                        'submit' => null,
                        'rejected_by' => null,
                        'notes' => null,
                        'updated_at' => now(),
                    ]);
                }

                $this->syncRekening($request->rekening, $spp->id, $unitKerjaId);
                $documentHistoryService->edited($spp->id, (string) $spp->src_name, $spp->id_unit_kerja);
            });

            Log::channel(self::LOG_CHANNEL)->info('SPP KKPD update success', [
                'spp_id' => $sppId,
                'selected_dpr_count' => count($dprIds),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPP berhasil diperbarui.']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->info('SPP KKPD update blocked', [
                'spp_id' => $sppId ?? null,
                'message' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('SPP KKPD update failed', [
                'spp_id' => $sppId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal memperbarui data SPP.'], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('SPP KKPD submit request', [
            'hash' => $request->id,
        ]);

        try {
            $sppId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD submit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD submit blocked: invalid active position', [
                'spp_id' => $sppId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan submit SPP.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        if (! in_array($jabatanId, [9, 5], true)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP KKPD submit blocked: forbidden role', [
                'spp_id' => $sppId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan submit SPP.'], 403);
        }

        try {
            $successMessage = 'SPP berhasil disubmit.';

            DB::transaction(function () use ($sppId, $user, $jabatanId, $unitKerjaId, $documentHistoryService, &$successMessage) {
                $spp = $this->findSppForCrud($sppId, $user, true);
                if (! $spp) {
                    throw new \RuntimeException('Data SPP tidak ditemukan.');
                }

                if (! $this->canAccessForSubmit($spp, $jabatanId, $unitKerjaId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini.');
                }

                if (! is_null($spp->rejected_by)) {
                    throw new \RuntimeException('Dokumen SPP sudah ditolak.');
                }

                if (is_null($spp->status)) {
                    throw new \RuntimeException('Dokumen SPP belum TTE.');
                }

                if (! $this->hasStatusForJabatan($spp->status, $jabatanId)) {
                    throw new \RuntimeException('Dokumen SPP belum TTE oleh jabatan aktif.');
                }

                $docs = Document::query()
                    ->where('payment_type', KKPD::PAYMENT_TYPE)
                    ->where(function ($q) use ($spp) {
                        $q->where('id', $spp->id)
                            ->orWhere('reference_id', $spp->id);
                    })
                    ->whereIn('src_type', [self::SRC_TYPE_SPP, self::SRC_TYPE_BMD])
                    ->whereNull('deleted_at')
                    ->get();

                if ($docs->isEmpty()) {
                    throw new \RuntimeException('Dokumen pendukung SPP tidak ditemukan.');
                }

                $submitArr = $this->csvToArray($spp->submit);
                $submitCount = array_count_values($submitArr);
                $currentCount = $submitCount[(string) $jabatanId] ?? 0;

                if ($currentCount >= 1) {
                    $maxAllowed = $jabatanId === 9 ? 2 : 1;
                    if ($currentCount >= $maxAllowed) {
                        throw new \RuntimeException(
                            $jabatanId === 9
                                ? 'Dokumen sudah disubmit maksimal 2 kali oleh BP.'
                                : 'Dokumen sudah disubmit oleh jabatan ini.'
                        );
                    }
                }

                $assignedTo = match ($jabatanId) {
                    9 => match (true) {
                        ($submitCount['9'] ?? 0) === 0 => '5',
                        ($submitCount['9'] ?? 0) === 1 && ($submitCount['5'] ?? 0) >= 1 && ($submitCount['7'] ?? 0) === 0 => '7',
                        default => throw new \RuntimeException('Urutan submit BP tidak valid.'),
                    },
                    5 => (($submitCount['9'] ?? 0) >= 1 && ($submitCount['5'] ?? 0) === 0)
                        ? '9'
                        : throw new \RuntimeException('Dokumen belum dapat disubmit oleh PA.'),
                    default => throw new \RuntimeException('User tidak memiliki hak submit.'),
                };

                $newSubmit = $spp->submit
                    ? $spp->submit.','.$jabatanId
                    : (string) $jabatanId;

                if ($jabatanId === 5 && ! $this->hasStatusForJabatan($spp->status, 5)) {
                    throw new \RuntimeException('Dokumen SPP belum TTE oleh PA.');
                }

                Document::query()
                    ->whereIn('id', $docs->pluck('id')->all())
                    ->update([
                        'submit' => $newSubmit,
                        'assigned_to' => $assignedTo,
                        'updated_at' => now(),
                    ]);

                foreach ($docs as $doc) {
                    $documentHistoryService->submit($doc->id, (string) $doc->src_name, $doc->id_unit_kerja);
                }

                $successMessage = match ($jabatanId) {
                    9 => $assignedTo === '5'
                        ? 'SPP berhasil disubmit ke PA.'
                        : 'SPP berhasil disubmit ke PPK.',
                    5 => 'SPP berhasil disubmit kembali ke BP.',
                    default => 'SPP berhasil disubmit.',
                };
            });

            Log::channel(self::LOG_CHANNEL)->info('SPP KKPD submit success', [
                'spp_id' => $sppId,
                'message' => $successMessage,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 200, 'message' => $successMessage]);
        } catch (\RuntimeException $e) {
            Log::channel(self::LOG_CHANNEL)->info('SPP KKPD submit blocked', [
                'spp_id' => $sppId ?? null,
                'message' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('SPP KKPD submit failed', [
                'spp_id' => $sppId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
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

    private function canCrud($user): bool
    {
        return $user
            && $user->jabatan
            && $user->unitKerja
            && (int) $user->jabatan->id === 9;
    }

    private function resolveAssignedTo($user): ?string
    {
        $jabatanId = (int) ($user->jabatan->id ?? 0);

        return $jabatanId === 9 ? '9' : null;
    }

    private function findSppForCrud(int $id, $user, bool $lock = false): ?Document
    {
        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        $query = Document::query()
            ->with('unitKerja')
            ->whereKey($id)
            ->where('src_type', self::SRC_TYPE_SPP)
            ->where('payment_type', KKPD::PAYMENT_TYPE)
            ->whereNull('deleted_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($jabatanId === 9) {
            $query->where(function ($scope) use ($unitKerjaId) {
                $scope->where('id_unit_kerja', $unitKerjaId)
                    ->orWhereHas('unitKerja', function ($uk) use ($unitKerjaId) {
                        $uk->where('skpd_id', $unitKerjaId);
                    });
            });

            return $query->first();
        }

        if ($jabatanId === 5) {
            $query->where(function ($scope) use ($unitKerjaId) {
                $scope->where('id_unit_kerja', $unitKerjaId)
                    ->orWhereHas('unitKerja', function ($uk) use ($unitKerjaId) {
                        $uk->where('skpd_id', $unitKerjaId);
                    });
            });

            return $query->first();
        }

        if ($jabatanId === 7) {
            $query->where(function ($scope) use ($unitKerjaId) {
                $scope->where('id_unit_kerja', $unitKerjaId)
                    ->orWhereHas('unitKerja', function ($uk) use ($unitKerjaId) {
                        $uk->where('skpd_id', $unitKerjaId);
                    });
            });

            return $query->first();
        }

        return null;
    }

    private function canAccessForSubmit(Document $document, int $jabatanId, int $unitKerjaId): bool
    {
        if ($jabatanId === 9) {
            if ((int) $document->id_unit_kerja === $unitKerjaId) {
                return true;
            }

            return (int) ($document->unitKerja?->skpd_id ?? 0) === $unitKerjaId;
        }

        if ($jabatanId === 5) {
            if ((int) $document->id_unit_kerja === $unitKerjaId) {
                return true;
            }

            return (int) ($document->unitKerja?->skpd_id ?? 0) === $unitKerjaId;
        }

        if ($jabatanId === 7) {
            if ((int) $document->id_unit_kerja === $unitKerjaId) {
                return true;
            }

            return (int) ($document->unitKerja?->skpd_id ?? 0) === $unitKerjaId;
        }

        return false;
    }

    private function canEditDraftOrRejected(Document $spp): bool
    {
        if (! is_null($spp->rejected_by)) {
            return true;
        }

        return trim((string) $spp->submit) === '';
    }

    private function findAvailableDprsForForm(array $ids, $user, ?int $currentSppId = null, bool $lock = false): Collection
    {
        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $submitExpr = "REPLACE(COALESCE(submit,''), ' ', '')";

        $query = Document::query()
            ->with('unitKerja')
            ->whereIn('id', $ids)
            ->where('src_type', self::SRC_TYPE_DPR)
            ->where('payment_type', KKPD::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->whereNull('rejected_by')
            ->where(function ($q) use ($currentSppId) {
                $q->whereNull('reference_id');

                if ($currentSppId) {
                    $q->orWhere('reference_id', $currentSppId);
                }
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($jabatanId === 9) {
            return $query->where(function ($scope) use ($submitExpr) {
                $scope->whereRaw("FIND_IN_SET('5', {$submitExpr})")
                    ->orWhereRaw("FIND_IN_SET('6', {$submitExpr})");
            })
                ->where(function ($scope) use ($unitKerjaId) {
                    $scope->where('id_unit_kerja', $unitKerjaId)
                        ->orWhereHas('unitKerja', function ($uk) use ($unitKerjaId) {
                            $uk->where('skpd_id', $unitKerjaId);
                        });
                })
                ->get();
        }

        return collect();
    }

    private function hasStatusForJabatan(?string $status, int $jabatanId): bool
    {
        return in_array((string) $jabatanId, $this->csvToArray($status), true);
    }

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== ''));
    }

    private function rejectedLabel(int $jabatanId): string
    {
        return match ($jabatanId) {
            5 => 'Ditolak PA',
            7 => 'Ditolak PPK',
            8 => 'Ditolak PPTK',
            9 => 'Ditolak BP',
            default => 'Ditolak',
        };
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

    private function syncRekening(array $rekening, int $sppId, int $unitKerjaId): void
    {
        AnggaranKegiatan::query()
            ->tahunAktif()
            ->where('id_spp', $sppId)
            ->delete();

        $rekeningInput = collect($rekening)->keyBy('id');
        $tempData = AnggaranKegiatanTemp::query()
            ->tahunAktif()
            ->whereIn('id_rekening', $rekeningInput->keys())
            ->get()
            ->keyBy('id_rekening');

        $insertData = [];
        foreach ($rekeningInput as $idRekening => $item) {
            if (! isset($tempData[$idRekening])) {
                throw new \RuntimeException('Data rekening tidak ditemukan pada referensi anggaran.');
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
                'id_spp' => $sppId,
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

    private function durationMs(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }
}
