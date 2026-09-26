<?php

namespace App\Http\Controllers\Payment\GU_UK;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\GU_UK;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SPP extends Controller
{
    private const PAYMENT_TYPE = 'GU_UK';

    private const SRC_TYPE = 'LPJ_BPP';

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
                6 => 'Ditolak KPA',
                9 => 'Ditolak BP',
                default => 'Ditolak',
            };

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if (! empty($this->csvToArray($row->submit))) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if ($this->hasStatusForJabatan($row->status, 10)) {
            return $badge('btn-success', 'fas fa-file-signature', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
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
        Log::channel('payment_gu_unit_kerja')->debug('LPJ_BPP GU_UK index accessed');

        return view('Payment.GU_UK.spp');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK json blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = $user->unitKerja?->id;
            $scopeUnitId = $unitKerjaId ? $this->resolveScopeUnitId((int) $unitKerjaId) : null;

            Log::channel('payment_gu_unit_kerja')->debug('LPJ_BPP GU_UK json request');

            $query = GU_UK::rootQueryAlias('lpj_bpp')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'lpj_bpp.id_unit_kerja')
                ->leftJoinSub(
                    DB::table('document as tbp_idx')
                        ->selectRaw('tbp_idx.parent_id, COUNT(*) as total_tbp')
                        ->where('tbp_idx.src_type', 'TBP')
                        ->where('tbp_idx.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('tbp_idx.deleted_at')
                        ->groupBy('tbp_idx.parent_id'),
                    'tbp_count',
                    function ($join) {
                        $join->on('tbp_count.parent_id', '=', 'lpj_bpp.id');
                    }
                )
                ->where('lpj_bpp.src_type', self::SRC_TYPE)
                ->where('lpj_bpp.payment_type', self::PAYMENT_TYPE)
                ->when(true, function ($q) use ($jabatanId, $unitKerjaId, $scopeUnitId) {
                    if ($jabatanId === 13) {
                        return;
                    }

                    if (in_array($jabatanId, [10, 6], true)) {
                        $q->where('lpj_bpp.id_unit_kerja', $unitKerjaId);

                        return;
                    }

                    if ($jabatanId === 9 && $scopeUnitId) {
                        $q->where(function ($scope) use ($scopeUnitId) {
                            $scope->where('uk.id', $scopeUnitId)
                                ->orWhere('uk.skpd_id', $scopeUnitId);
                        });

                        return;
                    }

                    $q->whereRaw('1 = 0');
                })
                ->select([
                    'lpj_bpp.id',
                    'lpj_bpp.nomor',
                    'lpj_bpp.src_name',
                    'lpj_bpp.spj_fungsional',
                    'lpj_bpp.status',
                    'lpj_bpp.submit',
                    'lpj_bpp.rejected_by',
                    'lpj_bpp.notes',
                    'lpj_bpp.created_at',
                    'uk.nama as unit_kerja',
                    DB::raw('COALESCE(tbp_count.total_tbp, 0) as total_tbp'),
                ])
                ->orderByDesc('lpj_bpp.created_at');

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
                    $enc = EncryptedId::encode($row->id);
                    $tbpButton = ((int) $row->total_tbp > 0)
                        ? '<button type="button" class="btn btn-sm btn-info detail_tbp ms-1"'
                            .' value="'.$enc.'"'
                            .' data-wenk="Tampilkan TBP"'
                            .' data-wenk-color="blue">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan TBP</button>'
                        : '<span class="btn btn-sm btn-secondary ms-1"><i class="fa-solid fa-eye-slash"></i> Tampilkan TBP</span>';

                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row).$tbpButton;
                    }

                    if (! is_null($row->rejected_by)) {
                        $label = match ((int) $row->rejected_by) {
                            6 => 'Ditolak KPA',
                            9 => 'Ditolak BP',
                            default => 'Ditolak',
                        };
                        $rejectedFileUrl = ! is_null($row->status)
                            ? '/File_LPJ_BPP/signs/'.$row->src_name
                            : '/File_LPJ_BPP/'.$row->src_name;

                        return '<span type="button" class="btn btn-sm btn-danger show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="'.e((string) $row->notes).'"'
                            .' data-wenk-color="red"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="far fa-file-excel"></i> '.$label.'</span>'.$tbpButton;
                    }

                    if ($jabatanId !== 10) {
                        $fileUrl = ! is_null($row->status)
                            ? '/File_LPJ_BPP/signs/'.$row->src_name
                            : '/File_LPJ_BPP/'.$row->src_name;

                        return '<span type="button" class="btn btn-sm btn-info show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Tampilkan Dokumen"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>'.$tbpButton;
                    }

                    $submitArr = $this->csvToArray($row->submit);
                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);
                    $fileUrl = ! is_null($row->status)
                        ? '/File_LPJ_BPP/signs/'.$row->src_name
                        : '/File_LPJ_BPP/'.$row->src_name;
                    $alreadySigned = $this->hasStatusForJabatan($row->status, $jabatanId);

                    if ($submittedByViewer) {
                        $lpjButton = '<span type="button" class="btn btn-sm btn-primary show-document"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Telah Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
                    } elseif (! $alreadySigned) {
                        $lpjButton = '<span type="button" class="btn btn-sm btn-warning show-document"'
                            .' data-status="0"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Belum TTE"'
                            .' data-wenk-color="orange"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                    } else {
                        $lpjButton = '<span type="button" class="btn btn-sm btn-success show-document"'
                            .' data-status="1"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Sudah TTE"'
                            .' data-wenk-color="green"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-contract"></i> Sudah TTE</span>';
                    }

                    return $lpjButton.$tbpButton;
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $actions = [];
                    $submitArr = $this->csvToArray($row->submit);
                    $submittedByBpp = in_array('10', $submitArr, true);
                    $isRejected = ! is_null($row->rejected_by);

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

                    if (! in_array($jabatanId, [10, 9], true)) {
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    $alreadySigned = $this->hasStatusForJabatan($row->status, 10);

                    if ($jabatanId === 10 && (! $submittedByBpp || $isRejected)) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_UK" data-type="LPJ_BPP"'
                        );

                        if ($alreadySigned && ! $isRejected) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }
                    }

                    if ($jabatanId === 9 && ! $isRejected && $submittedByBpp) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_UK" data-type="LPJ_BPP"'
                        );
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel('payment_gu_unit_kerja')->debug('LPJ_BPP GU_UK json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_unit_kerja')->error('LPJ_BPP GU_UK json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data LPJ BPP.',
            ], 500);
        }
    }

    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->debug('LPJ_BPP GU_UK formJson request', [
            'edited' => $request->edited === 'true',
            'hash' => $request->data,
        ]);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK formJson blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            if ($jabatanId !== 10) {
                Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK formJson blocked: forbidden role', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Anda tidak berwenang mengakses pilihan TBP.',
                ], 403);
            }

            $unitKerjaId = $user->unitKerja?->id;
            if (! $unitKerjaId) {
                Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK formJson blocked: invalid active unit', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Unit kerja aktif tidak valid.',
                ], 403);
            }

            $isEdited = $request->edited === 'true';
            $lpjId = null;

            if ($request->data) {
                try {
                    $lpjId = EncryptedId::decode($request->data);
                } catch (\Throwable) {
                    Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK formJson invalid hash', [
                        'hash' => $request->data,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json([
                        'status' => 400,
                        'message' => 'Parameter data tidak valid.',
                    ], 400);
                }
            }

            $query = GU_UK::rootQueryAlias('tbp')
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
                    $q->whereNull('tbp.rejected_by')
                        ->orWhere('tbp.rejected_by', '');
                })
                ->where('tbp.id_unit_kerja', $unitKerjaId)
                ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(tbp.submit,''), ' ', ''))", ['6'])
                ->where(function ($q) use ($isEdited, $lpjId) {
                    $q->whereNull('tbp.parent_id');
                    if ($isEdited && $lpjId) {
                        $q->orWhere('tbp.parent_id', $lpjId);
                    }
                })
                ->select([
                    'tbp.id',
                    'tbp.parent_id',
                    'tbp.nomor',
                    'tbp.reference_id',
                    'tbp.src_name',
                    'tbp.status',
                    'tbp.created_at',
                    'uk.nama as unit_kerja',
                    'npd.nomor as nomor_npd',
                ])
                ->orderByDesc('tbp.created_at');

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($data) {
                    $id = EncryptedId::encode($data->reference_id);
                    $url = ! is_null($data->status)
                        ? '/File_TBP/signs/'.$data->src_name
                        : '/File_TBP/'.$data->src_name;

                    return '<span type="button" class="btn btn-sm btn-info show-document"'
                        .' data-id="'.$id.'"'
                        .' data-wenk="Klik untuk menampilkan dokumen"'
                        .' data-wenk-color="blue"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                })
                ->addColumn('action', function ($data) use ($lpjId) {
                    $id = EncryptedId::encode($data->id);
                    $checked = ($lpjId && (int) $data->parent_id === (int) $lpjId) ? 'checked' : '';
                    $deniedButton = $checked ? '' : '<button
                            type="button"
                            class="btn btn-outline-danger denied"
                            value="'.$id.'"
                            data-wenk="Menolak data"
                            data-payment="GU_UK"
                            data-type="TBP"
                            data-wenk-color="red">
                            <i class="fa-solid fa-ban"></i>
                        </button>';

                    return '<div class="d-flex justify-content-center">'
                        .'<div class="btn-group btn-group-sm">'
                        .'<input type="checkbox"'
                        .' class="btn-check"'
                        .' name="selected_tbp[]"'
                        .' id="tbp_'.$id.'"'
                        .' value="'.$id.'"'
                        .' '.$checked.'>'
                        .'<label class="btn btn-outline-primary"'
                        .' for="tbp_'.$id.'"'
                        .' data-wenk="Pilih data"'
                        .' data-wenk-color="green">'
                        .'<i class="fa-solid fa-check"></i>'
                        .'</label>'
                        .$deniedButton
                        .'</div>'
                        .'</div>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);

            Log::channel('payment_gu_unit_kerja')->debug('LPJ_BPP GU_UK formJson success', [
                'edited' => $isEdited,
                'lpj_id' => $lpjId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_unit_kerja')->error('LPJ_BPP GU_UK formJson failed', [
                'hash' => $request->data,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat pilihan TBP.',
            ], 500);
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK store request', [
            'selected_tbp_count' => is_array($request->selected_tbp) ? count($request->selected_tbp) : 0,
            'nomor' => $request->input('nomor_lpj_bpp'),
            'has_file_lpj_bpp' => $request->hasFile('file_lpj_bpp'),
            'has_file_spj_fungsional' => $request->hasFile('file_spj_fungsional'),
        ]);

        $request->validate([
            'selected_tbp' => ['required', 'array', 'min:1'],
            'selected_tbp.*' => ['required', 'string'],
            'nomor_lpj_bpp' => ['required', 'string', 'max:255'],
            'file_lpj_bpp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_spj_fungsional' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $tbpIds = $this->decodeEncryptedIds($request->selected_tbp ?? []);
        if ($tbpIds === null) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK store invalid selected TBP payload', [
                'selected_tbp_count' => is_array($request->selected_tbp) ? count($request->selected_tbp) : 0,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Data TBP tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK store blocked: invalid active position', [
                'selected_tbp_count' => count($tbpIds),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        if ($jabatanId !== 10) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK store blocked: forbidden role', [
                'selected_tbp_count' => count($tbpIds),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat LPJ BPP.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        try {
            DB::transaction(function () use (
                $request,
                $tbpIds,
                $actorId,
                $unitKerjaId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $this->assertTbpAvailability($tbpIds, null, $unitKerjaId);

                $lpjFile = $this->storeFile($request->file('file_lpj_bpp'), '/File_LPJ_BPP', $storedFiles);
                $spjFungsionalFile = $this->storeFile($request->file('file_spj_fungsional'), '/File_spj_fungsional', $storedFiles);

                $lpj = Document::create([
                    'nomor' => $request->input('nomor_lpj_bpp'),
                    'src_name' => $lpjFile,
                    'src_type' => self::SRC_TYPE,
                    'payment_type' => self::PAYMENT_TYPE,
                    'spj_fungsional' => $spjFungsionalFile,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '10',
                    'created_at' => now(),
                ]);

                Document::whereIn('id', $tbpIds)->update([
                    'parent_id' => $lpj->id,
                    'updated_at' => now(),
                ]);

                $documentHistoryService->upload($lpj->id, $lpjFile, $unitKerjaId);
            });

            Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK store success', [
                'selected_tbp_count' => count($tbpIds),
                'nomor' => $request->input('nomor_lpj_bpp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data LPJ BPP berhasil disimpan']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK store blocked', [
                'selected_tbp_count' => count($tbpIds),
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_unit_kerja')->error('LPJ_BPP GU_UK store failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal menyimpan data LPJ BPP'], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->debug('LPJ_BPP GU_UK edit request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK edit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK edit blocked: invalid active position', [
                'lpj_bpp_id' => $id ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        $lpj = Document::query()
            ->where('id', $id)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->when(true, function ($q) use ($jabatanId, $unitKerjaId) {
                if ($jabatanId !== 10) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                $q->where('id_unit_kerja', $unitKerjaId);
            })
            ->first();

        if (! $lpj) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK edit not found/forbidden', [
                'lpj_bpp_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 404, 'message' => 'Data LPJ BPP tidak ditemukan.'], 404);
        }

        if (! $this->canEditDraft($lpj)) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK edit blocked: submitted document', [
                'lpj_bpp_id' => $lpj->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'LPJ BPP yang sudah disubmit tidak dapat diubah.',
            ], 409);
        }

        $selectedTbp = Document::query()
            ->where('src_type', 'TBP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('parent_id', $lpj->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($tbpId) => EncryptedId::encode((int) $tbpId))
            ->values();

        Log::channel('payment_gu_unit_kerja')->debug('LPJ_BPP GU_UK edit success', [
            'lpj_bpp_id' => $lpj->id,
            'selected_tbp_count' => $selectedTbp->count(),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'lpj_bpp' => $lpj,
                'selected_tbp' => $selectedTbp,
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

        Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK update request', [
            'hash' => $id,
            'selected_tbp_count' => is_array($request->selected_tbp) ? count($request->selected_tbp) : 0,
            'nomor' => $request->input('nomor_lpj_bpp'),
            'has_file_lpj_bpp' => $request->hasFile('file_lpj_bpp'),
            'has_file_spj_fungsional' => $request->hasFile('file_spj_fungsional'),
        ]);

        $request->validate([
            'selected_tbp' => ['required', 'array', 'min:1'],
            'selected_tbp.*' => ['required', 'string'],
            'nomor_lpj_bpp' => ['required', 'string', 'max:255'],
            'file_lpj_bpp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_spj_fungsional' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $tbpIds = $this->decodeEncryptedIds($request->selected_tbp ?? []);
        if ($tbpIds === null) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK update invalid selected TBP payload', [
                'hash' => $id,
                'selected_tbp_count' => is_array($request->selected_tbp) ? count($request->selected_tbp) : 0,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Data TBP tidak valid.'], 400);
        }

        try {
            $lpjId = EncryptedId::decode($id);
        } catch (\Throwable) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK update invalid hash', [
                'hash' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK update blocked: invalid active position', [
                'lpj_bpp_id' => $lpjId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        if ($jabatanId !== 10) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK update blocked: forbidden role', [
                'lpj_bpp_id' => $lpjId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah LPJ BPP.'], 403);
        }

        $unitKerjaId = (int) $user->unitKerja->id;
        $actorId = (int) $user->id;
        $storedFiles = [];

        try {
            DB::transaction(function () use (
                $request,
                $lpjId,
                $tbpIds,
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
                    throw new \RuntimeException('Data LPJ BPP tidak ditemukan.');
                }

                if (! $this->canEditDraft($lpj)) {
                    throw new \RuntimeException('LPJ BPP yang sudah disubmit tidak dapat diubah.');
                }

                $this->assertTbpAvailability($tbpIds, $lpj->id, $unitKerjaId);

                $payload = [
                    'nomor' => $request->input('nomor_lpj_bpp'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'assigned_to' => '10',
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_lpj_bpp')) {
                    $payload['src_name'] = $this->storeFile($request->file('file_lpj_bpp'), '/File_LPJ_BPP', $storedFiles);
                    $payload['uploaded_by'] = $actorId;
                    $payload['status'] = null;
                }

                if ($request->hasFile('file_spj_fungsional')) {
                    $payload['spj_fungsional'] = $this->storeFile($request->file('file_spj_fungsional'), '/File_spj_fungsional', $storedFiles);
                    $payload['uploaded_by'] = $actorId;
                }

                $lpj->update($payload);

                $existingTbpIds = Document::query()
                    ->where('src_type', 'TBP')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('parent_id', $lpj->id)
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn ($tbpId) => (int) $tbpId)
                    ->all();

                $toDetach = array_values(array_diff($existingTbpIds, $tbpIds));
                if (! empty($toDetach)) {
                    Document::whereIn('id', $toDetach)->update([
                        'parent_id' => null,
                        'updated_at' => now(),
                    ]);
                }

                Document::whereIn('id', $tbpIds)->update([
                    'parent_id' => $lpj->id,
                    'updated_at' => now(),
                ]);

                $documentHistoryService->edited($lpj->id, $lpj->src_name, $lpj->id_unit_kerja);
            });

            Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK update success', [
                'lpj_bpp_id' => $lpjId,
                'selected_tbp_count' => count($tbpIds),
                'nomor' => $request->input('nomor_lpj_bpp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data LPJ BPP berhasil diperbarui']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK update blocked', [
                'lpj_bpp_id' => $lpjId ?? null,
                'selected_tbp_count' => count($tbpIds),
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_unit_kerja')->error('LPJ_BPP GU_UK update failed', [
                'doc_id' => $lpjId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal memperbarui data LPJ BPP'], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK submit request', [
            'hash' => $request->id,
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK submit invalid hash', [
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
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK submit blocked: invalid active position', [
                'lpj_bpp_id' => $docId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        if ($jabatanId !== 10) {
            Log::channel('payment_gu_unit_kerja')->warning('LPJ_BPP GU_UK submit blocked: forbidden role', [
                'lpj_bpp_id' => $docId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang melakukan submit LPJ BPP.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($docId, $jabatanId, $unitKerjaId, $documentHistoryService) {
                $lpj = Document::query()
                    ->where('id', $docId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('id_unit_kerja', $unitKerjaId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $lpj) {
                    throw new \RuntimeException('Dokumen LPJ BPP tidak ditemukan.');
                }

                if (trim((string) $lpj->rejected_by) !== '') {
                    throw new \RuntimeException('Dokumen LPJ BPP sudah ditolak dan tidak dapat disubmit kembali secara langsung.');
                }

                if (! $this->hasStatusForJabatan($lpj->status, $jabatanId)) {
                    throw new \RuntimeException('Dokumen LPJ BPP belum TTE oleh BPP.');
                }

                $submitArr = $this->csvToArray($lpj->submit);
                if (in_array((string) $jabatanId, $submitArr, true)) {
                    throw new \RuntimeException('Dokumen LPJ BPP sudah disubmit.');
                }

                $newSubmit = $lpj->submit
                    ? $lpj->submit.','.$jabatanId
                    : (string) $jabatanId;

                $lpj->update([
                    'submit' => $newSubmit,
                    'assigned_to' => '9',
                    'updated_at' => now(),
                ]);

                $documentHistoryService->submit(
                    $lpj->id,
                    $lpj->src_name,
                    $lpj->id_unit_kerja
                );
            });

            Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK submit success', [
                'lpj_bpp_id' => $docId,
                'assigned_to' => '9',
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'LPJ BPP berhasil disubmit ke BP.',
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_gu_unit_kerja')->info('LPJ_BPP GU_UK submit blocked', [
                'lpj_bpp_id' => $docId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_gu_unit_kerja')->error('LPJ_BPP GU_UK submit failed', [
                'doc_id' => $docId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan sistem.',
            ], 500);
        }
    }

    private function decodeEncryptedIds(array $encryptedIds): ?array
    {
        $decoded = [];

        try {
            foreach ($encryptedIds as $encryptedId) {
                $id = EncryptedId::decode($encryptedId);
                $decoded[] = (int) $id;
            }
        } catch (\Throwable) {
            return null;
        }

        $decoded = array_values(array_unique(array_filter($decoded, fn ($id) => $id > 0)));

        return empty($decoded) ? null : $decoded;
    }

    private function resolveScopeUnitId(int $unitKerjaId): ?int
    {
        $unit = DB::table('unit_kerjas')
            ->select(['id', 'skpd_id'])
            ->where('id', $unitKerjaId)
            ->first();

        if (! $unit) {
            return null;
        }

        return (int) ($unit->skpd_id ?: $unit->id);
    }

    private function assertTbpAvailability(array $tbpIds, ?int $lpjId, int $unitKerjaId): void
    {
        $documents = Document::query()
            ->whereIn('id', $tbpIds)
            ->where('src_type', 'TBP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('id_unit_kerja', $unitKerjaId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->get();

        if ($documents->count() !== count($tbpIds)) {
            throw new \RuntimeException('Sebagian data TBP tidak ditemukan.');
        }

        foreach ($documents as $document) {
            if (! is_null($document->rejected_by) && trim((string) $document->rejected_by) !== '') {
                throw new \RuntimeException('TBP yang ditolak tidak dapat dipilih.');
            }

            $submit = array_values(array_filter(explode(',', (string) $document->submit), fn ($v) => $v !== ''));
            if (! in_array('6', $submit, true)) {
                throw new \RuntimeException('Hanya TBP yang sudah submit dari KPA yang dapat dipilih.');
            }

            if (! is_null($document->parent_id) && (int) $document->parent_id !== (int) $lpjId) {
                throw new \RuntimeException('Sebagian data TBP sudah terhubung ke LPJ BPP lain.');
            }
        }
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

        return array_values(array_filter(explode(',', $csv), fn ($v) => trim((string) $v) !== ''));
    }

    private function canEditDraft(Document $lpj): bool
    {
        if (! is_null($lpj->rejected_by)) {
            return true;
        }

        return ! in_array('10', $this->csvToArray($lpj->submit), true);
    }
}
