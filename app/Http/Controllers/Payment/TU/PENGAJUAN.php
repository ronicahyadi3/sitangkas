<?php

namespace App\Http\Controllers\Payment\TU;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\TU;
use App\Models\UnitKerja;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class PENGAJUAN extends Controller
{
    private const SRC_TYPE = 'PENGAJUAN';

    private const FILE_DIR = '/File_PENGAJUAN';

    private const PAYMENT_TYPE = 'TU';

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

        $statusArr = $this->csvToArray($row->status);
        $submitArr = $this->csvToArray($row->submit);
        $submitCount = array_count_values($submitArr);
        $assignedArr = $this->csvToArray($row->assigned_to);
        $isForwardedToBp = in_array('9', $assignedArr, true) || (($submitCount['4'] ?? 0) >= 2);
        $isVerifiedByBudAndForwarded = in_array('2', $statusArr, true) && $isForwardedToBp;

        if (! is_null($row->rejected_by)) {
            $label = match ((int) $row->rejected_by) {
                2 => 'Ditolak BUD',
                4 => 'Ditolak Verifikator',
                5 => 'Ditolak PA',
                6 => 'Ditolak KPA',
                default => 'Ditolak',
            };

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if ($isVerifiedByBudAndForwarded) {
            return $badge('btn-success', 'fas fa-user-check', 'Terverifikasi');
        }

        if (! is_null($row->verify)) {
            return $badge('btn-info', 'fas fa-user-check', 'Telah Verifikasi');
        }

        if (! empty($submitArr)) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if (! empty($statusArr)) {
            return $badge('btn-secondary', 'fas fa-file-signature', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function index()
    {
        return view('Payment.TU.pengajuan');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_tu')->warning('PENGAJUAN TU json blocked: invalid active position', [
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
            $assignedExpr = "REPLACE(COALESCE(document.assigned_to,''), ' ', '')";
            $submitExpr = "REPLACE(COALESCE(document.submit,''), ' ', '')";
            $statusExpr = "REPLACE(COALESCE(document.status,''), ' ', '')";

            Log::channel('payment_tu')->debug('PENGAJUAN TU json request');

            $query = TU::rootQuery()
                ->where('document.src_type', self::SRC_TYPE)
                ->where('document.payment_type', self::PAYMENT_TYPE)
                ->join('unit_kerjas as uk', 'uk.id', '=', 'document.id_unit_kerja')
                ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($jabatanId, $unitKerjaId, $actorId, $assignedExpr, $statusExpr, $submitExpr) {
                    if ($jabatanId === 8) {
                        $q->where('document.id_unit_kerja', $unitKerjaId);
                        $q->where('document.uploaded_by', $actorId);

                        return;
                    }

                    if (in_array($jabatanId, [5, 6], true)) {
                        $q->where('document.id_unit_kerja', $unitKerjaId);
                        $q->whereRaw("FIND_IN_SET('8', {$submitExpr})");

                        return;
                    }

                    if ($jabatanId === 4) {
                        $q->where(function ($scope) use ($assignedExpr, $submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('4', {$assignedExpr})")
                                ->orWhereRaw("FIND_IN_SET('4', {$submitExpr})")
                                ->orWhereRaw("FIND_IN_SET('5', {$submitExpr})")
                                ->orWhereRaw("FIND_IN_SET('6', {$submitExpr})");
                        });

                        return;
                    }

                    if ($jabatanId === 2) {
                        $q->where(function ($scope) use ($assignedExpr, $statusExpr, $submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('2', {$assignedExpr})")
                                ->orWhere(function ($history) use ($statusExpr, $submitExpr) {
                                    $history->whereRaw("FIND_IN_SET('2', {$statusExpr})")
                                        ->whereRaw("FIND_IN_SET('4', {$submitExpr})");
                                });
                        });

                        return;
                    }

                    if ($jabatanId === 9) {
                        $q->whereRaw("FIND_IN_SET('9', {$assignedExpr})");
                        $q->where('document.id_unit_kerja', $unitKerjaId);

                        return;
                    }

                    $q->whereRaw('1 = 0');
                })
                ->select([
                    'document.id',
                    'document.nomor as nomor_pengajuan',
                    'document.src_name as src_name_pengajuan',
                    'document.status',
                    'document.submit',
                    'document.assigned_to',
                    'document.verify',
                    'document.rejected_by',
                    'document.notes',
                    'document.created_at',
                    'uk.nama as unit_kerja',
                ])
                ->orderByDesc('document.created_at');

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
                    $fileUrl = ! is_null($row->status)
                        ? '/File_PENGAJUAN/signs/'.$row->src_name_pengajuan
                        : '/File_PENGAJUAN/'.$row->src_name_pengajuan;
                    $statusArr = $this->csvToArray($row->status);
                    $submitArr = $this->csvToArray($row->submit);
                    $submitCount = array_count_values($submitArr);
                    $assignedArr = $this->csvToArray($row->assigned_to);
                    $isForwardedToBp = in_array('9', $assignedArr, true) || (($submitCount['4'] ?? 0) >= 2);
                    $isVerifiedByBudAndForwarded = in_array('2', $statusArr, true) && $isForwardedToBp;

                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);
                    $viewerSigned = in_array((string) $jabatanId, $statusArr, true);

                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row);
                    }

                    if (! is_null($row->rejected_by)) {
                        $label = match ((int) $row->rejected_by) {
                            2 => 'Ditolak BUD',
                            4 => 'Ditolak Verifikator',
                            5 => 'Ditolak PA',
                            6 => 'Ditolak KPA',
                            default => 'Ditolak',
                        };

                        return '<span type="button" class="btn btn-sm btn-danger show-document"'
                            .' data-url="/File_PENGAJUAN/'.$row->src_name_pengajuan.'"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="'.e((string) $row->notes).'"'
                            .' data-wenk-color="red"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="far fa-file-excel"></i> '.$label.'</span>';
                    }

                    if ($jabatanId === 8) {
                        if ($isVerifiedByBudAndForwarded) {
                            return '<span type="button" class="btn btn-sm btn-success show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Terverifikasi"'
                                .' data-wenk-color="green"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-user-check"></i> Terverifikasi</span>';
                        }

                        if ($submittedByViewer) {
                            return '<span type="button" class="btn btn-sm btn-primary show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Telah Submit"'
                                .' data-wenk-color="blue"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
                        }

                        if ($viewerSigned) {
                            return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Belum Submit"'
                                .' data-wenk-color="blue"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                        }

                        return '<span type="button" class="btn btn-sm btn-warning show-document"'
                            .' data-status="0"'
                            .' data-url="'.$fileUrl.'"'
                            .' data-files="'.$row->src_name_pengajuan.'"'
                            .' data-id="'.$enc.'"'
                            .' data-wenk="Belum TTE"'
                            .' data-wenk-color="orange"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                    }

                    if (in_array($jabatanId, [5, 6], true)) {
                        if ($isVerifiedByBudAndForwarded) {
                            return '<span type="button" class="btn btn-sm btn-success show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Terverifikasi"'
                                .' data-wenk-color="green"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-user-check"></i> Terverifikasi</span>';
                        }

                        if ($submittedByViewer) {
                            return '<span type="button" class="btn btn-sm btn-primary show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Telah Submit"'
                                .' data-wenk-color="blue"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
                        }

                        if ($viewerSigned) {
                            return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Belum Submit"'
                                .' data-wenk-color="blue"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                        }

                        if (in_array('8', $submitArr, true) && in_array((string) $jabatanId, $assignedArr, true)) {
                            return '<span type="button" class="btn btn-sm btn-warning show-document"'
                                .' data-status="0"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Belum TTE"'
                                .' data-wenk-color="orange"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                        }
                    }

                    if ($jabatanId === 4) {
                        if ($row->verify && (($submitCount['4'] ?? 0) === 1) && in_array('2', $statusArr, true)) {
                            return '<span type="button" class="btn btn-sm btn-info show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Sudah TTE BUD"'
                                .' data-wenk-color="green"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-file-contract"></i> Sudah TTE</span>';
                        }

                        if ($row->verify && (($submitCount['4'] ?? 0) === 1)) {
                            return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Menunggu TTE BUD"'
                                .' data-wenk-color="blue"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-hourglass-half"></i> Menunggu TTE BUD</span>';
                        }

                        if ($row->verify) {
                            return '<span type="button" class="btn btn-sm btn-success show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Terverifikasi"'
                                .' data-wenk-color="green"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-user-check"></i> Terverifikasi</span>';
                        }

                        if (in_array('5', $submitArr, true) || in_array('6', $submitArr, true)) {
                            return '<span type="button" class="btn btn-sm btn-warning show-document"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Belum Verifikasi"'
                                .' data-wenk-color="orange"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fa-solid fa-triangle-exclamation"></i> Belum Verifikasi</span>';
                        }
                    }

                    if ($jabatanId === 2) {
                        if ($viewerSigned) {
                            if ($isForwardedToBp) {
                                return '<span type="button" class="btn btn-sm btn-success show-document"'
                                    .' data-status="1"'
                                    .' data-url="'.$fileUrl.'"'
                                    .' data-files="'.$row->src_name_pengajuan.'"'
                                    .' data-id="'.$enc.'"'
                                    .' data-wenk="Terverifikasi"'
                                    .' data-wenk-color="green"'
                                    .' data-toggle="modal" data-target="#FormTTE">'
                                    .'<i class="fas fa-user-check"></i> Terverifikasi</span>';
                            }

                            return '<span type="button" class="btn btn-sm btn-success show-document"'
                                .' data-status="1"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Sudah TTE"'
                                .' data-wenk-color="green"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-file-contract"></i> Sudah TTE</span>';
                        }

                        if ($row->verify && ($submitCount['4'] ?? 0) >= 1 && in_array('2', $assignedArr, true)) {
                            return '<span type="button" class="btn btn-sm btn-warning show-document"'
                                .' data-status="0"'
                                .' data-url="'.$fileUrl.'"'
                                .' data-files="'.$row->src_name_pengajuan.'"'
                                .' data-id="'.$enc.'"'
                                .' data-wenk="Belum TTE"'
                                .' data-wenk-color="orange"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                        }
                    }

                    return '<span type="button" class="btn btn-sm btn-info show-document"'
                        .' data-url="'.$fileUrl.'"'
                        .' data-files="'.$row->src_name_pengajuan.'"'
                        .' data-id="'.$enc.'"'
                        .' data-wenk="Tampilkan Dokumen"'
                        .' data-wenk-color="blue"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $actions = [];
                    $statusArr = $this->csvToArray($row->status);
                    $submitArr = $this->csvToArray($row->submit);
                    $submitCount = array_count_values($submitArr);
                    $signedByViewer = in_array((string) $jabatanId, $statusArr, true);
                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);
                    $isRejected = ! is_null($row->rejected_by);

                    if ($jabatanId === 13) {
                        $actions[] = $btn($enc, 'show-document', 'fa-solid fa-eye text-primary', 'Detail Dokumen', 'data-id="'.$enc.'"');
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (in_array($jabatanId, [1, 8], true)) {
                        $canEdit = $isRejected || ! $submittedByViewer;
                        if ($canEdit) {
                            $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                            $actions[] = $btn(
                                $enc,
                                'delete',
                                'fas fa-trash text-danger',
                                'Hapus',
                                'data-payment="TU" data-type="PENGAJUAN"'
                            );
                        }

                        if (! $isRejected && ! $submittedByViewer && $signedByViewer) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }
                    }

                    if (in_array($jabatanId, [5, 6], true)) {
                        if (! $isRejected && in_array('8', $submitArr, true) && ! $submittedByViewer) {
                            if ($signedByViewer) {
                                $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                            }
                            $actions[] = $btn(
                                $enc,
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                'data-payment="TU" data-type="PENGAJUAN"'
                            );
                        }
                    }

                    if ($jabatanId === 4) {
                        $canVerify = ! $isRejected && is_null($row->verify) && (in_array('5', $submitArr, true) || in_array('6', $submitArr, true));
                        if ($canVerify) {
                            $actions[] = $btn(
                                $enc,
                                'verify_data',
                                'fas fa-user-check text-success',
                                'Verifikasi',
                                'data-payment="TU" data-type="PENGAJUAN"'
                            );
                            $actions[] = $btn(
                                $enc,
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                'data-payment="TU" data-type="PENGAJUAN"'
                            );
                        }

                        if (! $isRejected && ! is_null($row->verify)) {
                            if (($submitCount['4'] ?? 0) === 0) {
                                $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                            } elseif (($submitCount['4'] ?? 0) === 1 && in_array('2', $statusArr, true)) {
                                $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                            }
                        }
                    }

                    if ($jabatanId === 2) {
                        if (! $isRejected && ! in_array('2', $statusArr, true) && ! is_null($row->verify) && ($submitCount['4'] ?? 0) >= 1) {
                            $actions[] = $btn(
                                $enc,
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                'data-payment="TU" data-type="PENGAJUAN"'
                            );
                        }
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel('payment_tu')->debug('PENGAJUAN TU json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('PENGAJUAN TU json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data pengajuan.',
            ], 500);
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        $request->validate([
            'nomor_pengajuan' => ['required', 'string', 'max:255'],
            'file_pengajuan' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $user = $activePosition->get();
        Log::channel('payment_tu')->info('PENGAJUAN TU store request', [
            'nomor' => $request->input('nomor_pengajuan'),
            'has_file' => $request->hasFile('file_pengajuan'),
        ]);

        if (! $this->canCrud($user)) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU store blocked: forbidden role', [
                'nomor' => $request->input('nomor_pengajuan'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang membuat dokumen pengajuan.',
            ], 403);
        }
        $actorId = $this->resolveActorUserId($user);

        $storedFiles = [];
        $createdDocumentId = null;

        try {
            DB::transaction(function () use (
                $request,
                $user,
                &$storedFiles,
                $documentHistoryService,
                $actorId,
                &$createdDocumentId
            ) {
                $filename = $this->storeFile($request->file('file_pengajuan'), self::FILE_DIR, $storedFiles);

                $document = Document::create([
                    'nomor' => $request->input('nomor_pengajuan'),
                    'src_name' => $filename,
                    'src_type' => self::SRC_TYPE,
                    'payment_type' => self::PAYMENT_TYPE,
                    'id_unit_kerja' => $user->unitKerja->id,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '8',
                    'created_at' => now(),
                ]);

                $createdDocumentId = $document->id;
                $documentHistoryService->upload($document->id, $filename, $user->unitKerja->id);
            });

            Log::channel('payment_tu')->info('PENGAJUAN TU store success', [
                'doc_id' => $createdDocumentId,
                'nomor' => $request->input('nomor_pengajuan'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data pengajuan berhasil disimpan.',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->error('PENGAJUAN TU store failed', [
                'nomor' => $request->input('nomor_pengajuan'),
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal menyimpan data pengajuan.',
            ], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);
        Log::channel('payment_tu')->debug('PENGAJUAN TU edit request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU edit invalid hash', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU edit blocked: forbidden role', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah dokumen pengajuan.',
            ], 403);
        }
        $actorId = $this->resolveActorUserId($user);

        $document = Document::query()
            ->where('id', $id)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('id_unit_kerja', $user->unitKerja->id)
            ->when((int) $user->jabatan->id === 8, fn ($q) => $q->where('uploaded_by', $actorId))
            ->whereNull('deleted_at')
            ->first();

        if (! $document) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU edit not found/forbidden', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }

        if (! $this->canEditDocument($document, (int) $user->jabatan->id)) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU edit blocked: invalid state', [
                'doc_id' => $document->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Dokumen pengajuan yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.',
            ], 409);
        }

        Log::channel('payment_tu')->debug('PENGAJUAN TU edit success', [
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
        $request->validate([
            'nomor_pengajuan' => ['required', 'string', 'max:255'],
            'file_pengajuan' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        Log::channel('payment_tu')->info('PENGAJUAN TU update request', [
            'hash' => $id,
            'nomor' => $request->input('nomor_pengajuan'),
            'has_file' => $request->hasFile('file_pengajuan'),
        ]);

        try {
            $documentId = EncryptedId::decode($id);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU update invalid hash', [
                'hash' => $id,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU update blocked: forbidden role', [
                'doc_id' => $documentId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengubah dokumen pengajuan.',
            ], 403);
        }

        $actorId = $this->resolveActorUserId($user);

        $document = Document::query()
            ->where('id', $documentId)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('id_unit_kerja', $user->unitKerja->id)
            ->when((int) $user->jabatan->id === 8, fn ($q) => $q->where('uploaded_by', $actorId))
            ->whereNull('deleted_at')
            ->first();

        if (! $document) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU update not found/forbidden', [
                'doc_id' => $documentId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }

        if (! $this->canEditDocument($document, (int) $user->jabatan->id)) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU update blocked: invalid state', [
                'doc_id' => $document->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Dokumen pengajuan yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.',
            ], 409);
        }

        $storedFiles = [];

        try {
            DB::transaction(function () use (
                $request,
                $document,
                &$storedFiles,
                $documentHistoryService,
                $actorId
            ) {
                $payload = [
                    'nomor' => $request->input('nomor_pengajuan'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'verify' => null,
                    'assigned_to' => '8',
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_pengajuan')) {
                    $filename = $this->storeFile($request->file('file_pengajuan'), self::FILE_DIR, $storedFiles);
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

            Log::channel('payment_tu')->info('PENGAJUAN TU update success', [
                'doc_id' => $document->id,
                'nomor' => $request->input('nomor_pengajuan'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data pengajuan berhasil diperbarui.',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->error('PENGAJUAN TU update failed', [
                'doc_id' => $documentId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal memperbarui data pengajuan.',
            ], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        Log::channel('payment_tu')->info('PENGAJUAN TU submit request', [
            'hash' => $request->id,
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU submit invalid hash', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU submit blocked: invalid active position', [
                'doc_id' => $docId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $actorId = $this->resolveActorUserId($user);
        if (! in_array($jabatanId, [1, 8, 5, 6, 4], true)) {
            Log::channel('payment_tu')->warning('PENGAJUAN TU submit blocked: forbidden role', [
                'doc_id' => $docId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan submit.'], 403);
        }

        try {
            DB::transaction(function () use ($docId, $jabatanId, $unitKerjaId, $actorId, $documentHistoryService) {
                $doc = Document::query()
                    ->where('id', $docId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $doc) {
                    throw new \RuntimeException('Dokumen pengajuan tidak ditemukan.');
                }

                if (! $this->canAccessForSubmit($doc, $jabatanId, $unitKerjaId, $actorId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini.');
                }

                if (! is_null($doc->rejected_by)) {
                    throw new \RuntimeException('Dokumen sudah ditolak.');
                }

                $submitArr = $this->csvToArray($doc->submit);
                $statusArr = $this->csvToArray($doc->status);
                $submitCount = array_count_values($submitArr);

                $newSubmit = $doc->submit;
                $assignedTo = $doc->assigned_to;

                if ($jabatanId === 8) {
                    if (! in_array('8', $statusArr, true)) {
                        throw new \RuntimeException('Dokumen belum TTE oleh PPTK.');
                    }
                    if (($submitCount['8'] ?? 0) >= 1) {
                        throw new \RuntimeException('Dokumen sudah disubmit oleh PPTK.');
                    }

                    $assignedTo = $this->nextFromPptk($doc->id_unit_kerja);
                    $newSubmit = $doc->submit ? $doc->submit.',8' : '8';
                } elseif (in_array($jabatanId, [5, 6], true)) {
                    if (! in_array('8', $submitArr, true)) {
                        throw new \RuntimeException('Dokumen belum disubmit oleh PPTK.');
                    }
                    if (($submitCount[(string) $jabatanId] ?? 0) >= 1) {
                        throw new \RuntimeException('Dokumen sudah disubmit oleh jabatan ini.');
                    }
                    if (! in_array((string) $jabatanId, $statusArr, true)) {
                        throw new \RuntimeException('Dokumen belum TTE oleh jabatan Anda.');
                    }

                    $assignedTo = '4';
                    $newSubmit = $doc->submit ? $doc->submit.','.$jabatanId : (string) $jabatanId;
                } elseif ($jabatanId === 4) {
                    if (is_null($doc->verify)) {
                        throw new \RuntimeException('Dokumen belum diverifikasi.');
                    }

                    $verifikatorSubmitCount = (int) ($submitCount['4'] ?? 0);
                    if ($verifikatorSubmitCount === 0) {
                        if (! in_array('5', $submitArr, true) && ! in_array('6', $submitArr, true)) {
                            throw new \RuntimeException('Dokumen belum disubmit oleh PA/KPA.');
                        }

                        $assignedTo = '2';
                        $newSubmit = $doc->submit ? $doc->submit.',4' : '4';
                    } elseif ($verifikatorSubmitCount === 1) {
                        if (! in_array('2', $statusArr, true)) {
                            throw new \RuntimeException('Dokumen belum TTE oleh BUD.');
                        }

                        $assignedTo = '9';
                        $newSubmit = $doc->submit.',4';
                    } else {
                        throw new \RuntimeException('Dokumen sudah disubmit maksimal 2 kali oleh verifikator.');
                    }
                }

                $doc->update([
                    'submit' => $newSubmit,
                    'assigned_to' => $assignedTo,
                    'updated_at' => now(),
                ]);

                $documentHistoryService->submit($doc->id, (string) $doc->src_name, $doc->id_unit_kerja);
            }, 5);

            $latestDoc = Document::query()
                ->where('id', $docId)
                ->select(['id', 'nomor', 'assigned_to', 'submit', 'verify', 'status', 'rejected_by', 'id_unit_kerja'])
                ->first();

            Log::channel('payment_tu')->info('PENGAJUAN TU submit success', [
                'doc_id' => $latestDoc?->id ?? $docId,
                'assigned_to' => $latestDoc?->assigned_to,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => match ($jabatanId) {
                    8 => 'Pengajuan berhasil disubmit ke PA/KPA.',
                    5, 6 => 'Pengajuan berhasil disubmit ke verifikator.',
                    4 => 'Pengajuan berhasil disubmit ke proses berikutnya.',
                    default => 'Pengajuan berhasil disubmit.',
                },
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_tu')->info('PENGAJUAN TU submit blocked', [
                'doc_id' => $docId,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('PENGAJUAN TU submit failed', [
                'doc_id' => $docId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 500, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    private function canCrud($user): bool
    {
        return $user
            && $user->jabatan
            && $user->unitKerja
            && in_array((int) $user->jabatan->id, [1, 8], true);
    }

    private function canEditDocument(Document $document, int $jabatanId): bool
    {
        if ($jabatanId === 1) {
            return true;
        }

        if ($jabatanId !== 8) {
            return false;
        }

        return ! is_null($document->rejected_by)
            || ! in_array('8', $this->csvToArray($document->submit), true);
    }

    private function resolveActorUserId($user): int
    {
        return (int) (($user->actingPptkUser) ? $user->actingPptkUser->id : $user->id);
    }

    private function canAccessForSubmit(Document $document, int $jabatanId, int $unitKerjaId, int $actorId): bool
    {
        if ($jabatanId === 1) {
            return true;
        }

        if ($jabatanId === 8) {
            return (int) $document->id_unit_kerja === $unitKerjaId
                && (int) $document->uploaded_by === $actorId;
        }

        if (in_array($jabatanId, [5, 6], true)) {
            return (int) $document->id_unit_kerja === $unitKerjaId;
        }

        if ($jabatanId === 4) {
            $assigned = $this->csvToArray($document->assigned_to);
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);

            if (in_array('4', $assigned, true)) {
                return true;
            }

            return in_array('4', $submit, true)
                && in_array('2', $status, true)
                && ! is_null($document->verify);
        }

        return false;
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
}
