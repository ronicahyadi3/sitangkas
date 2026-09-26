<?php

namespace App\Http\Controllers\Payment\TU;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\TU as PaymentTU;
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
    private const PAYMENT_TYPE = 'TU';

    private const LOG_CHANNEL = 'payment_tu';

    private const CHILD_TYPES = ['STS', 'LPJ', 'TBP'];

    public function index()
    {
        return view('Payment.TU.lpj');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;

            $query = $this->buildPackageQuery()
                ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($jabatanId, $unitKerjaId) {
                    if (in_array($jabatanId, [9, 10], true)) {
                        $q->where('sp2d.id_unit_kerja', $unitKerjaId);

                        return;
                    }

                    if (in_array($jabatanId, [5, 6], true)) {
                        $q->where('sp2d.id_unit_kerja', $unitKerjaId)
                            ->where(function ($scope) use ($jabatanId) {
                                $scope->whereRaw("FIND_IN_SET(?, REPLACE(COALESCE(sts.assigned_to, ''), ' ', ''))", [(string) $jabatanId])
                                    ->orWhereRaw("FIND_IN_SET(?, REPLACE(COALESCE(lpj.assigned_to, ''), ' ', ''))", [(string) $jabatanId])
                                    ->orWhereRaw("FIND_IN_SET(?, REPLACE(COALESCE(tbp.assigned_to, ''), ' ', ''))", [(string) $jabatanId]);
                            });

                        return;
                    }

                    $q->whereRaw('1 = 0');
                });

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

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_button', function ($row) use ($jabatanId) {
                    return $this->renderPackageStatus($row, $jabatanId);
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $actions = [];
                    $referenceHash = EncryptedId::encode((int) $row->reference_id);
                    $sp2dHash = EncryptedId::encode((int) $row->sp2d_id);

                    if ($jabatanId === 13) {
                        $actions[] = $btn(
                            $referenceHash,
                            'manage_package',
                            'fas fa-folder-open text-primary',
                            'Lihat Dokumen Paket',
                            sprintf(
                                'data-reference="%s" data-sp2d="%s" data-unit="%s" data-sp2d-number="%s" data-spp-number="%s" data-flow="%s"',
                                $referenceHash,
                                $sp2dHash,
                                e((string) $row->unit_kerja),
                                e((string) ($row->nomor_sp2d ?? '-')),
                                e((string) ($row->nomor_spp ?? '-')),
                                e((string) $this->resolveFlowRole($row))
                            )
                        );
                        $actions[] = $btn($sp2dHash, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (in_array($jabatanId, [9, 10], true) && $this->canManageCrud($row, $jabatanId)) {
                        $actions[] = $btn(
                            $referenceHash,
                            'manage_package',
                            'fas fa-folder-open text-primary',
                            'Kelola Dokumen LPJ',
                            sprintf(
                                'data-reference="%s" data-sp2d="%s" data-unit="%s" data-sp2d-number="%s" data-spp-number="%s" data-flow="%s"',
                                $referenceHash,
                                $sp2dHash,
                                e((string) $row->unit_kerja),
                                e((string) ($row->nomor_sp2d ?? '-')),
                                e((string) ($row->nomor_spp ?? '-')),
                                e((string) $this->resolveFlowRole($row))
                            )
                        );
                    }

                    if ($this->canSubmitPackage($row, $jabatanId)) {
                        $actions[] = $btn($referenceHash, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if ($this->canDenyPackage($row, $jabatanId)) {
                        $actions[] = $btn($referenceHash, 'deny_package', 'far fa-file-excel text-danger', 'Tolak');
                    }

                    $actions[] = $btn($sp2dHash, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('LPJ TU json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data LPJ TU.',
            ], 500);
        }
    }

    public function documentsJson(
        Request $request,
        ActivePositionService $activePosition
    ) {
        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $referenceHash = (string) $request->query('reference', '');
            if (trim($referenceHash) === '') {
                return DataTables::of(
                    Document::query()
                        ->whereRaw('1 = 0')
                        ->select([
                            'id',
                            'src_type',
                            'nomor',
                            'src_name',
                            'status',
                            'submit',
                            'assigned_to',
                            'rejected_by',
                            'notes',
                            'finished_at',
                            'created_at',
                            'updated_at',
                            'id_unit_kerja',
                        ])
                )
                    ->addIndexColumn()
                    ->addColumn('status_button', fn () => '')
                    ->addColumn('action', fn () => '')
                    ->rawColumns(['status_button', 'action'])
                    ->make(true);
            }

            $referenceId = EncryptedId::decode($referenceHash);
            $jabatanId = (int) $user->jabatan->id;

            $package = $this->findPackageByReferenceId($referenceId);
            if (! $package || ! $this->canViewPackage($package, $jabatanId, (int) $user->unitKerja->id)) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Anda tidak memiliki akses ke paket LPJ ini.',
                ], 403);
            }

            $query = Document::query()
                ->where('payment_type', self::PAYMENT_TYPE)
                ->whereIn('src_type', self::CHILD_TYPES)
                ->where('reference_id', $referenceId)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->select([
                    'id',
                    'src_type',
                    'nomor',
                    'src_name',
                    'status',
                    'submit',
                    'assigned_to',
                    'rejected_by',
                    'notes',
                    'finished_at',
                    'created_at',
                    'updated_at',
                    'id_unit_kerja',
                ]);

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

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_button', function ($row) use ($package) {
                    return $this->renderChildStatus($row, $package);
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId, $user, $package) {
                    $hash = EncryptedId::encode((int) $row->id);
                    $actions = [];
                    $path = $this->directoryForType((string) $row->src_type);
                    $url = ! is_null($row->status)
                        ? $path.'/signs/'.$row->src_name
                        : $path.'/'.$row->src_name;

                    if ($jabatanId === 13) {
                        $actions[] = $btn(
                            $hash,
                            'show-document',
                            'fa-solid fa-eye text-primary',
                            'Detail Dokumen',
                            'data-id="'.$hash.'"'
                        );
                        $actions[] = $btn($hash, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (
                        in_array($jabatanId, [9, 10], true)
                        && $this->canManagePackageByRole($package, $jabatanId, (int) $user->unitKerja->id)
                        && $this->canEditChildDocument($row)
                    ) {
                        $actions[] = $btn($hash, 'edit_child', 'fas fa-edit text-primary', 'Edit');
                        $actions[] = $btn($hash, 'delete_child', 'fas fa-trash text-danger', 'Hapus');
                    }

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat dokumen LPJ.',
            ], 500);
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        return $this->persistChildDocument($request, null, $activePosition, $documentHistoryService);
    }

    public function edit(
        string $id,
        ActivePositionService $activePosition
    ) {
        try {
            $documentId = EncryptedId::decode($id);
            $user = $activePosition->get();
            $document = Document::query()->find($documentId);

            if (! $document || ! in_array($document->src_type, self::CHILD_TYPES, true) || $document->payment_type !== self::PAYMENT_TYPE) {
                return response()->json(['status' => 404, 'message' => 'Dokumen tidak ditemukan.'], 404);
            }

            if (! $user || ! $user->jabatan || ! $user->unitKerja || ! $this->canManageChildDocument($document, $user)) {
                return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah dokumen ini.'], 403);
            }

            return response()->json([
                'status' => 200,
                'data' => $document,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data dokumen LPJ.',
            ], 500);
        }
    }

    public function update(
        Request $request,
        string $id,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        return $this->persistChildDocument($request, $id, $activePosition, $documentHistoryService);
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        try {
            $referenceId = EncryptedId::decode((string) $request->id);
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $package = $this->findPackageByReferenceId($referenceId);
            if (
                ! $package ||
                ! $this->canViewPackage($package, $jabatanId, (int) $user->unitKerja->id) ||
                ! $this->canSubmitPackage($package, $jabatanId)
            ) {
                return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang submit paket LPJ ini.'], 403);
            }

            $latestDocs = $this->getLatestChildDocuments($referenceId);
            if ($latestDocs->isEmpty()) {
                return response()->json(['status' => 400, 'message' => 'Dokumen LPJ belum diunggah.'], 400);
            }

            if (! $this->isPackageReadyForSubmit($latestDocs, $jabatanId)) {
                return response()->json(['status' => 400, 'message' => 'Masih ada dokumen LPJ yang belum siap untuk disubmit.'], 400);
            }

            DB::transaction(function () use ($referenceId, $jabatanId, $documentHistoryService) {
                $allDocs = Document::query()
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereIn('src_type', self::CHILD_TYPES)
                    ->where('reference_id', $referenceId)
                    ->whereNull('deleted_at')
                    ->get();

                if (in_array($jabatanId, [9, 10], true)) {
                    $assignedTo = $jabatanId === 9 ? '5' : '6';
                    foreach ($allDocs as $doc) {
                        $doc->update([
                            'assigned_to' => $assignedTo,
                            'submit' => (string) $jabatanId,
                            'updated_at' => now(),
                        ]);
                        $documentHistoryService->submit($doc->id, $doc->src_name, $doc->id_unit_kerja);
                    }

                    return;
                }

                foreach ($allDocs as $doc) {
                    $doc->update([
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $documentHistoryService->submit($doc->id, $doc->src_name, $doc->id_unit_kerja);
                }
            });

            return response()->json([
                'status' => 200,
                'message' => 'Paket LPJ berhasil diproses.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal submit paket LPJ.',
            ], 500);
        }
    }

    public function deny(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        try {
            $referenceId = EncryptedId::decode((string) $request->id);
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            if (! in_array($jabatanId, [5, 6], true)) {
                return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang menolak paket LPJ ini.'], 403);
            }

            $notes = trim((string) $request->notes);
            if ($notes === '') {
                return response()->json(['status' => 422, 'message' => 'Catatan penolakan wajib diisi.'], 422);
            }

            $package = $this->findPackageByReferenceId($referenceId);
            if (
                ! $package ||
                ! $this->canViewPackage($package, $jabatanId, (int) $user->unitKerja->id) ||
                ! $this->canDenyPackage($package, $jabatanId)
            ) {
                return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang menolak paket LPJ ini.'], 403);
            }

            DB::transaction(function () use ($referenceId, $jabatanId, $notes, $documentHistoryService) {
                $docs = Document::query()
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereIn('src_type', self::CHILD_TYPES)
                    ->where('reference_id', $referenceId)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($docs as $doc) {
                    $doc->update([
                        'rejected_by' => $jabatanId,
                        'notes' => $notes,
                        'updated_at' => now(),
                    ]);
                    $documentHistoryService->reject($doc->id, $doc->src_name, $doc->id_unit_kerja);
                }
            });

            return response()->json([
                'status' => 200,
                'message' => 'Paket LPJ berhasil ditolak.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal menolak paket LPJ.',
            ], 500);
        }
    }

    public function destroy(
        string $id,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        try {
            $documentId = EncryptedId::decode($id);
            $user = $activePosition->get();
            $document = Document::query()->find($documentId);

            if (! $document || ! in_array($document->src_type, self::CHILD_TYPES, true) || $document->payment_type !== self::PAYMENT_TYPE) {
                return response()->json(['status' => 404, 'message' => 'Dokumen tidak ditemukan.'], 404);
            }

            if (! $user || ! $user->jabatan || ! $user->unitKerja || ! $this->canManageChildDocument($document, $user)) {
                return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang menghapus dokumen ini.'], 403);
            }

            if (! is_null($document->finished_at) || (! is_null($document->submit) && is_null($document->rejected_by))) {
                return response()->json(['status' => 409, 'message' => 'Dokumen yang sudah diproses tidak dapat dihapus.'], 409);
            }

            $filename = $document->src_name;
            $unitKerja = $document->id_unit_kerja;

            DB::transaction(function () use ($document, $documentId, $filename, $unitKerja, $documentHistoryService) {
                $document->delete();
                $documentHistoryService->delete($documentId, $filename, $unitKerja);
            });

            return response()->json([
                'status' => 200,
                'message' => 'Dokumen LPJ berhasil dihapus.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal menghapus dokumen LPJ.',
            ], 500);
        }
    }

    private function buildPackageQuery()
    {
        $latestSts = $this->latestChildDocumentSubquery('STS');
        $latestLpj = $this->latestChildDocumentSubquery('LPJ');
        $latestTbp = $this->latestChildDocumentSubquery('TBP');

        return PaymentTU::rootQueryAlias('sp2d')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'sp2d.id_unit_kerja')
            ->leftJoin('document as spp', function ($join) {
                $join->on('spp.id', '=', 'sp2d.reference_id')
                    ->where('spp.src_type', 'SPP')
                    ->where('spp.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('spp.deleted_at');
            })
            ->leftJoin('document as spm', function ($join) {
                $join->on('spm.reference_id', '=', 'sp2d.reference_id')
                    ->where('spm.src_type', 'SPM')
                    ->where('spm.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('spm.deleted_at');
            })
            ->leftJoinSub($latestSts, 'latest_sts', function ($join) {
                $join->on('latest_sts.reference_id', '=', 'sp2d.reference_id');
            })
            ->leftJoin('document as sts', 'sts.id', '=', 'latest_sts.latest_id')
            ->leftJoinSub($latestLpj, 'latest_lpj', function ($join) {
                $join->on('latest_lpj.reference_id', '=', 'sp2d.reference_id');
            })
            ->leftJoin('document as lpj', 'lpj.id', '=', 'latest_lpj.latest_id')
            ->leftJoinSub($latestTbp, 'latest_tbp', function ($join) {
                $join->on('latest_tbp.reference_id', '=', 'sp2d.reference_id');
            })
            ->leftJoin('document as tbp', 'tbp.id', '=', 'latest_tbp.latest_id')
            ->where('sp2d.src_type', 'SP2D')
            ->where('sp2d.payment_type', self::PAYMENT_TYPE)
            ->where(function ($scope) {
                $scope->whereNotNull('sp2d.finished_at')
                    ->orWhereNotNull('sts.id')
                    ->orWhereNotNull('lpj.id')
                    ->orWhereNotNull('tbp.id');
            })
            ->select([
                'sp2d.id as sp2d_id',
                'sp2d.reference_id',
                'sp2d.nomor as nomor_sp2d',
                'sp2d.src_name as src_name_sp2d',
                'sp2d.status as status_sp2d',
                'sp2d.finished_at as sp2d_finished_at',
                'sp2d.created_at',
                'sp2d.updated_at',
                'sp2d.id_unit_kerja',
                'uk.nama as unit_kerja',
                'spp.nomor as nomor_spp',
                'spp.submit as submit_spp',
                'spm.nomor as nomor_spm',
                'sts.id as sts_id',
                'sts.nomor as nomor_sts',
                'sts.src_name as src_name_sts',
                'sts.status as status_sts',
                'sts.submit as submit_sts',
                'sts.assigned_to as assigned_to_sts',
                'sts.rejected_by as rejected_by_sts',
                'sts.notes as notes_sts',
                'sts.finished_at as finished_at_sts',
                'lpj.id as lpj_id',
                'lpj.nomor as nomor_lpj',
                'lpj.src_name as src_name_lpj',
                'lpj.status as status_lpj',
                'lpj.submit as submit_lpj',
                'lpj.assigned_to as assigned_to_lpj',
                'lpj.rejected_by as rejected_by_lpj',
                'lpj.notes as notes_lpj',
                'lpj.finished_at as finished_at_lpj',
                'tbp.id as tbp_id',
                'tbp.nomor as nomor_tbp',
                'tbp.src_name as src_name_tbp',
                'tbp.status as status_tbp',
                'tbp.submit as submit_tbp',
                'tbp.assigned_to as assigned_to_tbp',
                'tbp.rejected_by as rejected_by_tbp',
                'tbp.notes as notes_tbp',
                'tbp.finished_at as finished_at_tbp',
            ])
            ->orderByDesc('sp2d.updated_at');
    }

    private function latestChildDocumentSubquery(string $srcType)
    {
        return DB::table('document as d')
            ->selectRaw('d.reference_id, MAX(d.id) as latest_id')
            ->where('d.payment_type', self::PAYMENT_TYPE)
            ->where('d.src_type', $srcType)
            ->whereNull('d.deleted_at')
            ->groupBy('d.reference_id');
    }

    private function findPackageByReferenceId(int $referenceId)
    {
        return $this->buildPackageQuery()
            ->where('sp2d.reference_id', $referenceId)
            ->first();
    }

    private function getLatestChildDocuments(int $referenceId)
    {
        $latestIds = [];
        foreach (self::CHILD_TYPES as $srcType) {
            $latestId = Document::query()
                ->where('payment_type', self::PAYMENT_TYPE)
                ->where('src_type', $srcType)
                ->where('reference_id', $referenceId)
                ->whereNull('deleted_at')
                ->max('id');

            if ($latestId) {
                $latestIds[] = (int) $latestId;
            }
        }

        if (empty($latestIds)) {
            return collect();
        }

        return Document::query()
            ->whereIn('id', $latestIds)
            ->orderBy('src_type')
            ->get();
    }

    private function persistChildDocument(
        Request $request,
        ?string $documentHash,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        $user = $activePosition->get();

        if (! $user || ! $user->jabatan || ! $user->unitKerja || ! in_array((int) $user->jabatan->id, [9, 10], true)) {
            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengelola LPJ TU.'], 403);
        }

        $validated = $request->validate([
            'reference' => ['required'],
            'src_type' => ['required', 'in:STS,LPJ,TBP'],
            'nomor_document' => ['required', 'string', 'max:255'],
            'file_document' => [$documentHash ? 'nullable' : 'required', 'file', 'mimes:pdf'],
        ]);

        try {
            $referenceId = EncryptedId::decode((string) $validated['reference']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 400, 'message' => 'Referensi paket tidak valid.'], 400);
        }

        $package = $this->findPackageByReferenceId($referenceId);
        if (! $package || ! $this->canManagePackageByRole($package, (int) $user->jabatan->id, (int) $user->unitKerja->id)) {
            return response()->json(['status' => 403, 'message' => 'Paket LPJ tidak dapat dikelola.'], 403);
        }

        if (is_null($package->sp2d_finished_at) && ! $this->hasAnyChildDocument($referenceId)) {
            return response()->json(['status' => 409, 'message' => 'LPJ hanya dapat dibuat setelah SP2D selesai ditandatangani.'], 409);
        }

        $existingDocument = null;
        if ($documentHash) {
            try {
                $documentId = EncryptedId::decode($documentHash);
            } catch (\Throwable $e) {
                return response()->json(['status' => 400, 'message' => 'Dokumen tidak valid.'], 400);
            }

            $existingDocument = Document::query()->find($documentId);
            if (
                ! $existingDocument ||
                $existingDocument->payment_type !== self::PAYMENT_TYPE ||
                ! in_array($existingDocument->src_type, self::CHILD_TYPES, true) ||
                (int) $existingDocument->reference_id !== $referenceId ||
                ! $this->canEditChildDocument($existingDocument)
            ) {
                return response()->json(['status' => 403, 'message' => 'Dokumen tidak dapat diubah.'], 403);
            }
        } else {
            $duplicateExists = Document::query()
                ->where('payment_type', self::PAYMENT_TYPE)
                ->where('src_type', $validated['src_type'])
                ->where('reference_id', $referenceId)
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicateExists) {
                return response()->json([
                    'status' => 409,
                    'message' => 'Tipe dokumen ini sudah ada pada paket LPJ. Gunakan fitur edit untuk memperbarui dokumen.',
                ], 409);
            }
        }

        $storedPath = null;

        try {
            DB::transaction(function () use (
                $validated,
                $request,
                $user,
                $referenceId,
                $existingDocument,
                &$storedPath,
                $documentHistoryService
            ) {
                $filename = $existingDocument?->src_name;
                if ($request->hasFile('file_document')) {
                    $filename = $this->storeFile($request->file('file_document'), $validated['src_type']);
                    $storedPath = $filename;
                }

                if ($existingDocument) {
                    $payload = [
                        'nomor' => $validated['nomor_document'],
                        'src_name' => $filename,
                        'uploaded_by' => $user->id,
                        'rejected_by' => null,
                        'notes' => null,
                        'submit' => null,
                        'assigned_to' => (string) $user->jabatan->id,
                        'downloaded_by' => null,
                        'downloaded_at' => null,
                        'updated_at' => now(),
                    ];

                    if ($request->hasFile('file_document')) {
                        $payload['status'] = null;
                    }

                    $existingDocument->update($payload);
                    $documentHistoryService->edited($existingDocument->id, $filename, $existingDocument->id_unit_kerja);

                    return;
                }

                $document = Document::create([
                    'reference_id' => $referenceId,
                    'src_name' => $filename,
                    'src_type' => $validated['src_type'],
                    'payment_type' => self::PAYMENT_TYPE,
                    'id_unit_kerja' => $user->unitKerja->id,
                    'uploaded_by' => $user->id,
                    'assigned_to' => (string) $user->jabatan->id,
                    'nomor' => $validated['nomor_document'],
                ]);

                $documentHistoryService->upload($document->id, $filename, $document->id_unit_kerja);
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                @unlink(public_path($this->directoryForType($validated['src_type']).'/'.$storedPath));
            }

            Log::channel(self::LOG_CHANNEL)->error('LPJ TU save failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 500, 'message' => 'Gagal menyimpan dokumen LPJ.'], 500);
        }

        return response()->json([
            'status' => 200,
            'message' => $documentHash ? 'Dokumen LPJ berhasil diperbarui.' : 'Dokumen LPJ berhasil diunggah.',
        ]);
    }

    private function hasAnyChildDocument(int $referenceId): bool
    {
        return Document::query()
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereIn('src_type', self::CHILD_TYPES)
            ->where('reference_id', $referenceId)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function storeFile($file, string $srcType): string
    {
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $file->move(public_path($this->directoryForType($srcType)), $filename);

        return $filename;
    }

    private function directoryForType(string $srcType): string
    {
        return match ($srcType) {
            'STS' => '/File_STS',
            'TBP' => '/File_TBP',
            default => '/File_LPJ',
        };
    }

    private function renderPackageStatus($row, int $jabatanId): string
    {
        $referenceHash = EncryptedId::encode((int) $row->sp2d_id);
        [$previewUrl, $previewName] = $this->resolvePreviewFile($row);
        $notes = $this->resolveRejectedNotes($row);

        if ($this->packageHasRejectedDocument($row)) {
            return '<span type="button" class="btn btn-sm btn-danger show-document"'
                .' data-id="'.$referenceHash.'"'
                .' data-wenk="'.e($notes ?: 'Dokumen LPJ ditolak').'"'
                .' data-wenk-color="red"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="far fa-file-excel"></i> Ditolak</span>';
        }

        if ($this->packageIsFinished($row)) {
            return '<span type="button" class="btn btn-sm btn-success show-document"'
                .' data-id="'.$referenceHash.'"'
                .' data-wenk="Paket LPJ telah selesai"'
                .' data-wenk-color="green"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fas fa-check-double"></i> Selesai</span>';
        }

        if (! $this->packageHasDocuments($row)) {
            return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                .' data-id="'.$referenceHash.'"'
                .' data-wenk="Belum ada dokumen LPJ pada paket ini"'
                .' data-wenk-color="blue"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fas fa-folder-open"></i> Belum Upload</span>';
        }

        if ($jabatanId === 13) {
            $hasSubmitted = ! empty((string) ($row->submit_sts ?? ''))
                || ! empty((string) ($row->submit_lpj ?? ''))
                || ! empty((string) ($row->submit_tbp ?? ''));
            $hasSigned = ! empty((string) ($row->status_sts ?? ''))
                || ! empty((string) ($row->status_lpj ?? ''))
                || ! empty((string) ($row->status_tbp ?? ''));

            if ($hasSubmitted) {
                return '<span type="button" class="btn btn-sm btn-primary show-document"'
                    .' data-id="'.$referenceHash.'"'
                    .' data-wenk="Telah Submit"'
                    .' data-wenk-color="blue"'
                    .' data-toggle="modal" data-target="#FormTTE">'
                    .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
            }

            if ($hasSigned) {
                return '<span type="button" class="btn btn-sm btn-success show-document"'
                    .' data-id="'.$referenceHash.'"'
                    .' data-wenk="Sudah TTE"'
                    .' data-wenk-color="green"'
                    .' data-toggle="modal" data-target="#FormTTE">'
                    .'<i class="fas fa-file-contract"></i> Sudah TTE</span>';
            }

            return '<span type="button" class="btn btn-sm btn-warning show-document"'
                .' data-id="'.$referenceHash.'"'
                .' data-wenk="Belum TTE"'
                .' data-wenk-color="orange"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
        }

        if (in_array($jabatanId, [5, 6], true)) {
            $signed = $this->approverHasSignedRequiredDocuments($row, $jabatanId);
            $label = $signed ? 'Sudah TTE' : 'Belum TTE';
            $class = $signed ? 'btn-success' : 'btn-warning';
            $icon = $signed ? 'fa-file-contract' : 'fa-file-signature';

            return '<span type="button" class="btn btn-sm '.$class.' show-document"'
                .' data-id="'.$referenceHash.'"'
                .' data-wenk="'.$label.'"'
                .' data-wenk-color="'.($signed ? 'green' : 'orange').'"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fas '.$icon.'"></i> '.$label.'</span>';
        }

        if (in_array($jabatanId, [9, 10], true)) {
            $signed = $this->initiatorHasSignedAllDocuments($row, $jabatanId);
            $submitted = $this->initiatorHasSubmittedAllDocuments($row, $jabatanId);

            if ($submitted) {
                return '<span type="button" class="btn btn-sm btn-primary show-document"'
                    .' data-id="'.$referenceHash.'"'
                    .' data-wenk="Telah Submit"'
                    .' data-wenk-color="blue"'
                    .' data-toggle="modal" data-target="#FormTTE">'
                    .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
            }

            $label = $signed ? 'Sudah TTE' : 'Belum TTE';
            $class = $signed ? 'btn-success' : 'btn-warning';
            $icon = $signed ? 'fa-file-contract' : 'fa-file-signature';

            return '<span type="button" class="btn btn-sm '.$class.' show-document"'
                .' data-id="'.$referenceHash.'"'
                .' data-wenk="'.$label.'"'
                .' data-wenk-color="'.($signed ? 'green' : 'orange').'"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fas '.$icon.'"></i> '.$label.'</span>';
        }

        return '<span type="button" class="btn btn-sm btn-info show-document"'
            .' data-id="'.$referenceHash.'"'
            .' data-wenk="Tampilkan Dokumen"'
            .' data-wenk-color="blue"'
            .' data-toggle="modal" data-target="#FormTTE">'
            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
    }

    private function renderChildStatus(Document $row, $package): string
    {
        $sp2dHash = EncryptedId::encode((int) $package->sp2d_id);
        $path = $this->directoryForType($row->src_type);
        $url = ! is_null($row->status)
            ? $path.'/signs/'.$row->src_name
            : $path.'/'.$row->src_name;

        if (! is_null($row->rejected_by)) {
            return '<span type="button" class="btn btn-sm btn-danger show-document"'
                .' data-id="'.$sp2dHash.'"'
                .' data-wenk="'.e((string) ($row->notes ?: 'Dokumen ditolak')).'"'
                .' data-wenk-color="red"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="far fa-file-excel"></i> Ditolak</span>';
        }

        if (! is_null($row->finished_at)) {
            return '<span type="button" class="btn btn-sm btn-success show-document"'
                .' data-id="'.$sp2dHash.'"'
                .' data-wenk="Dokumen selesai"'
                .' data-wenk-color="green">'
                .'<i class="fas fa-check-double"></i> Selesai</span>';
        }

        if (! is_null($row->status)) {
            return '<span type="button" class="btn btn-sm btn-success show-document"'
                .' data-id="'.$sp2dHash.'"'
                .' data-wenk="Dokumen sudah ditandatangani"'
                .' data-wenk-color="green">'
                .'<i class="fas fa-file-contract"></i> Sudah TTE</span>';
        }

        return '<span type="button" class="btn btn-sm btn-warning show-document"'
            .' data-id="'.$sp2dHash.'"'
            .' data-wenk="Dokumen belum ditandatangani"'
            .' data-wenk-color="orange">'
            .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
    }

    private function resolvePreviewFile($row): array
    {
        foreach (['lpj', 'sts', 'tbp', 'sp2d'] as $prefix) {
            $nameField = 'src_name_'.$prefix;
            if (! empty($row->{$nameField})) {
                $type = strtoupper($prefix);
                if ($type === 'SP2D') {
                    $path = '/File_SP2D/';
                    $signed = ! is_null($row->status_sp2d);
                } else {
                    $path = $this->directoryForType($type).'/';
                    $signed = ! is_null($row->{'status_'.$prefix});
                }

                $url = $signed ? rtrim($path, '/').'/signs/'.$row->{$nameField} : $path.$row->{$nameField};

                return [$url, (string) $row->{$nameField}];
            }
        }

        return ['', ''];
    }

    private function resolveRejectedNotes($row): ?string
    {
        foreach (['notes_lpj', 'notes_sts', 'notes_tbp'] as $field) {
            if (! empty($row->{$field})) {
                return (string) $row->{$field};
            }
        }

        return null;
    }

    private function packageHasDocuments($row): bool
    {
        return ! empty($row->sts_id) || ! empty($row->lpj_id) || ! empty($row->tbp_id);
    }

    private function packageHasRejectedDocument($row): bool
    {
        return ! is_null($row->rejected_by_sts) || ! is_null($row->rejected_by_lpj) || ! is_null($row->rejected_by_tbp);
    }

    private function packageIsFinished($row): bool
    {
        $hasDocument = false;

        foreach ([
            'finished_at_sts' => 'sts_id',
            'finished_at_lpj' => 'lpj_id',
            'finished_at_tbp' => 'tbp_id',
        ] as $finishedField => $idField) {
            if (empty($row->{$idField})) {
                continue;
            }

            $hasDocument = true;

            if (empty($row->{$finishedField})) {
                return false;
            }
        }

        return $hasDocument;
    }

    private function initiatorHasSignedAllDocuments($row, int $jabatanId): bool
    {
        $docs = $this->packageDocumentsForRoleCheck($row, false);
        if (empty($docs)) {
            return false;
        }

        foreach ($docs as $status) {
            if (! in_array((string) $jabatanId, $this->csvToArray($status), true)) {
                return false;
            }
        }

        return true;
    }

    private function initiatorHasSubmittedAllDocuments($row, int $jabatanId): bool
    {
        $docs = $this->packageDocumentsForRoleCheck($row, false);
        if (empty($docs)) {
            return false;
        }

        foreach (['submit_sts', 'submit_lpj', 'submit_tbp'] as $field) {
            $statusField = str_replace('submit_', 'status_', $field);
            if (empty($row->{str_replace('submit_', '', $field).'_id'})) {
                continue;
            }
            if (! in_array((string) $jabatanId, $this->csvToArray($row->{$field}), true)) {
                return false;
            }
            if (empty($row->{$statusField})) {
                return false;
            }
        }

        return true;
    }

    private function approverHasSignedRequiredDocuments($row, int $jabatanId): bool
    {
        $docs = $this->packageDocumentsForRoleCheck($row, true);
        if (empty($docs)) {
            return false;
        }

        foreach ($docs as $status) {
            if (! in_array((string) $jabatanId, $this->csvToArray($status), true)) {
                return false;
            }
        }

        return true;
    }

    private function packageDocumentsForRoleCheck($row, bool $approverMode): array
    {
        $fields = $approverMode
            ? ['status_sts' => 'sts_id', 'status_tbp' => 'tbp_id']
            : ['status_sts' => 'sts_id', 'status_lpj' => 'lpj_id', 'status_tbp' => 'tbp_id'];

        $docs = [];
        foreach ($fields as $statusField => $idField) {
            if (! empty($row->{$idField})) {
                $docs[] = (string) ($row->{$statusField} ?? '');
            }
        }

        return $docs;
    }

    private function canManageCrud($row, int $jabatanId): bool
    {
        if (! in_array($jabatanId, [9, 10], true)) {
            return false;
        }

        if ($this->packageIsFinished($row)) {
            return false;
        }

        if ($this->packageHasDocuments($row) && $this->resolveFlowRole($row) !== (string) $jabatanId) {
            return false;
        }

        if ($this->packageHasRejectedDocument($row)) {
            return true;
        }

        return ! $this->initiatorHasSubmittedAllDocuments($row, $jabatanId);
    }

    private function canSubmitPackage($row, int $jabatanId): bool
    {
        if ($this->packageHasRejectedDocument($row) || $this->packageIsFinished($row) || ! $this->packageHasDocuments($row)) {
            return false;
        }

        if (in_array($jabatanId, [9, 10], true)) {
            return $this->resolveFlowRole($row) === (string) $jabatanId
                && $this->initiatorHasSignedAllDocuments($row, $jabatanId)
                && ! $this->initiatorHasSubmittedAllDocuments($row, $jabatanId);
        }

        if (in_array($jabatanId, [5, 6], true)) {
            return $this->resolveFlowRole($row) === ($jabatanId === 5 ? '9' : '10')
                && $this->approverHasSignedRequiredDocuments($row, $jabatanId)
                && $this->allExistingDocumentsSigned($row);
        }

        return false;
    }

    private function canDenyPackage($row, int $jabatanId): bool
    {
        if (! in_array($jabatanId, [5, 6], true) || $this->packageHasRejectedDocument($row) || $this->packageIsFinished($row)) {
            return false;
        }

        return $this->resolveFlowRole($row) === ($jabatanId === 5 ? '9' : '10') && $this->packageHasDocuments($row);
    }

    private function allExistingDocumentsSigned($row): bool
    {
        foreach (['status_sts' => 'sts_id', 'status_lpj' => 'lpj_id', 'status_tbp' => 'tbp_id'] as $statusField => $idField) {
            if (! empty($row->{$idField}) && empty($row->{$statusField})) {
                return false;
            }
        }

        return true;
    }

    private function canViewPackage($package, int $jabatanId, int $unitKerjaId): bool
    {
        if (in_array($jabatanId, [1, 13], true)) {
            return true;
        }

        if (! in_array($jabatanId, [5, 6, 9, 10], true)) {
            return false;
        }

        return (int) $package->id_unit_kerja === $unitKerjaId;
    }

    private function canManagePackageByRole($package, int $jabatanId, int $unitKerjaId): bool
    {
        if (! $this->canViewPackage($package, $jabatanId, $unitKerjaId)) {
            return false;
        }

        if (! in_array($jabatanId, [9, 10], true)) {
            return false;
        }

        if ($this->packageIsFinished($package)) {
            return false;
        }

        if ($this->packageHasDocuments($package) && $this->resolveFlowRole($package) !== (string) $jabatanId) {
            return false;
        }

        if ($this->packageHasRejectedDocument($package)) {
            return true;
        }

        return ! $this->initiatorHasSubmittedAllDocuments($package, $jabatanId);
    }

    private function canEditChildDocument(Document $document): bool
    {
        if (! in_array((string) $document->src_type, self::CHILD_TYPES, true)) {
            return false;
        }

        if (! is_null($document->finished_at)) {
            return false;
        }

        if (! is_null($document->submit) && is_null($document->rejected_by)) {
            return false;
        }

        return true;
    }

    private function canManageChildDocument(Document $document, $user): bool
    {
        if (! $this->canEditChildDocument($document)) {
            return false;
        }

        $jabatanId = (int) ($user->jabatan->id ?? 0);
        $unitKerjaId = (int) ($user->unitKerja->id ?? 0);

        if (! in_array($jabatanId, [9, 10], true) || (int) $document->id_unit_kerja !== $unitKerjaId) {
            return false;
        }

        $package = $this->findPackageByReferenceId((int) $document->reference_id);
        if (! $package) {
            return false;
        }

        return $this->canManagePackageByRole($package, $jabatanId, $unitKerjaId);
    }

    private function resolveFlowRole($row): string
    {
        if (
            $this->csvContainsRole($row->submit_spp ?? null, '10')
            || $this->csvContainsRole($row->submit_spp ?? null, '6')
        ) {
            return '10';
        }

        if (
            $this->csvContainsRole($row->submit_spp ?? null, '9')
            || $this->csvContainsRole($row->submit_spp ?? null, '5')
        ) {
            return '9';
        }

        if ($this->csvContainsRole($row->submit_tbp ?? null, '10')
            || $this->csvContainsRole($row->submit_lpj ?? null, '10')
            || $this->csvContainsRole($row->submit_sts ?? null, '10')) {
            return '10';
        }

        if (
            $this->csvContainsRole($row->submit_tbp ?? null, '9')
            || $this->csvContainsRole($row->submit_lpj ?? null, '9')
            || $this->csvContainsRole($row->submit_sts ?? null, '9')
        ) {
            return '9';
        }

        if (
            $this->csvContainsRole($row->assigned_to_tbp ?? null, '6')
            || $this->csvContainsRole($row->assigned_to_lpj ?? null, '6')
            || $this->csvContainsRole($row->assigned_to_sts ?? null, '6')
            || $this->csvContainsRole($row->assigned_to_tbp ?? null, '10')
            || $this->csvContainsRole($row->assigned_to_lpj ?? null, '10')
            || $this->csvContainsRole($row->assigned_to_sts ?? null, '10')
        ) {
            return '10';
        }

        if (
            $this->csvContainsRole($row->assigned_to_tbp ?? null, '5')
            || $this->csvContainsRole($row->assigned_to_lpj ?? null, '5')
            || $this->csvContainsRole($row->assigned_to_sts ?? null, '5')
            || $this->csvContainsRole($row->assigned_to_tbp ?? null, '9')
            || $this->csvContainsRole($row->assigned_to_lpj ?? null, '9')
            || $this->csvContainsRole($row->assigned_to_sts ?? null, '9')
        ) {
            return '9';
        }

        return '9';
    }

    private function isPackageReadyForSubmit($latestDocs, int $jabatanId): bool
    {
        if ($latestDocs->isEmpty()) {
            return false;
        }

        if (in_array($jabatanId, [9, 10], true)) {
            foreach ($latestDocs as $doc) {
                if (! in_array((string) $jabatanId, $this->csvToArray($doc->status), true)) {
                    return false;
                }
            }

            return true;
        }

        $required = $latestDocs->filter(fn ($doc) => in_array($doc->src_type, ['STS', 'TBP'], true));
        if ($required->isEmpty()) {
            return false;
        }

        foreach ($required as $doc) {
            if (! in_array((string) $jabatanId, $this->csvToArray($doc->status), true)) {
                return false;
            }
        }

        foreach ($latestDocs as $doc) {
            if (empty($doc->status)) {
                return false;
            }
        }

        return true;
    }

    private function csvToArray(?string $value): array
    {
        if (! $value) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($item) => $item !== ''));
    }

    private function csvContainsRole(?string $value, string $role): bool
    {
        return in_array($role, $this->csvToArray($value), true);
    }
}
