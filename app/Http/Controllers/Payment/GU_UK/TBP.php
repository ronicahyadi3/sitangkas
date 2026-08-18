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

class TBP extends Controller
{
    private const PAYMENT_TYPE = 'GU_UK';

    private const SRC_TYPE = 'TBP';

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
                10 => 'Ditolak BPP',
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
        Log::channel('payment_gu_unit_kerja')->debug('TBP GU_UK index accessed');

        return view('Payment.GU_UK.tbp');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK json blocked: invalid active position', [
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
            $submitExpr = "REPLACE(COALESCE(tbp.submit,''), ' ', '')";

            Log::channel('payment_gu_unit_kerja')->debug('TBP GU_UK json request');

            $query = GU_UK::rootQueryAlias('tbp')
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
                ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($jabatanId, $unitKerjaId, $scopeUnitId, $submitExpr) {
                    if ($jabatanId === 10) {
                        $q->where('tbp.id_unit_kerja', $unitKerjaId);

                        return;
                    }

                    if (in_array($jabatanId, [9, 5], true) && $scopeUnitId) {
                        $q->where(function ($scope) use ($scopeUnitId) {
                            $scope->where('tbp.id_unit_kerja', $scopeUnitId)
                                ->orWhere('uk.skpd_id', $scopeUnitId);
                        });

                        return;
                    }

                    if ($jabatanId === 6) {
                        $q->where('tbp.id_unit_kerja', $unitKerjaId)
                            ->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['10']);

                        return;
                    }

                    $q->where('tbp.id_unit_kerja', $unitKerjaId);
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

