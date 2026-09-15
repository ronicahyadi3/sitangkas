<?php

namespace App\Http\Controllers\Payment\GU_UK;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\GU_UK;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class NPD extends Controller
{
    public function __construct(private readonly PositionIdentityResolver $positionIdentityResolver) {}

    private const SRC_TYPE = 'NPD';

    private const FILE_DIR = '/File_NPD';

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

        if (! is_null($row->rejected_by_npd)) {
            $label = match ((int) $row->rejected_by_npd) {
                6 => 'Ditolak KPA',
                10 => 'Ditolak BPP',
                default => 'Ditolak',
            };

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if (! empty($this->csvToArray($row->submit_npd))) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if (! empty((string) $row->status_npd)) {
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
        Log::channel('payment_gu_unit_kerja')->debug('NPD GU_UK index accessed');

        return view('Payment.GU_UK.npd');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK json blocked: invalid active position', [
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
            $actorId = $this->resolveActorUserId($user);
            $assignedExpr = "REPLACE(COALESCE(document.assigned_to,''), ' ', '')";
            $submitExpr = "REPLACE(COALESCE(document.submit,''), ' ', '')";

            Log::channel('payment_gu_unit_kerja')->debug('NPD GU_UK json request');

            $query = GU_UK::rootQuery()
                ->tap(fn ($q) => GU_UK::withNpd($q, false))
                ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($jabatanId, $unitKerjaId, $scopeUnitId, $actorId, $assignedExpr, $submitExpr) {
                    if ($jabatanId === 8) {
                        $q->where('document.id_unit_kerja', $unitKerjaId)
                            ->whereIn('document.uploaded_by', $this->positionIdentityResolver->equivalentIds($actorId));

                        return;
                    }

                    if ($jabatanId === 9 && $scopeUnitId) {
                        $q->where(function ($scope) use ($scopeUnitId) {
                            $scope->where('document.id_unit_kerja', $scopeUnitId)
                                ->orWhere('unit_kerja_npd.skpd_id', $scopeUnitId);
                        });

                        return;
                    }

                    if (in_array($jabatanId, [9, 5], true) && $scopeUnitId) {
                        $q->where(function ($scope) use ($scopeUnitId) {
                            $scope->where('document.id_unit_kerja', $scopeUnitId)
                                ->orWhere('unit_kerja_npd.skpd_id', $scopeUnitId);
                        });

                        return;
                    }

                    if ($jabatanId === 6) {
                        $q->where('document.id_unit_kerja', $unitKerjaId)
                            ->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['8']);

                        return;
                    }

                    if ($jabatanId === 10) {
                        $q->where('document.id_unit_kerja', $unitKerjaId)
                            ->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['6']);

                        return;
                    }

                    $q->where(function ($scope) use ($unitKerjaId, $jabatanId, $assignedExpr) {
                        $scope->where('document.id_unit_kerja', $unitKerjaId)
                            ->orWhere('unit_kerja_npd.skpd_id', $unitKerjaId)
                            ->orWhereRaw("FIND_IN_SET(?, {$assignedExpr})", [(string) $jabatanId]);
                    });
                })
                ->orderByDesc('created_at_npd');

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
                ->addColumn('status', function ($row) use ($jabatanId) {
                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row);
                    }

                    $enc = EncryptedId::encode($row->id_npd);
                    $submitArr = $this->csvToArray($row->submit_npd);
                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);
                    $isFilled = static fn ($value): bool => ! is_null($value) && trim((string) $value) !== '';

                    if (! is_null($row->rejected_by_npd)) {
                        $rejectedLabel = match ((int) $row->rejected_by_npd) {
                            6 => 'Ditolak KPA',
                            10 => 'Ditolak BPP',
                            default => 'Ditolak',
                        };

                        return '<span type="button" class="btn btn-sm btn-danger show-document"'
                            .' data-url="/File_NPD/'.$row->src_name_npd.'"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="'.e((string) $row->notes_npd).'"'
                            .' data-wenk-color="red"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="far fa-file-excel"></i> '.$rejectedLabel.'</span>';
                    }

                    if (! in_array($jabatanId, [8, 6], true)) {
                        $fileUrl = $isFilled($row->status_npd)
                            ? '/File_NPD/signs/'.$row->src_name_npd
                            : '/File_NPD/'.$row->src_name_npd;

                        return '<span type="button" class="btn btn-sm btn-info show-document"'
                            .' data-url="'.$fileUrl.'"'
                            .' data-files="'.$row->src_name_npd.'"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Tampilkan Dokumen"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                    }

                    if ($submittedByViewer) {
                        $fileUrl = $isFilled($row->status_npd)
                            ? '/File_NPD/signs/'.$row->src_name_npd
                            : '/File_NPD/'.$row->src_name_npd;

                        return '<span type="button" class="btn btn-sm btn-primary show-document"'
                            .' data-url="'.$fileUrl.'"'
                            .' data-files="'.$row->src_name_npd.'"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Telah Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
                    }

                    $fileUrl = $isFilled($row->status_npd)
                        ? '/File_NPD/signs/'.$row->src_name_npd
                        : '/File_NPD/'.$row->src_name_npd;

                    $statusArr = $row->status_npd ? explode(',', $row->status_npd) : [];
                    $alreadySigned = in_array((string) $jabatanId, $statusArr, true);

                    if (! $alreadySigned) {
                        return '<span type="button" class="btn btn-sm btn-warning show-document"'
                            .' data-status="0"'
                            .' data-url="'.$fileUrl.'"'
                            .' data-files="'.$row->src_name_npd.'"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Belum TTE"'
                            .' data-wenk-color="orange"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-success show-document"'
                        .' data-status="1"'
                        .' data-url="'.$fileUrl.'"'
                        .' data-files="'.$row->src_name_npd.'"'
                        .' data-id="'.$enc.'"'
                        .' data-wenk="Sudah TTE"'
                        .' data-wenk-color="green"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fas fa-file-contract"></i> Sudah TTE</span>';
                })
                ->addColumn('action', function ($row) use ($jabatanId, $btn) {
                    $enc = EncryptedId::encode($row->id_npd);
                    $actions = [];
                    $statusArr = $row->status_npd ? explode(',', $row->status_npd) : [];
                    $alreadySigned = in_array((string) $jabatanId, $statusArr, true);
                    $submitArr = $this->csvToArray($row->submit_npd);
                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);
                    $submittedByPptk = in_array('8', $submitArr, true);
                    $submittedByKpa = in_array('6', $submitArr, true);
                    $isRejected = ! is_null($row->rejected_by_npd);

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

                    if (! in_array($jabatanId, [8, 6], true)) {
                        return $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                    }

                    if ($jabatanId === 8 && ! $submittedByPptk && ! $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_UK" data-type="NPD"'
                        );
                    }

                    if ($jabatanId === 8 && $isRejected) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_UK" data-type="NPD"'
                        );
                    }

                    if (
                        $jabatanId === 8 &&
                        ! $isRejected &&
                        ! $submittedByViewer &&
                        $alreadySigned
                    ) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if (
                        $jabatanId === 6 &&
                        ! $isRejected &&
                        $submittedByPptk &&
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
                            'data-payment="GU_UK" data-type="NPD"'
                        );
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->filterColumn('unit_kerja_npd', function ($query, $keyword) {
                    $query->where('unit_kerja_npd.nama', 'like', "%{$keyword}%");
                })
                ->rawColumns(['status', 'action'])
                ->make(true);

            Log::channel('payment_gu_unit_kerja')->debug('NPD GU_UK json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_gu_unit_kerja')->error('NPD GU_UK json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data NPD.',
            ], 500);
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->info('NPD GU_UK store request', [
            'nomor' => $request->input('nomor_npd'),
            'has_file' => $request->hasFile('file_npd'),
        ]);

        $request->validate([
            'nomor_npd' => ['required', 'string', 'max:255'],
            'file_npd' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK store blocked: invalid active position', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK store blocked: forbidden role', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang membuat NPD.',
            ], 403);
        }

        $storedFiles = [];
        $actorId = $this->resolveActorUserId($user);
        $createdDocumentId = null;

        try {
            DB::transaction(function () use ($request, $user, $actorId, &$storedFiles, $documentHistoryService, &$createdDocumentId) {
                $filename = $this->storeFile($request->file('file_npd'), self::FILE_DIR, $storedFiles);

                $document = Document::create([
                    'nomor' => $request->input('nomor_npd'),
                    'src_name' => $filename,
                    'src_type' => self::SRC_TYPE,
                    'payment_type' => GU_UK::PAYMENT_TYPE,
                    'id_unit_kerja' => $user->unitKerja->id,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '8',
                    'created_at' => now(),
                ]);

                $createdDocumentId = $document->id;

                $documentHistoryService->upload(
                    $document->id,
                    $filename,
                    $user->unitKerja->id
                );
            });

            Log::channel('payment_gu_unit_kerja')->info('NPD GU_UK store success', [
                'doc_id' => $createdDocumentId,
                'nomor' => $request->input('nomor_npd'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data NPD berhasil disimpan',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles ?? []);
            Log::channel('payment_gu_unit_kerja')->error('NPD GU_UK store failed', [
                'nomor' => $request->input('nomor_npd'),
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal menyimpan data NPD',
            ], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->debug('NPD GU_UK edit request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK edit invalid hash', [
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
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK edit blocked: invalid active position', [
                'doc_id' => $id ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK edit blocked: forbidden role', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah NPD.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $actorId = $this->resolveActorUserId($user);

        $document = Document::query()
            ->where('id', $id)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', GU_UK::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->when($jabatanId !== 1, function ($q) use ($jabatanId, $unitKerjaId, $actorId) {
                if ($jabatanId === 8) {
                    $q->where('id_unit_kerja', $unitKerjaId)
                        ->whereIn('uploaded_by', $this->positionIdentityResolver->equivalentIds($actorId));

                    return;
                }

                $q->where('id_unit_kerja', $unitKerjaId);
            })
            ->first();

        if (! $document) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK edit not found/forbidden', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Data NPD tidak ditemukan.',
            ], 404);
        }

        Log::channel('payment_gu_unit_kerja')->debug('NPD GU_UK edit success', [
            'doc_id' => $document->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return response()->json([
            'status' => 200,
            'data' => $document,
        ]);
    }

    public function update(
        Request $request,
        string $id,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->info('NPD GU_UK update request', [
            'hash' => $id,
            'nomor' => $request->input('nomor_npd'),
            'has_file' => $request->hasFile('file_npd'),
        ]);

        $request->validate([
            'nomor_npd' => ['required', 'string', 'max:255'],
            'file_npd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $documentId = EncryptedId::decode($id);
        } catch (\Throwable) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK update invalid hash', [
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
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK update blocked: invalid active position', [
                'doc_id' => $documentId ?? null,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        if (! $this->canManageCrud($user)) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK update blocked: forbidden role', [
                'doc_id' => $documentId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah NPD.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $actorId = $this->resolveActorUserId($user);

        $document = Document::query()
            ->where('id', $documentId)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', GU_UK::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->when($jabatanId !== 1, function ($q) use ($jabatanId, $unitKerjaId, $actorId) {
                if ($jabatanId === 8) {
                    $q->where('id_unit_kerja', $unitKerjaId)
                        ->whereIn('uploaded_by', $this->positionIdentityResolver->equivalentIds($actorId));

                    return;
                }

                $q->where('id_unit_kerja', $unitKerjaId);
            })
            ->first();

        if (! $document) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK update not found/forbidden', [
                'doc_id' => $documentId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Data NPD tidak ditemukan.',
            ], 404);
        }

        $storedFiles = [];
        try {
            DB::transaction(function () use (
                $request,
                $document,
                $actorId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $payload = [
                    'nomor' => $request->input('nomor_npd'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'assigned_to' => '8',
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_npd')) {
                    $filename = $this->storeFile($request->file('file_npd'), self::FILE_DIR, $storedFiles);
                    $payload['src_name'] = $filename;
                    $payload['uploaded_by'] = $actorId;
                    $payload['status'] = null;
                }

                $document->update($payload);

                $documentHistoryService->edited(
                    $document->id,
                    $document->src_name,
                    $document->id_unit_kerja
                );
            });

            Log::channel('payment_gu_unit_kerja')->info('NPD GU_UK update success', [
                'doc_id' => $documentId,
                'nomor' => $request->input('nomor_npd'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data NPD berhasil diperbarui',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles ?? []);
            Log::channel('payment_gu_unit_kerja')->error('NPD GU_UK update failed', [
                'doc_id' => $documentId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal memperbarui data NPD',
            ], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel('payment_gu_unit_kerja')->info('NPD GU_UK submit request', [
            'hash' => $request->id,
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK submit invalid hash', [
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
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK submit blocked: invalid active position', [
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
        $actorId = $this->resolveActorUserId($user);

        if (! in_array($jabatanId, [8, 6], true)) {
            Log::channel('payment_gu_unit_kerja')->warning('NPD GU_UK submit blocked: forbidden role', [
                'doc_id' => $docId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang melakukan submit NPD.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($docId, $jabatanId, $unitKerjaId, $actorId, $documentHistoryService) {
                $doc = Document::query()
                    ->where('id', $docId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', GU_UK::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $doc) {
                    throw new \RuntimeException('Dokumen NPD tidak ditemukan');
                }

                if (! $this->canAccessForSubmit($doc, $jabatanId, $unitKerjaId, $actorId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini');
                }

                if (! is_null($doc->rejected_by)) {
                    throw new \RuntimeException('Dokumen sudah ditolak');
                }

                $submitArr = $this->csvToArray($doc->submit);
                if (in_array((string) $jabatanId, $submitArr, true)) {
                    throw new \RuntimeException('Dokumen sudah disubmit oleh jabatan ini');
                }

                if (! $this->hasStatusForJabatan($doc->status, $jabatanId)) {
                    throw new \RuntimeException('Dokumen belum TTE oleh jabatan Anda');
                }

                if ($jabatanId === 6 && ! in_array('8', $submitArr, true)) {
                    throw new \RuntimeException('Dokumen belum disubmit oleh PPTK');
                }

                $newSubmit = $doc->submit
                    ? $doc->submit.','.$jabatanId
                    : (string) $jabatanId;

                $assignedTo = match ($jabatanId) {
                    8 => '6',
                    6 => '10',
                };

                $doc->update([
                    'submit' => $newSubmit,
                    'assigned_to' => $assignedTo,
                    'updated_at' => now(),
                ]);

                $documentHistoryService->submit(
                    $doc->id,
                    $doc->src_name,
                    $doc->id_unit_kerja
                );
            });

            Log::channel('payment_gu_unit_kerja')->info('NPD GU_UK submit success', [
                'doc_id' => $docId,
                'assigned_to' => $jabatanId === 8 ? '6' : '10',
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => $jabatanId === 8
                    ? 'NPD berhasil disubmit ke KPA.'
                    : 'NPD berhasil disubmit ke BPP.',
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_gu_unit_kerja')->info('NPD GU_UK submit blocked', [
                'doc_id' => $docId,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_gu_unit_kerja')->error('NPD GU_UK submit failed', [
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
        return (int) $this->positionIdentityResolver->pptkActorPosition($user)->getKey();
    }

    private function canManageCrud($user): bool
    {
        return $user
            && $user->jabatan
            && (int) $user->jabatan->id === 8;
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

    private function canAccessForSubmit(Document $document, int $jabatanId, ?int $unitKerjaId, int $actorId): bool
    {
        if ($jabatanId === 1) {
            return true;
        }

        if (! $unitKerjaId) {
            return false;
        }

        if ($jabatanId === 8) {
            return (int) $document->id_unit_kerja === (int) $unitKerjaId
                && $this->positionIdentityResolver->contains($actorId, (int) $document->uploaded_by);
        }

        if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
            return true;
        }

        return false;
    }
}
