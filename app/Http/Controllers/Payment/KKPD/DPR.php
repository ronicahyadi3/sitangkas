<?php

namespace App\Http\Controllers\Payment\KKPD;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\KKPD;
use App\Models\UnitKerja;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class DPR extends Controller
{
    private const SRC_TYPE_DPR = 'DPR';

    private const SRC_TYPE_NPD = 'NPD';

    private const SRC_TYPE_DPT = 'DPT';

    private const FILE_DIR_DPR = '/File_DPR';

    private const FILE_DIR_NPD = '/File_NPD';

    private const FILE_DIR_DPT = '/File_DPT';

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

        if ($this->isRejectedRow($row)) {
            return $badge('btn-danger', 'far fa-file-excel', 'Ditolak');
        }

        $submitDpr = $this->csvToArray($row->submit_dpr);
        $statusDpr = $this->csvToArray($row->status_dpr);
        $statusNpd = $this->csvToArray($row->status_npd);
        $statusDpt = $this->csvToArray($row->status_dpt);

        if (! empty($submitDpr)) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if (! empty($statusDpr) || ! empty($statusNpd) || ! empty($statusDpt)) {
            return $badge('btn-success', 'fas fa-file-contract', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function index()
    {
        Log::channel(self::LOG_CHANNEL)->debug('DPR KKPD index accessed');

        return view('Payment.KKPD.dpr');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD json blocked: invalid active position', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            $actorId = $this->resolveActorUserId($user);

            if (! in_array($jabatanId, [5, 6, 8, 9, 13], true)) {
                Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD json blocked: forbidden role', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return DataTables::of(collect())->make(true);
            }

            $submitExpr = "REPLACE(COALESCE(dpr.submit,''), ' ', '')";

            Log::channel(self::LOG_CHANNEL)->debug('DPR KKPD json request');

            $query = KKPD::rootQueryAlias('dpr')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'dpr.id_unit_kerja')
                ->leftJoin('document as npd', function ($join) {
                    $join->on('npd.reference_id', '=', 'dpr.id')
                        ->where('npd.src_type', self::SRC_TYPE_NPD)
                        ->where('npd.payment_type', KKPD::PAYMENT_TYPE)
                        ->whereNull('npd.deleted_at');
                })
                ->leftJoin('document as dpt', function ($join) {
                    $join->on('dpt.reference_id', '=', 'dpr.id')
                        ->where('dpt.src_type', self::SRC_TYPE_DPT)
                        ->where('dpt.payment_type', KKPD::PAYMENT_TYPE)
                        ->whereNull('dpt.deleted_at');
                })
                ->where('dpr.src_type', self::SRC_TYPE_DPR)
                ->where('dpr.payment_type', KKPD::PAYMENT_TYPE)
                ->when(true, function ($q) use ($unitKerjaId, $jabatanId, $actorId, $submitExpr) {
                    if ($jabatanId === 13) {
                        return;
                    }

                    if ($jabatanId === 9) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where('dpr.id_unit_kerja', $unitKerjaId)
                                ->orWhere('uk.skpd_id', $unitKerjaId);
                        })->where(function ($scope) use ($submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('5', {$submitExpr})")
                                ->orWhereRaw("FIND_IN_SET('6', {$submitExpr})");
                        });

                        return;
                    }

                    $q->where('dpr.id_unit_kerja', $unitKerjaId);

                    if ($jabatanId === 8) {
                        $q->where('dpr.uploaded_by', $actorId);
                    }
                })
                ->when(in_array($jabatanId, [5, 6], true), function ($q) use ($submitExpr) {
                    $q->whereRaw("FIND_IN_SET('8', {$submitExpr})");
                })
                ->select([
                    'dpr.id',
                    'dpr.nomor as nomor_dpr',
                    'dpr.src_name as src_name_dpr',
                    'dpr.status as status_dpr',
                    'dpr.submit as submit_dpr',
                    'dpr.assigned_to as assigned_to_dpr',
                    'dpr.rejected_by as rejected_by_dpr',
                    'dpr.notes as notes_dpr',
                    'dpr.created_at',
                    'uk.nama as unit_kerja',
                    'npd.id as npd_id',
                    'npd.src_name as src_name_npd',
                    'npd.status as status_npd',
                    'npd.submit as submit_npd',
                    'npd.assigned_to as assigned_to_npd',
                    'npd.rejected_by as rejected_by_npd',
                    'npd.notes as notes_npd',
                    'dpt.id as dpt_id',
                    'dpt.nomor as nomor_dpt',
                    'dpt.src_name as src_name_dpt',
                    'dpt.status as status_dpt',
                    'dpt.submit as submit_dpt',
                    'dpt.assigned_to as assigned_to_dpt',
                    'dpt.rejected_by as rejected_by_dpt',
                    'dpt.notes as notes_dpt',
                ])
                ->orderByDesc('dpr.created_at');

            $btn = static function (
                string $value,
                string $class,
                string $icon,
                string $title,
                string $extra = ''
            ): string {
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

                    return $this->renderStatusButton($row, $jabatanId);
                })
                ->addColumn('action', function ($row) use ($jabatanId, $btn) {
                    $enc = EncryptedId::encode((int) $row->id);
                    $actions = [];
                    $submitDpr = $this->csvToArray($row->submit_dpr);
                    $statusDpr = $this->csvToArray($row->status_dpr);
                    $statusNpd = $this->csvToArray($row->status_npd);
                    $statusDpt = $this->csvToArray($row->status_dpt);
                    $submittedByPptk = in_array('8', $submitDpr, true);
                    $submittedByViewer = in_array((string) $jabatanId, $submitDpr, true);
                    $isRejected = $this->isRejectedRow($row);

                    if ($jabatanId === 13) {
                        $actions[] = $btn($enc, 'show-document', 'fa-solid fa-eye text-primary', 'Detail Dokumen', 'data-id="'.$enc.'"');
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if ($isRejected && $jabatanId === 8) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="KKPD" data-type="DPR"'
                        );
                    } elseif ($jabatanId === 8 && ! $submittedByPptk) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="KKPD" data-type="DPR"'
                        );
                    }

                    if (
                        $jabatanId === 8 &&
                        ! $isRejected &&
                        ! $submittedByPptk &&
                        in_array('8', $statusDpr, true) &&
                        in_array('8', $statusNpd, true)
                    ) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if (
                        in_array($jabatanId, [5, 6], true) &&
                        ! $isRejected &&
                        $submittedByPptk &&
                        ! $submittedByViewer
                    ) {
                        if (
                            in_array((string) $jabatanId, $statusNpd, true) &&
                            in_array((string) $jabatanId, $statusDpt, true)
                        ) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }

                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="KKPD" data-type="DPR"'
                        );
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel(self::LOG_CHANNEL)->debug('DPR KKPD json success', [
                'duration_ms' => $this->durationMs($start),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('DPR KKPD json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data DPR.',
            ], 500);
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('DPR KKPD store request', [
            'nomor_dpr' => $request->input('nomor_dpr'),
            'nomor_dpt' => $request->input('nomor_dpt'),
            'has_file_dpr' => $request->hasFile('file_dpr'),
            'has_file_npd' => $request->hasFile('file_npd'),
            'has_file_dpt' => $request->hasFile('file_dpt'),
        ]);

        $request->validate([
            'nomor_dpr' => ['required', 'string', 'max:255'],
            'file_dpr' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_npd' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'nomor_dpt' => ['required', 'string', 'max:255'],
            'file_dpt' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD store blocked: invalid active position', [
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD store blocked: forbidden role', [
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang membuat data DPR.',
            ], 403);
        }

        $actorId = $this->resolveActorUserId($user);
        $storedFiles = [];
        $createdDprId = null;
        $createdNpdId = null;
        $createdDptId = null;

        try {
            DB::transaction(function () use (
                $request,
                $user,
                $actorId,
                &$storedFiles,
                $documentHistoryService,
                &$createdDprId,
                &$createdNpdId,
                &$createdDptId
            ) {
                $dprFilename = $this->storeFile($request->file('file_dpr'), self::FILE_DIR_DPR, $storedFiles);
                $npdFilename = $this->storeFile($request->file('file_npd'), self::FILE_DIR_NPD, $storedFiles);
                $dptFilename = $this->storeFile($request->file('file_dpt'), self::FILE_DIR_DPT, $storedFiles);

                $dpr = Document::create([
                    'nomor' => $request->input('nomor_dpr'),
                    'src_name' => $dprFilename,
                    'src_type' => self::SRC_TYPE_DPR,
                    'payment_type' => KKPD::PAYMENT_TYPE,
                    'id_unit_kerja' => $user->unitKerja->id,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '8',
                    'users_to' => $actorId,
                    'created_at' => now(),
                ]);
                $createdDprId = $dpr->id;

                $npd = Document::create([
                    'src_name' => $npdFilename,
                    'src_type' => self::SRC_TYPE_NPD,
                    'payment_type' => KKPD::PAYMENT_TYPE,
                    'reference_id' => $dpr->id,
                    'parent_id' => $dpr->id,
                    'id_unit_kerja' => $user->unitKerja->id,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '8',
                    'users_to' => $actorId,
                    'created_at' => now(),
                ]);
                $createdNpdId = $npd->id;

                $dpt = Document::create([
                    'nomor' => $request->input('nomor_dpt'),
                    'src_name' => $dptFilename,
                    'src_type' => self::SRC_TYPE_DPT,
                    'payment_type' => KKPD::PAYMENT_TYPE,
                    'reference_id' => $dpr->id,
                    'parent_id' => $dpr->id,
                    'id_unit_kerja' => $user->unitKerja->id,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '8',
                    'users_to' => $actorId,
                    'created_at' => now(),
                ]);
                $createdDptId = $dpt->id;

                $documentHistoryService->upload($dpr->id, $dprFilename, $user->unitKerja->id);
                $documentHistoryService->upload($npd->id, $npdFilename, $user->unitKerja->id);
                $documentHistoryService->upload($dpt->id, $dptFilename, $user->unitKerja->id);
            });

            Log::channel(self::LOG_CHANNEL)->info('DPR KKPD store success', [
                'dpr_id' => $createdDprId,
                'npd_id' => $createdNpdId,
                'dpt_id' => $createdDptId,
                'nomor_dpr' => $request->input('nomor_dpr'),
                'nomor_dpt' => $request->input('nomor_dpt'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data DPR berhasil disimpan.',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('DPR KKPD store failed', [
                'nomor_dpr' => $request->input('nomor_dpr'),
                'nomor_dpt' => $request->input('nomor_dpt'),
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal menyimpan data DPR.',
            ], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->debug('DPR KKPD edit request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD edit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD edit blocked: invalid active position', [
                'dpr_id' => $id ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD edit blocked: forbidden role', [
                'dpr_id' => $id ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah data DPR.',
            ], 403);
        }

        $dpr = $this->findDprForCrud($id);
        if (! $dpr) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD edit not found', [
                'dpr_id' => $id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Data DPR tidak ditemukan.',
            ], 404);
        }

        if (! $this->isOwnerUploader($dpr, $user)) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD edit blocked: not owner uploader', [
                'dpr_id' => $dpr->id,
                'document_unit_kerja_id' => $dpr->id_unit_kerja,
                'document_uploaded_by' => $dpr->uploaded_by,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Data DPR hanya dapat diubah oleh pembuat dokumen.',
            ], 403);
        }

        $relatedDocs = Document::query()
            ->where('payment_type', KKPD::PAYMENT_TYPE)
            ->where('reference_id', $dpr->id)
            ->whereIn('src_type', [self::SRC_TYPE_NPD, self::SRC_TYPE_DPT])
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('src_type');

        Log::channel(self::LOG_CHANNEL)->debug('DPR KKPD edit success', [
            'dpr_id' => $dpr->id,
            'npd_id' => $relatedDocs->get(self::SRC_TYPE_NPD)?->id,
            'dpt_id' => $relatedDocs->get(self::SRC_TYPE_DPT)?->id,
            'duration_ms' => $this->durationMs($start),
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'dpr' => $dpr,
                'npd' => $relatedDocs->get(self::SRC_TYPE_NPD),
                'dpt' => $relatedDocs->get(self::SRC_TYPE_DPT),
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

        Log::channel(self::LOG_CHANNEL)->info('DPR KKPD update request', [
            'hash' => $id,
            'nomor_dpr' => $request->input('nomor_dpr'),
            'nomor_dpt' => $request->input('nomor_dpt'),
            'has_file_dpr' => $request->hasFile('file_dpr'),
            'has_file_npd' => $request->hasFile('file_npd'),
            'has_file_dpt' => $request->hasFile('file_dpt'),
        ]);

        $request->validate([
            'nomor_dpr' => ['required', 'string', 'max:255'],
            'file_dpr' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_npd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'nomor_dpt' => ['required', 'string', 'max:255'],
            'file_dpt' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $documentId = EncryptedId::decode($id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD update invalid hash', [
                'hash' => $id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD update blocked: invalid active position', [
                'dpr_id' => $documentId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD update blocked: forbidden role', [
                'dpr_id' => $documentId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah data DPR.',
            ], 403);
        }

        $dpr = $this->findDprForCrud($documentId);
        if (! $dpr) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD update not found', [
                'dpr_id' => $documentId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Data DPR tidak ditemukan.',
            ], 404);
        }

        if (! $this->isOwnerUploader($dpr, $user)) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD update blocked: not owner uploader', [
                'dpr_id' => $dpr->id,
                'document_unit_kerja_id' => $dpr->id_unit_kerja,
                'document_uploaded_by' => $dpr->uploaded_by,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Data DPR hanya dapat diubah oleh pembuat dokumen.',
            ], 403);
        }

        $actorId = $this->resolveActorUserId($user);
        $storedFiles = [];

        try {
            DB::transaction(function () use (
                $request,
                $dpr,
                $actorId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $relatedDocs = Document::query()
                    ->where('payment_type', KKPD::PAYMENT_TYPE)
                    ->where('reference_id', $dpr->id)
                    ->whereIn('src_type', [self::SRC_TYPE_NPD, self::SRC_TYPE_DPT])
                    ->whereNull('deleted_at')
                    ->get()
                    ->keyBy('src_type');

                $npd = $relatedDocs->get(self::SRC_TYPE_NPD);
                $dpt = $relatedDocs->get(self::SRC_TYPE_DPT);

                $dprPayload = [
                    'nomor' => $request->input('nomor_dpr'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'assigned_to' => '8',
                    'users_to' => $actorId,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_dpr')) {
                    $dprPayload['src_name'] = $this->storeFile($request->file('file_dpr'), self::FILE_DIR_DPR, $storedFiles);
                    $dprPayload['uploaded_by'] = $actorId;
                    $dprPayload['status'] = null;
                }

                $dpr->update($dprPayload);

                if ($npd) {
                    $npdPayload = [
                        'reference_id' => $dpr->id,
                        'parent_id' => $dpr->id,
                        'rejected_by' => null,
                        'notes' => null,
                        'submit' => null,
                        'assigned_to' => '8',
                        'users_to' => $actorId,
                        'updated_at' => now(),
                    ];

                    if ($request->hasFile('file_npd')) {
                        $npdPayload['src_name'] = $this->storeFile($request->file('file_npd'), self::FILE_DIR_NPD, $storedFiles);
                        $npdPayload['uploaded_by'] = $actorId;
                        $npdPayload['status'] = null;
                    }

                    $npd->update($npdPayload);
                } else {
                    if (! $request->hasFile('file_npd')) {
                        throw new \RuntimeException('File NPD wajib diunggah karena data NPD belum tersedia.');
                    }

                    $npd = Document::create([
                        'src_name' => $this->storeFile($request->file('file_npd'), self::FILE_DIR_NPD, $storedFiles),
                        'src_type' => self::SRC_TYPE_NPD,
                        'payment_type' => KKPD::PAYMENT_TYPE,
                        'reference_id' => $dpr->id,
                        'parent_id' => $dpr->id,
                        'id_unit_kerja' => $dpr->id_unit_kerja,
                        'uploaded_by' => $actorId,
                        'assigned_to' => '8',
                        'users_to' => $actorId,
                        'created_at' => now(),
                    ]);
                }

                if ($dpt) {
                    $dptPayload = [
                        'nomor' => $request->input('nomor_dpt'),
                        'reference_id' => $dpr->id,
                        'parent_id' => $dpr->id,
                        'rejected_by' => null,
                        'notes' => null,
                        'submit' => null,
                        'assigned_to' => '8',
                        'users_to' => $actorId,
                        'updated_at' => now(),
                    ];

                    if ($request->hasFile('file_dpt')) {
                        $dptPayload['src_name'] = $this->storeFile($request->file('file_dpt'), self::FILE_DIR_DPT, $storedFiles);
                        $dptPayload['uploaded_by'] = $actorId;
                        $dptPayload['status'] = null;
                    }

                    $dpt->update($dptPayload);
                } else {
                    if (! $request->hasFile('file_dpt')) {
                        throw new \RuntimeException('File DPT wajib diunggah karena data DPT belum tersedia.');
                    }

                    $dpt = Document::create([
                        'nomor' => $request->input('nomor_dpt'),
                        'src_name' => $this->storeFile($request->file('file_dpt'), self::FILE_DIR_DPT, $storedFiles),
                        'src_type' => self::SRC_TYPE_DPT,
                        'payment_type' => KKPD::PAYMENT_TYPE,
                        'reference_id' => $dpr->id,
                        'parent_id' => $dpr->id,
                        'id_unit_kerja' => $dpr->id_unit_kerja,
                        'uploaded_by' => $actorId,
                        'assigned_to' => '8',
                        'users_to' => $actorId,
                        'created_at' => now(),
                    ]);
                }

                $documentHistoryService->edited($dpr->id, (string) $dpr->src_name, $dpr->id_unit_kerja);
                $documentHistoryService->edited($npd->id, (string) $npd->src_name, $npd->id_unit_kerja);
                $documentHistoryService->edited($dpt->id, (string) $dpt->src_name, $dpt->id_unit_kerja);
            });

            Log::channel(self::LOG_CHANNEL)->info('DPR KKPD update success', [
                'dpr_id' => $documentId,
                'nomor_dpr' => $request->input('nomor_dpr'),
                'nomor_dpt' => $request->input('nomor_dpt'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data DPR berhasil diperbarui.',
            ]);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);

            Log::channel(self::LOG_CHANNEL)->info('DPR KKPD update blocked', [
                'dpr_id' => $documentId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('DPR KKPD update failed', [
                'dpr_id' => $documentId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal memperbarui data DPR.',
            ], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('DPR KKPD submit request', [
            'hash' => $request->id,
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD submit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD submit blocked: invalid active position', [
                'dpr_id' => $docId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        if (! in_array($jabatanId, [8, 5, 6], true)) {
            Log::channel(self::LOG_CHANNEL)->warning('DPR KKPD submit blocked: forbidden role', [
                'dpr_id' => $docId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang melakukan submit DPR.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($docId, $jabatanId, $unitKerjaId, $documentHistoryService) {
                $dpr = Document::query()
                    ->where('id', $docId)
                    ->where('src_type', self::SRC_TYPE_DPR)
                    ->where('payment_type', KKPD::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $dpr) {
                    throw new \RuntimeException('Dokumen DPR tidak ditemukan.');
                }

                if (! $this->canAccessForSubmit($dpr, $jabatanId, $unitKerjaId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini.');
                }

                $docs = Document::query()
                    ->where('payment_type', KKPD::PAYMENT_TYPE)
                    ->where(function ($q) use ($dpr) {
                        $q->where('id', $dpr->id)
                            ->orWhere('reference_id', $dpr->id);
                    })
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('src_type');

                $npd = $docs->get(self::SRC_TYPE_NPD);
                $dpt = $docs->get(self::SRC_TYPE_DPT);

                if (! $npd || ! $dpt) {
                    throw new \RuntimeException('Dokumen pendukung DPR belum lengkap.');
                }

                if ($this->isRejectedDocument($dpr) || $this->isRejectedDocument($npd) || $this->isRejectedDocument($dpt)) {
                    throw new \RuntimeException('Dokumen sudah ditolak.');
                }

                $submitArr = $this->csvToArray($dpr->submit);
                if (in_array((string) $jabatanId, $submitArr, true)) {
                    throw new \RuntimeException('Dokumen sudah disubmit oleh jabatan ini.');
                }

                if ($jabatanId === 8) {
                    if (
                        ! $this->hasStatusForJabatan($dpr->status, 8) ||
                        ! $this->hasStatusForJabatan($npd->status, 8)
                    ) {
                        throw new \RuntimeException('PPTK wajib TTE dokumen DPR dan NPD sebelum submit.');
                    }

                    $assignedTo = $this->nextFromPptk($dpr->id_unit_kerja);
                    $newSubmit = $dpr->submit ? $dpr->submit.',8' : '8';
                    $this->propagateSubmitState($docs, $newSubmit, $assignedTo);
                } else {
                    if (! in_array('8', $submitArr, true)) {
                        throw new \RuntimeException('Dokumen belum disubmit oleh PPTK.');
                    }

                    if (
                        ! $this->hasStatusForJabatan($npd->status, $jabatanId) ||
                        ! $this->hasStatusForJabatan($dpt->status, $jabatanId)
                    ) {
                        throw new \RuntimeException('PA/KPA wajib TTE dokumen NPD dan DPT sebelum submit.');
                    }

                    $assignedTo = '9';
                    $newSubmit = $dpr->submit ? $dpr->submit.','.$jabatanId : (string) $jabatanId;
                    $this->propagateSubmitState($docs, $newSubmit, $assignedTo);
                }

                foreach ($docs as $doc) {
                    $documentHistoryService->submit($doc->id, (string) $doc->src_name, $doc->id_unit_kerja);
                }
            });

            Log::channel(self::LOG_CHANNEL)->info('DPR KKPD submit success', [
                'dpr_id' => $docId,
                'assigned_to' => match ($jabatanId) {
                    8 => $this->nextFromPptk($unitKerjaId),
                    default => '9',
                },
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 200,
                'message' => match ($jabatanId) {
                    8 => 'DPR berhasil disubmit ke PA/KPA.',
                    5 => 'DPR berhasil disubmit ke BP.',
                    6 => 'DPR berhasil disubmit ke BP.',
                    default => 'DPR berhasil disubmit.',
                },
            ]);
        } catch (\RuntimeException $e) {
            Log::channel(self::LOG_CHANNEL)->info('DPR KKPD submit blocked', [
                'dpr_id' => $docId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('DPR KKPD submit failed', [
                'dpr_id' => $docId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan sistem.',
            ], 500);
        }
    }

    private function canCrud($user): bool
    {
        return $user
            && $user->jabatan
            && $user->unitKerja
            && (int) $user->jabatan->id === 8;
    }

    private function findDprForCrud(int $id): ?Document
    {
        return Document::query()
            ->where('id', $id)
            ->where('src_type', self::SRC_TYPE_DPR)
            ->where('payment_type', KKPD::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->first();
    }

    private function renderStatusButton($row, int $jabatanId): string
    {
        $enc = EncryptedId::encode((int) $row->id);
        $submitDpr = $this->csvToArray($row->submit_dpr);
        $statusDpr = $this->csvToArray($row->status_dpr);
        $statusNpd = $this->csvToArray($row->status_npd);
        $statusDpt = $this->csvToArray($row->status_dpt);
        $submittedByViewer = in_array((string) $jabatanId, $submitDpr, true);

        if ($this->isRejectedRow($row)) {
            $notes = (string) ($row->notes_dpt ?? $row->notes_npd ?? $row->notes_dpr ?? 'Dokumen ditolak');
            $rejectedBy = $row->rejected_by_dpt ?? $row->rejected_by_npd ?? $row->rejected_by_dpr;
            $label = $this->rejectedLabel((int) $rejectedBy);

            return '<span type="button" class="btn btn-sm btn-danger show-document"'
                .' data-id="'.$enc.'"'
                .' data-wenk="'.e($notes).'"'
                .' data-wenk-color="red">'
                .'<i class="far fa-file-excel"></i> '.e($label).'</span>';
        }

        if ($jabatanId === 8) {
            if ($submittedByViewer) {
                return '<span type="button" class="btn btn-sm btn-primary show-document"'
                    .' data-id="'.$enc.'"'
                    .' data-wenk="Telah Submit"'
                    .' data-wenk-color="blue">'
                    .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
            }

            if (in_array('8', $statusDpr, true) && in_array('8', $statusNpd, true)) {
                return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                    .' data-id="'.$enc.'"'
                    .' data-wenk="Belum Submit"'
                    .' data-wenk-color="blue">'
                    .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>';
            }

            return '<span type="button" class="btn btn-sm btn-warning show-document"'
                .' data-id="'.$enc.'"'
                .' data-wenk="Belum TTE"'
                .' data-wenk-color="orange">'
                .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
        }

        if (in_array($jabatanId, [5, 6], true)) {
            if (! $this->isAssignedTo($row->assigned_to_dpr, $jabatanId) && ! $submittedByViewer) {
                return '<span type="button" class="btn btn-sm btn-info show-document"'
                    .' data-id="'.$enc.'"'
                    .' data-wenk="Tampilkan Dokumen"'
                    .' data-wenk-color="blue">'
                    .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
            }

            if ($submittedByViewer) {
                return '<span type="button" class="btn btn-sm btn-primary show-document"'
                    .' data-id="'.$enc.'"'
                    .' data-wenk="Telah Submit"'
                    .' data-wenk-color="blue">'
                    .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
            }

            if (
                in_array((string) $jabatanId, $statusNpd, true) &&
                in_array((string) $jabatanId, $statusDpt, true)
            ) {
                return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                    .' data-id="'.$enc.'"'
                    .' data-wenk="Belum Submit"'
                    .' data-wenk-color="blue">'
                    .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>';
            }

            return '<span type="button" class="btn btn-sm btn-warning show-document"'
                .' data-id="'.$enc.'"'
                .' data-wenk="Belum TTE"'
                .' data-wenk-color="orange">'
                .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
        }

        return '<span type="button" class="btn btn-sm btn-info show-document"'
            .' data-id="'.$enc.'"'
            .' data-wenk="Tampilkan Dokumen"'
            .' data-wenk-color="blue">'
            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
    }

    private function nextFromPptk(int $unitKerjaId): string
    {
        $unit = UnitKerja::query()
            ->select(['id', 'skpd_id'])
            ->whereKey($unitKerjaId)
            ->first();

        if (! $unit) {
            return '5';
        }

        return ! empty($unit->skpd_id) ? '6' : '5';
    }

    private function canAccessForSubmit(Document $document, int $jabatanId, int $unitKerjaId): bool
    {
        if (in_array($jabatanId, [8, 5, 6], true)) {
            if ((int) $document->id_unit_kerja === $unitKerjaId) {
                return true;
            }

            return $this->isAssignedTo($document->assigned_to, $jabatanId);
        }

        return false;
    }

    private function isOwnerUploader(Document $document, $user): bool
    {
        return $user
            && $user->jabatan
            && $user->unitKerja
            && (int) $user->jabatan->id === 8
            && (int) $document->id_unit_kerja === (int) $user->unitKerja->id
            && (int) $document->uploaded_by === $this->resolveActorUserId($user);
    }

    private function propagateSubmitState($docs, string $submit, string $assignedTo): void
    {
        foreach ($docs as $doc) {
            $doc->update([
                'submit' => $submit,
                'assigned_to' => $assignedTo,
                'updated_at' => now(),
            ]);
        }
    }

    private function hasStatusForJabatan(?string $status, int $jabatanId): bool
    {
        return in_array((string) $jabatanId, $this->csvToArray($status), true);
    }

    private function isAssignedTo(?string $assignedTo, int $jabatanId): bool
    {
        return in_array((string) $jabatanId, $this->csvToArray($assignedTo), true);
    }

    private function isRejectedRow($row): bool
    {
        return ! is_null($row->rejected_by_dpr)
            || ! is_null($row->rejected_by_npd)
            || ! is_null($row->rejected_by_dpt);
    }

    private function isRejectedDocument(Document $document): bool
    {
        return ! is_null($document->rejected_by);
    }

    private function rejectedLabel(int $jabatanId): string
    {
        return match ($jabatanId) {
            5 => 'Ditolak PA',
            6 => 'Ditolak KPA',
            8 => 'Ditolak PPTK',
            9 => 'Ditolak BP',
            10 => 'Ditolak BPP',
            default => 'Ditolak',
        };
    }

    private function resolveActorUserId($user): int
    {
        return (int) (($user->actingPptkUser) ? $user->actingPptkUser->id : $user->id);
    }

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== ''));
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
