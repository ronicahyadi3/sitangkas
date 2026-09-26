<?php

namespace App\Http\Controllers\Payment\GU_SKPD;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\GU_SKPD;
use App\Models\UnitKerja;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class TBP extends Controller
{
    private const PAYMENT_TYPE = 'GU_SKPD';

    private const SRC_TYPE = 'TBP';

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
            if (! $path || ! is_string($path)) {
                continue;
            }
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function index()
    {
        Log::channel('payment_gu_skpd')->debug('TBP GU_SKPD index accessed');

        return view('Payment.GU_SKPD.tbp');
    }

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
                9 => 'Ditolak BP',
                default => 'Ditolak',
            };

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if (! empty($this->csvToArray($row->submit))) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if (! empty((string) $row->status)) {
            return $badge('btn-success', 'fas fa-file-signature', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD json blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = $user->unitKerja?->id;
            $submitExpr = "REPLACE(COALESCE(tbp.submit,''), ' ', '')";

            Log::channel('payment_gu_skpd')->debug('TBP GU_SKPD json request');

            $query = GU_SKPD::rootQueryAlias('tbp')
                ->leftJoin('unit_kerjas as uk', 'uk.id', '=', 'tbp.id_unit_kerja')
                ->leftJoin('document as npd', function ($join) {
                    $join->on('npd.id', '=', 'tbp.reference_id')
                        ->where('npd.src_type', 'NPD')
                        ->where('npd.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('npd.deleted_at');
                })
                ->leftJoin('document as spj', function ($join) {
                    $join->on('spj.reference_id', '=', 'tbp.reference_id')
                        ->where('spj.src_type', 'SPJ')
                        ->where('spj.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('spj.deleted_at');
                })
                ->where('tbp.src_type', self::SRC_TYPE)
                ->where('tbp.payment_type', self::PAYMENT_TYPE)
                ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($jabatanId, $unitKerjaId, $submitExpr) {
                    $q->where(function ($scope) use ($unitKerjaId) {
                        $scope->where('tbp.id_unit_kerja', $unitKerjaId)
                            ->orWhere('uk.skpd_id', $unitKerjaId);
                    });

                    if ($jabatanId === 5) {
                        $q->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['9']);
                    }
                })
                ->select([
                    'tbp.id',
                    'tbp.reference_id',
                    'tbp.nomor',
                    'tbp.src_name',
                    'tbp.status',
                    'tbp.submit',
                    'tbp.rejected_by',
                    'tbp.notes',
                    'tbp.created_at',
                    'uk.nama as unit_kerja',
                    'npd.nomor as nomor_npd',
                    'spj.id as id_spj',
                    'spj.src_name as src_name_spj',
                    'spj.billing as billing_spj',
                ])
                ->orderByDesc('tbp.created_at');

            $btn = static function (
                string $value,
                string $class,
                string $icon,
                string $title,
                string $extra = ''
            ): string {
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
                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row);
                    }

                    $enc = EncryptedId::encode($row->reference_id);

                    if (! is_null($row->rejected_by)) {
                        $rejectedLabel = match ((int) $row->rejected_by) {
                            5 => 'Ditolak PA',
                            9 => 'Ditolak BP',
                            default => 'Ditolak',
                        };

                        return '<span type="button" class="btn btn-sm btn-danger show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="'.e((string) $row->notes).'"'
                            .' data-wenk-color="red"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="far fa-file-excel"></i> '.$rejectedLabel.'</span>';
                    }

                    $fileUrl = ! is_null($row->status)
                        ? '/File_TBP/signs/'.$row->src_name
                        : '/File_TBP/'.$row->src_name;

                    if (! in_array($jabatanId, [9, 5], true)) {
                        return '<span type="button" class="btn btn-sm btn-info show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Tampilkan Dokumen"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                    }

                    $statusArr = $row->status ? explode(',', $row->status) : [];
                    $submitArr = $row->submit ? explode(',', $row->submit) : [];
                    $alreadySigned = in_array((string) $jabatanId, $statusArr, true);
                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);

                    if ($submittedByViewer) {
                        return '<span type="button" class="btn btn-sm btn-primary show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Telah Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
                    }

                    if (! $alreadySigned) {
                        return '<span type="button" class="btn btn-sm btn-warning show-document"'
                            .' data-status="0"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Belum TTE"'
                            .' data-wenk-color="orange"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                    } else {
                        return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                            .' data-status="1"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Sudah TTE"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                    }

                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $enc_ref = EncryptedId::encode($row->reference_id);

                    $actions = [];

                    if ($jabatanId === 13) {
                        $actions[] = $btn(
                            $enc_ref,
                            'show-document',
                            'fa-solid fa-eye text-primary',
                            'Detail Dokumen',
                            'data-id="'.$enc_ref.'"'
                        );
                        $actions[] = $btn($enc_ref, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (! in_array($jabatanId, [9, 5], true)) {
                        $actions[] = $btn($enc_ref, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    $statusArr = $row->status ? explode(',', $row->status) : [];
                    $submitArr = $row->submit ? explode(',', $row->submit) : [];
                    $alreadySignedByBp = in_array('9', $statusArr, true);
                    $alreadySignedByPa = in_array('5', $statusArr, true);
                    $submittedByBp = in_array('9', $submitArr, true);
                    $submittedByPa = in_array('5', $submitArr, true);

                    if (
                        is_null($row->rejected_by) &&
                        (
                            ($jabatanId === 9 && ! $submittedByBp && $alreadySignedByBp) ||
                            ($jabatanId === 5 && $submittedByBp && ! $submittedByPa && $alreadySignedByPa)
                        )
                    ) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if (
                        $jabatanId === 5 &&
                        is_null($row->rejected_by) &&
                        $submittedByBp &&
                        ! $submittedByPa
                    ) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_SKPD" data-type="TBP"'
                        );
                    }

                    if ($jabatanId === 9 && ! is_null($row->rejected_by)) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_SKPD" data-type="TBP"'
                        );
                    }

                    if (is_null($row->submit)) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_SKPD" data-type="TBP"'
                        );
                    }

                    $actions[] = $btn($enc_ref, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel('payment_gu_skpd')->debug('TBP GU_SKPD json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_skpd')->error('TBP GU_SKPD json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data TBP.',
            ], 500);
        }
    }

    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD formJson blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $unitKerja = $user->unitKerja?->id;
            if (! $unitKerja) {
                Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD formJson blocked: invalid unit kerja', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Unit kerja aktif tidak valid.',
                ], 403);
            }
            $isEdited = $request->edited === 'true';

            Log::channel('payment_gu_skpd')->debug('TBP GU_SKPD formJson request', [
                'edited' => $isEdited,
            ]);

            $tbpId = null;
            if ($request->data) {
                try {
                    $tbpId = EncryptedId::decode($request->data);
                } catch (\Throwable) {
                    Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD formJson invalid data hash', [
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json([
                        'status' => 400,
                        'message' => 'Parameter data tidak valid.',
                    ], 400);
                }
            }

            $currentRef = null;
            if ($tbpId) {
                $currentRef = Document::query()
                    ->where('id', $tbpId)
                    ->where('src_type', 'TBP')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->value('reference_id');
            }

            $query = GU_SKPD::rootQueryAlias('document')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'document.id_unit_kerja')
                ->where('document.src_type', 'NPD')
                ->where('document.payment_type', self::PAYMENT_TYPE)
                ->where(function ($q) {
                    $q->whereNull('document.rejected_by')
                        ->orWhere('document.rejected_by', '');
                })
                ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(document.submit,''), ' ', ''))", ['5'])
                ->where(function ($q) use ($unitKerja) {
                    $q->where('document.id_unit_kerja', $unitKerja)
                        ->orWhere('uk.skpd_id', $unitKerja);
                })
                ->where(function ($q) use ($isEdited, $currentRef) {
                    $q->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('document as tbp')
                            ->whereColumn('tbp.reference_id', 'document.id')
                            ->where('tbp.src_type', 'TBP')
                            ->where('tbp.payment_type', self::PAYMENT_TYPE)
                            ->whereNull('tbp.deleted_at');
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
                    'document.rejected_by',
                    'uk.nama as unit_kerja',
                    'document.created_at',
                ])
                ->orderByDesc('document.created_at');

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($data) {
                    $id = EncryptedId::encode($data->id);
                    $url = ! is_null($data->status)
                        ? '/File_NPD/signs/'.$data->src_name
                        : '/File_NPD/'.$data->src_name;

                    return '<span class="btn btn-sm btn-success show-document"'
                        .' data-id="'.$id.'"'
                        .' data-wenk="Klik untuk menampilkan dokumen"'
                        .' data-wenk-color="green"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                })
                ->addColumn('action', function ($data) use ($request, $currentRef) {
                    $id = EncryptedId::encode($data->id);
                    $checked = ($request->edited === 'true' && (int) $currentRef === (int) $data->id) ? 'checked' : '';
                    $deniedButton = $checked ? '' : '<button
                        type="button"
                        class="btn btn-outline-danger denied"
                        value="'.$id.'"
                        data-wenk="Menolak data"
                        data-payment="GU_SKPD"
                        data-type="NPD"
                        data-wenk-color="red">
                        <i class="fa-solid fa-ban"></i>
                    </button>';

                    return '<div class="d-flex justify-content-center">
                    <div class="btn-group btn-group-sm">
                        <input type="radio"
                            class="btn-check"
                            name="selected_npd"
                            id="npd_'.$id.'"
                            value="'.$id.'"
                            '.$checked.'>

                        <label class="btn btn-outline-primary"
                            for="npd_'.$id.'"
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

            Log::channel('payment_gu_skpd')->debug('TBP GU_SKPD formJson success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_skpd')->error('TBP GU_SKPD formJson failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data pilihan NPD.',
            ], 500);
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_skpd')->info('TBP GU_SKPD store request', [
            'nomor' => $request->input('nomor_tbp'),
            'has_file_tbp' => $request->hasFile('file_tbp'),
            'has_file_spj' => $request->hasFile('file_spj'),
            'has_file_billing' => $request->hasFile('file_billing'),
        ]);

        $request->validate([
            'selected_npd' => ['required', 'string'],
            'nomor_tbp' => ['required', 'string', 'max:255'],
            'file_tbp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_spj' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_billing' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $npdId = EncryptedId::decode($request->selected_npd);
        } catch (\Throwable) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD store invalid selected_npd', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Data NPD tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD store blocked: invalid active position', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD store blocked: forbidden role', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang membuat TBP.',
            ], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        try {
            DB::transaction(function () use (
                $request,
                $npdId,
                $actorId,
                $unitKerjaId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $this->assertAccessibleNpdForCrud($npdId, $unitKerjaId);

                $tbpFile = $this->storeFile($request->file('file_tbp'), '/File_TBP', $storedFiles);
                $spjFile = $this->storeFile($request->file('file_spj'), '/File_SPJ', $storedFiles);
                $billingFile = $this->storeFile($request->file('file_billing'), '/File_Billing', $storedFiles);

                $tbp = Document::create([
                    'nomor' => $request->input('nomor_tbp'),
                    'src_name' => $tbpFile,
                    'src_type' => 'TBP',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $npdId,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '9',
                    'created_at' => now(),
                ]);

                $spj = Document::create([
                    'nomor' => $request->input('nomor_tbp'),
                    'src_name' => $spjFile,
                    'src_type' => 'SPJ',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $npdId,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'billing' => $billingFile,
                    'assigned_to' => '9',
                    'created_at' => now(),
                ]);

                $documentHistoryService->upload($tbp->id, $tbpFile, $unitKerjaId);
                $documentHistoryService->upload($spj->id, $spjFile, $unitKerjaId);
            });

            Log::channel('payment_gu_skpd')->info('TBP GU_SKPD store success', [
                'nomor' => $request->input('nomor_tbp'),
                'reference_id' => $npdId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data TBP berhasil disimpan',
            ]);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->info('TBP GU_SKPD store blocked', [
                'nomor' => $request->input('nomor_tbp'),
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->error('TBP GU_SKPD store failed', [
                'nomor' => $request->input('nomor_tbp'),
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal menyimpan data TBP',
            ], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        Log::channel('payment_gu_skpd')->debug('TBP GU_SKPD edit request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD edit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD edit blocked: invalid active position', [
                'doc_id' => $id ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD edit blocked: forbidden role', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah TBP.',
            ], 403);
        }

        $tbp = $this->findAccessibleTbpForCrud($id, (int) $user->unitKerja->id);

        if (! $tbp) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD edit not found/forbidden', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Data TBP tidak ditemukan.',
            ], 404);
        }

        if (! $this->isEditableForCrud($tbp)) {
            Log::channel('payment_gu_skpd')->info('TBP GU_SKPD edit blocked: non editable state', [
                'doc_id' => $tbp->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Data TBP tidak dapat diubah pada status saat ini.',
            ], 409);
        }

        $spj = Document::query()
            ->where('src_type', 'SPJ')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $tbp->reference_id)
            ->whereNull('deleted_at')
            ->first();

        Log::channel('payment_gu_skpd')->debug('TBP GU_SKPD edit success', [
            'doc_id' => $tbp->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'tbp' => $tbp,
                'spj' => $spj,
                'selected_npd' => EncryptedId::encode((int) $tbp->reference_id),
            ],
        ]);
    }

    public function update(
        Request $request,
        string $id,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_skpd')->info('TBP GU_SKPD update request', [
            'hash' => $id,
            'nomor' => $request->input('nomor_tbp'),
            'has_file_tbp' => $request->hasFile('file_tbp'),
            'has_file_spj' => $request->hasFile('file_spj'),
            'has_file_billing' => $request->hasFile('file_billing'),
        ]);

        $request->validate([
            'selected_npd' => ['required', 'string'],
            'nomor_tbp' => ['required', 'string', 'max:255'],
            'file_tbp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_spj' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_billing' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $tbpId = EncryptedId::decode($id);
            $npdId = EncryptedId::decode($request->selected_npd);
        } catch (\Throwable) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD update invalid hash', [
                'hash' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD update blocked: invalid active position', [
                'doc_id' => $tbpId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD update blocked: forbidden role', [
                'doc_id' => $tbpId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah TBP.',
            ], 403);
        }

        $actorId = (int) $user->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $storedFiles = [];

        try {
            DB::transaction(function () use (
                $request,
                $tbpId,
                $npdId,
                $actorId,
                $unitKerjaId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $tbp = Document::query()
                    ->where('id', $tbpId)
                    ->where('src_type', 'TBP')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $tbp || ! $this->isDocumentInUserScope((int) $tbp->id_unit_kerja, $unitKerjaId)) {
                    throw new \RuntimeException('Data TBP tidak ditemukan.');
                }

                if (! $this->isEditableForCrud($tbp)) {
                    throw new \RuntimeException('Data TBP tidak dapat diubah pada status saat ini.');
                }

                $this->assertAccessibleNpdForCrud($npdId, $unitKerjaId, $tbp->id);

                $spj = Document::query()
                    ->where('src_type', 'SPJ')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('reference_id', $tbp->reference_id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spj) {
                    throw new \RuntimeException('Data SPJ terkait tidak ditemukan.');
                }

                $tbpPayload = [
                    'reference_id' => $npdId,
                    'nomor' => $request->input('nomor_tbp'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                $spjPayload = [
                    'reference_id' => $npdId,
                    'nomor' => $request->input('nomor_tbp'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_tbp')) {
                    $tbpFile = $this->storeFile($request->file('file_tbp'), '/File_TBP', $storedFiles);
                    $tbpPayload['src_name'] = $tbpFile;
                    $tbpPayload['uploaded_by'] = $actorId;
                    $tbpPayload['status'] = null;
                }

                if ($request->hasFile('file_spj')) {
                    $spjFile = $this->storeFile($request->file('file_spj'), '/File_SPJ', $storedFiles);
                    $spjPayload['src_name'] = $spjFile;
                    $spjPayload['uploaded_by'] = $actorId;
                    $spjPayload['status'] = null;
                }

                if ($request->hasFile('file_billing')) {
                    $billingFile = $this->storeFile($request->file('file_billing'), '/File_Billing', $storedFiles);
                    $spjPayload['billing'] = $billingFile;
                }

                $tbp->update($tbpPayload);
                $spj->update($spjPayload);

                $documentHistoryService->edited($tbp->id, $tbp->src_name, $tbp->id_unit_kerja);
                $documentHistoryService->edited($spj->id, $spj->src_name, $spj->id_unit_kerja);
            });

            Log::channel('payment_gu_skpd')->info('TBP GU_SKPD update success', [
                'doc_id' => $tbpId,
                'nomor' => $request->input('nomor_tbp'),
                'reference_id' => $npdId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data TBP berhasil diperbarui',
            ]);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->info('TBP GU_SKPD update blocked', [
                'doc_id' => $tbpId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->error('TBP GU_SKPD update failed', [
                'doc_id' => $tbpId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal memperbarui data TBP',
            ], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_skpd')->info('TBP GU_SKPD submit request', [
            'hash' => $request->id,
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD submit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD submit blocked: invalid active position', [
                'doc_id' => $docId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = $user->unitKerja?->id;

        if (! in_array($jabatanId, [9, 5], true)) {
            Log::channel('payment_gu_skpd')->warning('TBP GU_SKPD submit blocked: forbidden role', [
                'doc_id' => $docId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang melakukan submit TBP.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($docId, $jabatanId, $unitKerjaId, $documentHistoryService) {
                $tbp = Document::query()
                    ->where('id', $docId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $tbp) {
                    throw new \RuntimeException('Dokumen TBP tidak ditemukan');
                }

                if (! $this->canAccessForSubmit($tbp, $jabatanId, $unitKerjaId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini');
                }

                if (! is_null($tbp->rejected_by)) {
                    throw new \RuntimeException('Dokumen sudah ditolak');
                }

                $submitArr = $this->csvToArray($tbp->submit);
                if (in_array((string) $jabatanId, $submitArr, true)) {
                    throw new \RuntimeException('Dokumen sudah disubmit oleh jabatan ini');
                }

                if (! $this->hasStatusForJabatan($tbp->status, $jabatanId)) {
                    throw new \RuntimeException('Dokumen belum TTE oleh jabatan Anda');
                }

                if ($jabatanId === 5 && ! in_array('9', $submitArr, true)) {
                    throw new \RuntimeException('Dokumen belum disubmit oleh BP');
                }

                $newSubmit = $tbp->submit
                    ? $tbp->submit.','.$jabatanId
                    : (string) $jabatanId;

                $assignedTo = $jabatanId === 9 ? '5' : '9';

                $tbp->update([
                    'submit' => $newSubmit,
                    'assigned_to' => $assignedTo,
                    'updated_at' => now(),
                ]);

                $spj = Document::query()
                    ->where('src_type', 'SPJ')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('reference_id', $tbp->reference_id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if ($spj) {
                    $spj->update([
                        'submit' => $newSubmit,
                        'assigned_to' => $assignedTo,
                        'updated_at' => now(),
                    ]);
                }

                $documentHistoryService->submit(
                    $tbp->id,
                    $tbp->src_name,
                    $tbp->id_unit_kerja
                );

                if ($spj) {
                    $documentHistoryService->submit(
                        $spj->id,
                        $spj->src_name,
                        $spj->id_unit_kerja
                    );
                }
            });

            Log::channel('payment_gu_skpd')->info('TBP GU_SKPD submit success', [
                'doc_id' => $docId,
                'assigned_to' => $jabatanId === 9 ? '5' : '9',
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => $jabatanId === 9
                    ? 'TBP berhasil disubmit ke PA.'
                    : 'TBP berhasil disubmit ke BP.',
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_gu_skpd')->info('TBP GU_SKPD submit blocked', [
                'doc_id' => $docId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            report($e);
            Log::channel('payment_gu_skpd')->error('TBP GU_SKPD submit failed', [
                'doc_id' => $docId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan sistem.',
            ], 500);
        }
    }

    private function hasStatusForJabatan(?string $status, int $jabatanId): bool
    {
        if (! $status) {
            return false;
        }

        $statusArr = array_filter(explode(',', $status));

        return in_array((string) $jabatanId, $statusArr, true);
    }

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(explode(',', $csv), fn ($v) => trim((string) $v) !== ''));
    }

    private function canManageCrud($user): bool
    {
        return (int) ($user?->jabatan?->id ?? 0) === 9;
    }

    private function findAccessibleTbpForCrud(int $tbpId, int $unitKerjaId): ?Document
    {
        $tbp = Document::query()
            ->where('id', $tbpId)
            ->where('src_type', 'TBP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->first();

        if (! $tbp) {
            return null;
        }

        return $this->isDocumentInUserScope((int) $tbp->id_unit_kerja, $unitKerjaId)
            ? $tbp
            : null;
    }

    private function assertAccessibleNpdForCrud(int $npdId, int $unitKerjaId, ?int $excludeTbpId = null): void
    {
        $npd = Document::query()
            ->where('id', $npdId)
            ->where('src_type', 'NPD')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (! $npd) {
            throw new \RuntimeException('Data NPD tidak ditemukan.');
        }

        if (! $this->isDocumentInUserScope((int) $npd->id_unit_kerja, $unitKerjaId)) {
            throw new \RuntimeException('Anda tidak berwenang memilih data NPD ini.');
        }

        if (! is_null($npd->rejected_by) && trim((string) $npd->rejected_by) !== '') {
            throw new \RuntimeException('Data NPD sudah ditolak.');
        }

        if (! in_array('5', $this->csvToArray($npd->submit), true)) {
            throw new \RuntimeException('Data NPD belum disubmit oleh PA.');
        }

        $existsTbp = Document::query()
            ->where('src_type', 'TBP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $npdId)
            ->whereNull('deleted_at')
            ->when($excludeTbpId, fn ($q) => $q->where('id', '!=', $excludeTbpId))
            ->exists();

        if ($existsTbp) {
            throw new \RuntimeException('TBP untuk NPD terpilih sudah ada.');
        }
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

        if ($this->isDocumentInUserScope((int) $document->id_unit_kerja, (int) $unitKerjaId)) {
            return true;
        }

        if (
            $document->payment_type === self::PAYMENT_TYPE &&
            $document->src_type === self::SRC_TYPE
        ) {
            return false;
        }

        $assigned = array_filter(explode(',', (string) $document->assigned_to));

        return in_array((string) $jabatanId, $assigned, true);
    }
}