                    $enc = EncryptedId::encode($row->id);
                    $encRef = EncryptedId::encode($row->reference_id);
                    $submitArr = $this->csvToArray($row->submit);
                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);

                    if (! is_null($row->rejected_by)) {
                        $rejectedLabel = match ((int) $row->rejected_by) {
                            6 => 'Ditolak KPA',
                            10 => 'Ditolak BPP',
                            default => 'Ditolak',
                        };

                        return '<span type="button" class="btn btn-sm btn-danger show-document"'
                            .' data-url="/File_TBP/'.$row->src_name.'"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="'.e((string) $row->notes).'"'
                            .' data-wenk-color="red"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="far fa-file-excel"></i> '.$rejectedLabel.'</span>';
                    }

                    $fileUrl = ! is_null($row->status)
                        ? '/File_TBP/signs/'.$row->src_name
                        : '/File_TBP/'.$row->src_name;

                    if (! in_array($jabatanId, [10, 6], true)) {
                        return '<span type="button" class="btn btn-sm btn-info show-document"'
                            .' data-url="'.$fileUrl.'"'
                            .' data-files="'.$row->src_name.'"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="Tampilkan Dokumen"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                    }

                    if ($submittedByViewer) {
                        return '<span type="button" class="btn btn-sm btn-primary show-document"'
                            .' data-url="'.$fileUrl.'"'
                            .' data-files="'.$row->src_name.'"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="Telah Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
                    }

                    $statusArr = $row->status ? explode(',', $row->status) : [];
                    $alreadySigned = in_array((string) $jabatanId, $statusArr, true);

                    if (! $alreadySigned) {
                        return '<span type="button" class="btn btn-sm btn-warning show-document"'
                            .' data-status="0"'
                            .' data-url="'.$fileUrl.'"'
                            .' data-files="'.$row->src_name.'"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="Belum TTE"'
                            .' data-wenk-color="orange"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-success show-document"'
                        .' data-status="1"'
                        .' data-url="'.$fileUrl.'"'
                        .' data-files="'.$row->src_name.'"'
                        .' data-id="'.$encRef.'"'
                        .' data-wenk="Sudah TTE"'
                        .' data-wenk-color="green"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fas fa-file-contract"></i> Sudah TTE</span>';
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $encRef = EncryptedId::encode($row->reference_id);

                    $actions = [];
                    $statusArr = $row->status ? explode(',', $row->status) : [];
                    $submitArr = $this->csvToArray($row->submit);
                    $alreadySigned = in_array((string) $jabatanId, $statusArr, true);
                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);
                    $submittedByBpp = in_array('10', $submitArr, true);
                    $submittedByKpa = in_array('6', $submitArr, true);
                    $isRejected = ! is_null($row->rejected_by);

                    if ($jabatanId === 13) {
                        $actions[] = $btn(
                            $encRef,
                            'show-document',
                            'fa-solid fa-eye text-primary',
                            'Detail Dokumen',
                            'data-id="'.$encRef.'"'
                        );
                        $actions[] = $btn($encRef, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (! in_array($jabatanId, [10, 6], true)) {
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (in_array($jabatanId, [1, 10], true) && ! $submittedByBpp && ! $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_UK" data-type="TBP"'
                        );
                    }

                    if ($jabatanId === 10 && $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_UK" data-type="TBP"'
                        );
                    }

                    if (
                        $jabatanId === 10 &&
                        ! $isRejected &&
                        ! $submittedByViewer &&
                        $alreadySigned
                    ) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if (
                        $jabatanId === 6 &&
                        ! $isRejected &&
                        $submittedByBpp &&
                        ! $submittedByKpa
                    ) {
                        if ($alreadySigned) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_UK" data-type="TBP"'
                        );
                    }

                    $actions[] = $btn($encRef, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel('payment_gu_unit_kerja')->debug('TBP GU_UK json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_unit_kerja')->error('TBP GU_UK json failed', [
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

        Log::channel('payment_gu_unit_kerja')->debug('TBP GU_UK formJson request', [
            'edited' => $request->edited === 'true',
            'hash' => $request->data,
        ]);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK formJson blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            if (! $this->canManageCrud($user)) {
                Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK formJson blocked: forbidden role', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Anda tidak berwenang mengakses pilihan NPD.',
                ], 403);
            }

            $unitKerja = $user->unitKerja?->id;
            if (! $unitKerja) {
                Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK formJson blocked: invalid active unit', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Unit kerja aktif tidak valid.',
                ], 403);
            }

            $isEdited = $request->edited === 'true';
            $tbpId = null;

            if ($request->data) {
                try {
                    $tbpId = EncryptedId::decode($request->data);
                } catch (\Throwable) {
                    Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK formJson invalid hash', [
                        'hash' => $request->data,
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
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->value('reference_id');
            }

            $query = GU_UK::rootQueryAlias('document')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'document.id_unit_kerja')
                ->where('document.src_type', 'NPD')
                ->where('document.payment_type', self::PAYMENT_TYPE)
                ->where(function ($q) {
                    $q->whereNull('document.rejected_by')
                        ->orWhere('document.rejected_by', '');
                })
                ->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(document.submit,''), ' ', ''))", ['6'])
                ->where('document.id_unit_kerja', $unitKerja)
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
                        .' data-url="'.$url.'"'
                        .' data-files="'.$data->src_name.'"'
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
                            data-payment="GU_UK"
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

            Log::channel('payment_gu_unit_kerja')->debug('TBP GU_UK formJson success', [
                'edited' => $isEdited,
                'tbp_id' => $tbpId,
                'current_ref' => $currentRef,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_unit_kerja')->error('TBP GU_UK formJson failed', [
                'hash' => $request->data,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat pilihan NPD.',
            ], 500);
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK store request', [
            'selected_npd' => $request->input('selected_npd'),
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
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK store invalid selected NPD hash', [
                'selected_npd' => $request->input('selected_npd'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Data NPD tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK store blocked: invalid active position', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK store blocked: forbidden role', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang membuat TBP.',
            ], 403);
        }

        $storedFiles = [];
        $actorId = $this->resolveActorUserId($user);
        $unitKerjaId = $user->unitKerja->id;
        $createdTbpId = null;
        $createdSpjId = null;

        try {
            DB::transaction(function () use (
                $request,
                $npdId,
                $actorId,
                $unitKerjaId,
                &$storedFiles,
                $documentHistoryService,
                &$createdTbpId,
                &$createdSpjId
            ) {
                $this->assertEligibleNpd($npdId, $unitKerjaId, true);

                $existsTbp = Document::query()
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('reference_id', $npdId)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($existsTbp) {
                    throw new \RuntimeException('TBP untuk NPD terpilih sudah ada.');
                }

                $tbpFile = $this->storeFile($request->file('file_tbp'), '/File_TBP', $storedFiles);
                $spjFile = $this->storeFile($request->file('file_spj'), '/File_SPJ', $storedFiles);
                $billingFile = $this->storeFile($request->file('file_billing'), '/File_Billing', $storedFiles);

                $tbp = Document::create([
                    'nomor' => $request->input('nomor_tbp'),
                    'src_name' => $tbpFile,
                    'src_type' => self::SRC_TYPE,
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $npdId,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '10',
                    'created_at' => now(),
                ]);
                $createdTbpId = $tbp->id;

                $spj = Document::create([
                    'nomor' => $request->input('nomor_tbp'),
                    'src_name' => $spjFile,
                    'src_type' => 'SPJ',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $npdId,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'billing' => $billingFile,
                    'assigned_to' => '10',
                    'created_at' => now(),
                ]);
                $createdSpjId = $spj->id;

                $documentHistoryService->upload($tbp->id, $tbpFile, $unitKerjaId);
                $documentHistoryService->upload($spj->id, $spjFile, $unitKerjaId);
            });

            Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK store success', [
                'tbp_id' => $createdTbpId,
                'spj_id' => $createdSpjId,
                'selected_npd' => $npdId,
                'nomor' => $request->input('nomor_tbp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data TBP berhasil disimpan',
            ]);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);

            Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK store blocked', [
                'selected_npd' => $npdId ?? null,
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
            Log::channel('payment_gu_unit_kerja')->error('TBP GU_UK store failed', [
                'selected_npd' => $npdId ?? null,
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

        Log::channel('payment_gu_unit_kerja')->debug('TBP GU_UK edit request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK edit invalid hash', [
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
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK edit blocked: invalid active position', [
                'tbp_id' => $id ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK edit blocked: forbidden role', [
                'tbp_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah TBP.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        $tbp = Document::query()
            ->where('id', $id)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->when($jabatanId !== 1, function ($q) use ($jabatanId, $unitKerjaId) {
                if ($jabatanId !== 10) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                $q->where('id_unit_kerja', $unitKerjaId);
            })
            ->first();

        if (! $tbp) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK edit not found/forbidden', [
                'tbp_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Data TBP tidak ditemukan.',
            ], 404);
        }

        if (! $this->canEditDraftOrRejected($tbp)) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK edit blocked: submitted document', [
                'tbp_id' => $tbp->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'TBP yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.',
            ], 409);
        }

        $spj = Document::query()
            ->where('src_type', 'SPJ')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $tbp->reference_id)
            ->whereNull('deleted_at')
            ->first();

        Log::channel('payment_gu_unit_kerja')->debug('TBP GU_UK edit success', [
            'tbp_id' => $tbp->id,
            'spj_id' => $spj?->id,
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

        Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK update request', [
            'hash' => $id,
            'selected_npd' => $request->input('selected_npd'),
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
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK update invalid hash', [
                'hash' => $id,
                'selected_npd' => $request->input('selected_npd'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK update blocked: invalid active position', [
                'tbp_id' => $tbpId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK update blocked: forbidden role', [
                'tbp_id' => $tbpId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah TBP.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $actorId = $this->resolveActorUserId($user);
        $storedFiles = [];

        try {
            DB::transaction(function () use (
                $request,
                $tbpId,
                $npdId,
                $jabatanId,
                $unitKerjaId,
                $actorId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $this->assertEligibleNpd($npdId, $unitKerjaId, true);

                $tbp = Document::query()
                    ->where('id', $tbpId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->when($jabatanId !== 1, function ($q) use ($jabatanId, $unitKerjaId) {
                        if ($jabatanId !== 10) {
                            $q->whereRaw('1 = 0');

                            return;
                        }

                        $q->where('id_unit_kerja', $unitKerjaId);
                    })
                    ->lockForUpdate()
                    ->first();

                if (! $tbp) {
                    throw new \RuntimeException('Data TBP tidak ditemukan.');
                }

                if (! $this->canEditDraftOrRejected($tbp)) {
                    throw new \RuntimeException('TBP yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.');
                }

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

                $duplicate = Document::query()
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('reference_id', $npdId)
                    ->where('id', '!=', $tbp->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($duplicate) {
                    throw new \RuntimeException('TBP untuk NPD terpilih sudah ada.');
                }

                $payload = [
                    'reference_id' => $npdId,
                    'nomor' => $request->input('nomor_tbp'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'assigned_to' => '10',
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                $spjPayload = [
                    'reference_id' => $npdId,
                    'nomor' => $request->input('nomor_tbp'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'assigned_to' => '10',
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_tbp')) {
                    $tbpFile = $this->storeFile($request->file('file_tbp'), '/File_TBP', $storedFiles);
                    $payload = array_merge($payload, [
                        'src_name' => $tbpFile,
                        'uploaded_by' => $actorId,
                        'rejected_by' => null,
                        'notes' => null,
                        'submit' => null,
                        'assigned_to' => '10',
                        'users_to' => null,
                        'status' => null,
                    ]);
                }

                if ($request->hasFile('file_spj')) {
                    $spjFile = $this->storeFile($request->file('file_spj'), '/File_SPJ', $storedFiles);
                    $spjPayload = array_merge($spjPayload, [
                        'src_name' => $spjFile,
                        'uploaded_by' => $actorId,
                        'rejected_by' => null,
                        'notes' => null,
                        'submit' => null,
                        'assigned_to' => '10',
                        'users_to' => null,
                        'status' => null,
                    ]);
                }

                if ($request->hasFile('file_billing')) {
                    $billingFile = $this->storeFile($request->file('file_billing'), '/File_Billing', $storedFiles);
                    $spjPayload['billing'] = $billingFile;
                }

                $tbp->update($payload);
                $spj->update($spjPayload);

                $documentHistoryService->edited($tbp->id, $tbp->src_name, $tbp->id_unit_kerja);
                $documentHistoryService->edited($spj->id, $spj->src_name, $spj->id_unit_kerja);
            });

            Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK update success', [
                'tbp_id' => $tbpId,
                'selected_npd' => $npdId,
                'nomor' => $request->input('nomor_tbp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data TBP berhasil diperbarui',
            ]);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);

            Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK update blocked', [
                'tbp_id' => $tbpId ?? null,
                'selected_npd' => $npdId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_unit_kerja')->error('TBP GU_UK update failed', [
                'tbp_id' => $tbpId ?? null,
                'selected_npd' => $npdId ?? null,
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

        Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK submit request', [
            'hash' => $request->id,
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK submit invalid hash', [
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
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK submit blocked: invalid active position', [
                'tbp_id' => $docId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = $user->unitKerja?->id;

        if (! in_array($jabatanId, [10, 6], true)) {
            Log::channel('payment_gu_unit_kerja')->warning('TBP GU_UK submit blocked: forbidden role', [
                'tbp_id' => $docId,
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

                if ($jabatanId === 6 && ! in_array('10', $submitArr, true)) {
                    throw new \RuntimeException('Dokumen belum disubmit oleh BPP');
                }

                $newSubmit = $tbp->submit
                    ? $tbp->submit.','.$jabatanId
                    : (string) $jabatanId;

                $assignedTo = match ($jabatanId) {
                    10 => '6',
                    6 => '10',
                };

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

            Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK submit success', [
                'tbp_id' => $docId,
                'assigned_to' => $jabatanId === 10 ? '6' : '10',
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => $jabatanId === 10
                    ? 'TBP berhasil disubmit ke KPA.'
                    : 'TBP berhasil disubmit kembali ke BPP.',
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_gu_unit_kerja')->info('TBP GU_UK submit blocked', [
                'tbp_id' => $docId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_gu_unit_kerja')->error('TBP GU_UK submit failed', [
                'tbp_id' => $docId ?? null,
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

    private function resolveActorUserId($user): int
    {
        return (int) (($user->actingPptkUser) ? $user->actingPptkUser->id : $user->id);
    }

    private function canManageCrud($user): bool
    {
        return $user
            && $user->jabatan
            && (int) $user->jabatan->id === 10;
    }

    private function canEditDraftOrRejected(Document $tbp): bool
    {
        if (! is_null($tbp->rejected_by)) {
            return true;
        }

        $submitArr = $this->csvToArray($tbp->submit);

        return ! in_array('10', $submitArr, true);
    }

    private function assertEligibleNpd(int $npdId, int $unitKerjaId, bool $lock = false): Document
    {
        $submitExpr = "REPLACE(COALESCE(document.submit,''), ' ', '')";

        $query = Document::query()
            ->from('document')
            ->where('document.id', $npdId)
            ->where('document.src_type', 'NPD')
            ->where('document.payment_type', self::PAYMENT_TYPE)
            ->where('document.id_unit_kerja', $unitKerjaId)
            ->whereNull('document.deleted_at')
            ->whereNull('document.rejected_by')
            ->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['6']);

        if ($lock) {
            $query->lockForUpdate();
        }

        $npd = $query->first();

        if (! $npd) {
            throw new \RuntimeException('NPD terpilih tidak valid atau belum disubmit oleh KPA.');
        }

        return $npd;
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

        return false;
    }
}
