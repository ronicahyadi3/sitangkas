<?php

namespace App\Http\Controllers\Payment\LS;

use App\Http\Controllers\Controller;
use App\Http\Requests\LS\StoreSp2dRequest;
use App\Http\Requests\LS\UpdateSp2dRequest;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Payment\LS;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SP2D extends Controller
{
    public function __construct(private readonly PositionIdentityResolver $positionIdentityResolver) {}

    protected function storeFile($file, $directory, ?array &$storedFiles = null)
    {
        $filename = Str::uuid()->toString().'.pdf';
        $targetDir = public_path($directory);
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

    protected function saveDocumentData(array $data)
    {
        $document = new Document($data);
        $document->save();

        return $document;
    }

    protected function updateDocumentData(int $documentId, array $data): ?Document
    {
        $updated = Document::where('id', $documentId)->update($data);

        if (! $updated) {
            return null;
        }

        return Document::find($documentId);
    }

    protected function ButtonStatus($id, $data, array $jabatanNameMap, int $viewerJabatanId)
    {

        $statusArr = $data->status ? explode(',', $data->status) : [];

        $status = in_array($id, $statusArr);
        $encript = EncryptedId::encode($data->reference_id);

        $rejectedBy = $jabatanNameMap[$data->rejected_by] ?? null;

        if (! is_null($data->denied_billing_at)) {
            return '<span type="button" class="btn btn-sm btn-danger show-document"
                     data-wenk-pos="top"
                     data-id="'.$encript.'"
                     data-wenk="'.e($data->notes).'"
                     data-wenk-color="red"
                     data-toggle="modal"
                     data-target="#FormTTE">
                    <i class="far fa-times-circle"></i> Billing Ditolak
                </span>';
        }

        if (is_null($data->rejected_by)) {

            if (! is_null($data->finished_at)) {
                return '<span type="button" class="btn btn-sm btn-success show-document"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Selesai Pencairan"
                         data-wenk-color="green"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-check-double"></i> Selesai
                    </span>';
            }

            $inStatus = ($id == 4) ? isset($data->status) : in_array($id, $statusArr);

            if (! $inStatus && in_array($viewerJabatanId, [2, 3])) {
                return '<span type="button" class="btn btn-sm btn-warning show-document"
                         data-status="'.($status ? 1 : 0).'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="Belum Tanda Tangan"
                         data-wenk-color="orange"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-file-signature"></i> Belum Tanda Tangan
                    </span>';
            }

            if ($inStatus) {
                return '<span type="button" class="btn btn-sm btn-success show-document"
                         data-status="'.($status ? 1 : 0).'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Tanda Tangan"
                         data-wenk-color="green"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-file-contract"></i> Sudah Tanda Tangan
                    </span>';
            }

            if (! in_array($viewerJabatanId, [2, 3])) {
                return '<span type="button" class="btn btn-sm btn-info show-document"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="Tampilkan Dokumen"
                         data-wenk-color="blue"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fa-solid fa-eye"></i> Tampilkan
                    </span>';
            }

            return '<span type="button" class="btn btn-sm btn-dark"
                     data-status=""
                     data-wenk-pos="top"
                     data-wenk="Data Error, Hub Developer"
                     data-wenk-color="red">
                    <i class="fas fa-exclamation-triangle"></i> Error Data
                </span>';
        }

        return '<span type="button" class="btn btn-sm btn-danger show-document"
                 data-wenk-pos="top"
                 data-id="'.$encript.'"
                 data-wenk="'.e($data->notes).'"
                 data-wenk-color="red"
                 data-toggle="modal"
                 data-target="#FormTTE">
                <i class="far fa-file-excel"></i> Dokumen Ditolak '.$rejectedBy.'
            </span>';
    }

    protected function auditorStatusBadge(object $data, array $jabatanNameMap): string
    {
        $rejectedBy = $jabatanNameMap[$data->rejected_by] ?? null;

        $badge = static function (string $class, string $icon, string $label): string {
            return sprintf(
                '<span class="btn btn-sm %s disabled" aria-disabled="true"><i class="%s"></i> %s</span>',
                $class,
                $icon,
                e($label)
            );
        };

        if (! is_null($data->denied_billing_at)) {
            return $badge('btn-danger', 'far fa-times-circle', 'Billing Ditolak');
        }

        if (! is_null($data->rejected_by)) {
            $label = $rejectedBy
                ? 'Dokumen Ditolak '.$rejectedBy
                : 'Dokumen Ditolak';

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if (! is_null($data->finished_at)) {
            return $badge('btn-success', 'fas fa-check-double', 'Selesai');
        }

        if (! empty($data->status)) {
            return $badge('btn-success', 'fas fa-file-contract', 'Sudah Tanda Tangan');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum Tanda Tangan');
    }

    // ######################################################################################################################################################
    public function index()
    {
        return view('Payment.LS.sp2d');
    }

    public function json(ActivePositionService $activePosition)
    {
        $startedAt = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_ls')->warning('SP2D LS json blocked: invalid active position', [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }
            $jabatanId = $user->jabatan->id;
            $jabatanNameMap = Jabatan::query()->pluck('nama', 'id')->toArray();
            $userIds = in_array((int) $jabatanId, [2, 3], true)
                ? $this->positionIdentityResolver->equivalentIds(
                    $this->positionIdentityResolver->budActorPosition($user),
                )
                : [];

            Log::channel('payment_ls')->debug('SP2D LS json request', [
                'has_acting_bud' => $user->relationLoaded('actingBudUserPosition'),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            $dataQuery = LS::rootQuery()
                ->tap(fn ($q) => LS::withSp2d($q))
                ->leftJoin('user_positions as user_pos_to', function ($join) {
                    $join->on('user_pos_to.id', '=', 'document.users_to');
                })
                ->leftJoin('users as users_to_data', function ($join) {
                    $join->on('users_to_data.id', '=', 'user_pos_to.user_id')
                        ->whereNull('users_to_data.deleted_at');
                })
                ->select([
                    'document.id',
                    'document.reference_id',
                    'document.updated_at',
                    'document.nomor',
                    'document.uraian',
                    'document.nominal',
                    'document.status',
                    'document.rejected_by',
                    'document.denied_billing_at',
                    'document.notes',
                    'document.finished_at',
                    'document.users_to',
                    'unit_kerjas_sp2d.nama as unit_kerja',
                    'users_to_data.nama as user_name',
                ])
                ->when(in_array($jabatanId, [2, 3]), function ($q) use ($userIds) {
                    $q->whereIn('document.users_to', $userIds);
                });

            $btn = static function (
                string $class,
                string $icon,
                string $title,
                string $value,
                string $extra = ''
            ): string {
                return sprintf(
                    '<button value="%s" class="btn p-2 m-0 %s" title="%s" %s>
            <i class="%s fa-lg"></i>
        </button>',
                    $value,
                    $class,
                    $title,
                    $extra,
                    $icon
                );
            };

            $response = DataTables::of($dataQuery)
                ->addIndexColumn()
                ->addColumn('status', function ($row) use ($jabatanId, $jabatanNameMap) {
                    if ((int) $jabatanId === 13) {
                        return $this->auditorStatusBadge($row, $jabatanNameMap);
                    }

                    if (! $jabatanId) {
                        return '<span class="btn btn-sm btn-danger">
                        <i class="far fa-file-excel"></i> Error Data
                    </span>';
                    }

                    return $this->ButtonStatus($jabatanId, $row, $jabatanNameMap, $jabatanId);
                })

                ->addColumn('action', function ($row) use ($jabatanId, $btn) {

                    $encSpp = EncryptedId::encode($row->reference_id);
                    $encSp2d = EncryptedId::encode($row->id);

                    $actions = [];

                    switch ($jabatanId) {
                        case 13:
                            $actions[] = $btn(
                                'show-document',
                                'fa-solid fa-eye text-primary',
                                'Detail Dokumen',
                                $encSpp,
                                'data-id="'.$encSpp.'"'
                            );
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                            break;

                        case 1:
                        case 2:
                        case 3:
                            if (! $row->rejected_by) {
                                $actions[] = $btn(
                                    'denied',
                                    'far fa-file-excel text-danger',
                                    'Menolak Data',
                                    $encSp2d,
                                    'data-payment="LS" data-type="SP2D" '
                                );
                            }
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                            break;
                        case 4:
                            if (! $row->status || $row->rejected_by) {
                                $actions[] = $btn(
                                    'edit_data',
                                    'fas fa-user-cog text-primary',
                                    'Edit',
                                    $encSp2d
                                );
                                $actions[] = $btn(
                                    'delete',
                                    'fas fa-trash text-danger',
                                    'Menolak Data',
                                    $encSp2d,
                                    'data-payment="LS" data-type="SP2D" '
                                );
                            }

                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                            break;

                        case 6:
                        case 5:
                        case 7:
                        case 8:
                        case 9:
                        case 10:
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                            break;
                    }

                    return implode('', $actions);
                })

            /* ================= FILTER ================= */
                ->filterColumn('unit_kerja', function ($query, $keyword) {
                    $query->where('unit_kerjas_sp2d.nama', 'like', "%{$keyword}%");
                })
                ->filterColumn('user_name', function ($query, $keyword) {
                    $query->where('users_to_data.nama', 'like', "%{$keyword}%");
                })
                ->rawColumns(['action', 'status'])
                ->make(true);

            Log::channel('payment_ls')->debug('SP2D LS json success', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_ls')->error('SP2D LS json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data SP2D.',
            ], 500);
        }
    }

    public function store(
        StoreSp2dRequest $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $startedAt = microtime(true);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            Log::channel('payment_ls')->warning('SP2D LS store blocked: invalid active position', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }
        $userId = $user->id;
        $unitKerja = $request->unitKerjaId();
        $referenceId = $request->referenceId();
        $userTo = $request->userTo();

        $files = ['sp2d'];
        $storedFiles = [];

        Log::channel('payment_ls')->info('SP2D LS store request', [
            'reference_id' => $referenceId,
            'user_to' => $userTo,
            'has_file' => $request->hasFile('file_sp2d'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        try {
            DB::transaction(function () use (
                $request,
                $files,
                $referenceId,
                $userId,
                $userTo,
                $unitKerja,
                &$storedFiles,
                $documentHistoryService
            ) {
                foreach ($files as $type) {
                    $fileInput = 'file_'.$type;
                    $nomorInput = 'nomor_'.$type;

                    if (! $request->hasFile($fileInput)) {
                        continue;
                    }

                    $uploadedFile = $request->file($fileInput);
                    $srcType = strtoupper($type);
                    $path = '/File_'.$srcType;

                    $filename = $this->storeFile($uploadedFile, $path, $storedFiles);

                    $document = $this->saveDocumentData([
                        'nomor' => $request->input($nomorInput),
                        'src_name' => $filename,
                        'src_type' => $srcType,
                        'payment_type' => 'LS',
                        'reference_id' => $referenceId,
                        'id_unit_kerja' => $unitKerja,
                        'uploaded_by' => $userId,
                        'uraian' => $request->uraian,
                        'nominal' => $request->nominal,
                        'rekening' => $request->rekening,
                        'users_to' => $userTo,
                        'created_at' => now(),
                    ]);

                    $documentHistoryService->upload(
                        $document->id,
                        $filename,
                        $unitKerja
                    );
                }
            });

            Log::channel('payment_ls')->info('SP2D LS store success', [
                'reference_id' => $referenceId,
                'user_to' => $userTo,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data Tersimpan...',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles ?? []);

            Log::channel('payment_ls')->error('SP2D LS store failed', [
                'reference_id' => $referenceId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal menyimpan data',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $startedAt = microtime(true);

        Log::channel('payment_ls')->debug('SP2D LS edit request', [
            'hash' => $request->id,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
        try {
            $id = EncryptedId::decode($request->id);
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_ls')->warning('SP2D LS edit blocked: invalid active position', [
                    'hash' => $request->id,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $query = LS::rootQuery()
                ->tap(fn ($q) => LS::withSp2d($q))
                ->where('document.id', $id);

            $result = $query->first();

            if (! $result) {
                Log::channel('payment_ls')->warning('SP2D LS edit not found', [
                    'doc_id' => $id,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'status' => 404,
                    'message' => 'Dokumen SP2D tidak ditemukan',
                ], 404);
            }

            $result->user = $result->users_to
                ? EncryptedId::encode((int) $result->users_to)
                : null;

            Log::channel('payment_ls')->debug('SP2D LS edit success', [
                'doc_id' => $id,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'status' => 200,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel('payment_ls')->error('SP2D LS edit failed', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Permintaan tidak valid',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function update(
        UpdateSp2dRequest $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $startedAt = microtime(true);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            Log::channel('payment_ls')->warning('SP2D LS update blocked: invalid active position', [
                'hash' => $request->route('id'),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }
        $userId = $user->id;

        $sp2dId = $request->sp2dId();
        $referenceId = $request->referenceId();
        $unitKerjaId = $request->unitKerjaId();
        $userTo = $request->userTo();

        Log::channel('payment_ls')->info('SP2D LS update request', [
            'hash' => $request->route('id'),
            'sp2d_id' => $sp2dId,
            'reference_id' => $referenceId,
            'spm_unit_kerja_id' => $unitKerjaId,
            'user_to' => $userTo,
            'has_file' => $request->hasFile('file_sp2d'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $sp2d = $request->sp2dData();

        if (! $sp2d) {
            return response()->json([
                'status' => 404,
                'message' => 'Data SP2D tidak ditemukan',
            ], 404);
        }

        $basePayload = [
            'reference_id' => $referenceId,
            'id_unit_kerja' => $unitKerjaId,
            'uraian' => $request->input('uraian'),
            'nominal' => $request->input('nominal'),
            'rekening' => $request->input('rekening'),
            'nomor' => $request->input('nomor_sp2d'),
            'users_to' => $userTo,
            'rejected_by' => null,
            'notes' => null,
            'assigned_to' => null,
            'submit' => null,
            'updated_at' => now(),
        ];

        try {
            $storedFiles = [];
            DB::transaction(function () use (
                $request,
                $sp2dId,
                $userId,
                $basePayload,
                &$storedFiles,
                $documentHistoryService
            ) {

                if (! $request->hasFile('file_sp2d')) {
                    $document = $this->updateDocumentData(
                        $sp2dId,
                        $basePayload
                    );

                    if ($document) {
                        $documentHistoryService->edited(
                            $document->id,
                            $document->src_name
                        );
                    }

                    return;
                }

                $filename = $this->storeFile(
                    $request->file('file_sp2d'),
                    '/File_SP2D',
                    $storedFiles
                );

                $document = $this->updateDocumentData(
                    $sp2dId,
                    array_merge($basePayload, [
                        'src_name' => $filename,
                        'uploaded_by' => $userId,
                        'status' => null,
                    ])
                );

                if ($document) {
                    $documentHistoryService->edited(
                        $document->id,
                        $filename
                    );
                }
            });

            Log::channel('payment_ls')->info('SP2D LS update success', [
                'sp2d_id' => $sp2dId,
                'reference_id' => $referenceId,
                'spm_unit_kerja_id' => $unitKerjaId,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data SP2D berhasil diperbarui',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles ?? []);

            Log::channel('payment_ls')->error('SP2D LS update failed', [
                'sp2d_id' => $sp2dId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal memperbarui data SP2D',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 400);
        }
    }

    // ######################################################################################################################################################
    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $startedAt = microtime(true);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            Log::channel('payment_ls')->warning('SP2D LS formJson blocked: invalid active position', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }
        $isEdited = $request->edited === 'true';

        try {
            $idSp2d = $request->data ? EncryptedId::decode($request->data) : null;
        } catch (\Throwable) {
            Log::channel('payment_ls')->warning('SP2D LS formJson invalid data hash', [
                'data' => $request->data,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter data tidak valid.',
            ], 400);
        }

        Log::channel('payment_ls')->debug('SP2D LS formJson request', [
            'edited' => $request->edited,
            'data' => $request->data,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $referenceId = Document::where('id', $idSp2d)
            ->whereNull('deleted_at')
            ->where('src_type', 'SP2D')
            ->value('reference_id');

        $dataQuery = LS::rootQueryAlias('spm')
            ->where('spm.src_type', 'SPM')
            ->where('spm.payment_type', 'LS')
            ->join('unit_kerjas as unit_kerjas_spm', 'unit_kerjas_spm.id', '=', 'spm.id_unit_kerja')
            ->leftJoin('document as spp', function ($join) {
                $join->on('spp.id', '=', 'spm.reference_id')
                    ->where('spp.src_type', 'SPP')
                    ->where('spp.payment_type', 'LS')
                    ->whereNull('spp.deleted_at');
            })
            ->leftJoin('unit_kerjas as unit_kerja_spp', 'unit_kerja_spp.id', '=', 'spp.id_unit_kerja')
            ->select([
                'spm.id',
                'spm.reference_id',
                'spm.created_at',
                'spm.nomor',
                'spm.src_name',
                'spm.rejected_by',
                'spm.verify',
                'spp.id_unit_kerja',
                'unit_kerja_spp.nama as unit_kerja',
            ])
            ->where('spm.verify', 1)
            ->whereNull('spm.rejected_by')
            ->where(function ($q) use ($isEdited, $referenceId) {
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('document as sp2d')
                        ->whereColumn('sp2d.reference_id', 'spm.reference_id')
                        ->where('sp2d.src_type', 'SP2D')
                        ->where('sp2d.payment_type', 'LS')
                        ->whereNull('sp2d.deleted_at');
                });

                if ($isEdited && $referenceId) {
                    $q->orWhere('spm.reference_id', $referenceId);
                }
            })

            ->orderByDesc('spm.created_at');

        $response = DataTables::of($dataQuery)
            ->addIndexColumn()

            ->addColumn('status', function ($data) {
                $id = EncryptedId::encode($data->reference_id);

                $html = '
                <span class="btn btn-sm btn-info show-document"
                    data-url="/File_SPM/signs/'.$data->src_name.'"
                    data-files="'.$data->src_name.'"
                    data-id="'.$id.'"
                    data-wenk="Klik untuk menampilan dokumen"
                    data-wenk-color="blue"
                    data-toggle="modal"
                    data-target="#FormTTE">
                    <i class="fa-solid fa-eye"></i> Tampilkan
                </span>
            ';

                if ($data->rejected_by) {
                    $html .= '<span class="btn btn-sm btn-danger ms-1">Ditolak</span>';
                }

                return $html;
            })

            ->addColumn('action', function ($data) use ($request, $referenceId) {
                $id = EncryptedId::encode($data->id);
                $checked = ($request->edited === 'true' && $referenceId == $data->reference_id) ? 'checked' : '';

                $deniedButton = $checked ? '' : '
                        <button
                            type="button"
                            class="btn btn-outline-danger denied"
                            value="'.$id.'"
                            data-wenk="Menolak data"
                            data-payment="LS"
                            data-type="SPM"
                            data-wenk-color="red">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                ';

                return '
                <div class="d-flex justify-content-center">
                    <div class="btn-group btn-group-sm">

                        <input type="radio"
                            class="btn-check"
                            name="selected_spm"
                            id="spp_'.$id.'"
                            value="'.$id.'"
                            '.$checked.'>

                        <label class="btn btn-outline-primary"
                            for="spp_'.$id.'"
                            data-wenk="Pilih data"
                            data-wenk-color="green">
                            <i class="fa-solid fa-check"></i>
                        </label>
                        '.$deniedButton.'
                    </div>
                </div>
            ';
            })

            ->filterColumn('unit_kerja', function ($query, $keyword) {
                $query->where('unit_kerja_spp.nama', 'like', "%{$keyword}%");
            })

            ->rawColumns(['status', 'action'])
            ->make(true);

        Log::channel('payment_ls')->debug('SP2D LS formJson success', [
            'edited' => $isEdited,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response;
    }
}
