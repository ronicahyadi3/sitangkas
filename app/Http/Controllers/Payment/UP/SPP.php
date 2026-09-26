<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment\UP;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\UP as PaymentUP;
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
    private const PAYMENT_TYPE = 'UP';

    private const SRC_TYPE = 'SPP';

    private const FILE_DIR = '/File_SPP';

    private const LOG_CHANNEL = 'payment_up';

    private function auditorStatusBadge(object $row): string
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

    public function index(ActivePositionService $activePosition)
    {
        abort_unless($this->canAccessPage($activePosition->get()), 403);

        return view('Payment.UP.spp');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel(self::LOG_CHANNEL)->warning('SPP UP json blocked: invalid active position', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            $assignedExpr = "REPLACE(COALESCE(spp.assigned_to,''), ' ', '')";
            $submitExpr = "REPLACE(COALESCE(spp.submit,''), ' ', '')";

            if (! in_array($jabatanId, [5, 7, 9, 13], true)) {
                Log::channel(self::LOG_CHANNEL)->warning('SPP UP json blocked: forbidden role', [
                    'jabatan_id' => $jabatanId,
                    'duration_ms' => $this->durationMs($start),
                ]);

                return DataTables::of(collect())->make(true);
            }

            $query = PaymentUP::rootQueryAlias('spp')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'spp.id_unit_kerja')
                ->where('spp.src_type', self::SRC_TYPE)
                ->where('spp.payment_type', self::PAYMENT_TYPE)
                ->when(true, function ($q) use ($jabatanId, $unitKerjaId, $assignedExpr, $submitExpr) {
                    if ($jabatanId === 13) {
                        return;
                    }

                    if ($jabatanId === 9) {
                        $q->where('spp.id_unit_kerja', $unitKerjaId);

                        return;
                    }

                    if ($jabatanId === 5) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where('spp.id_unit_kerja', $unitKerjaId)
                                ->orWhere('uk.skpd_id', $unitKerjaId);
                        })->where(function ($scope) use ($assignedExpr, $submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('5', {$assignedExpr})")
                                ->orWhereRaw("FIND_IN_SET('5', {$submitExpr})");
                        });

                        return;
                    }

                    if ($jabatanId === 7) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where('spp.id_unit_kerja', $unitKerjaId)
                                ->orWhere('uk.skpd_id', $unitKerjaId);
                        })->where(function ($scope) use ($assignedExpr, $submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('7', {$assignedExpr})")
                                ->orWhereNotNull('spp.verify')
                                ->orWhereRaw("FIND_IN_SET('5', {$submitExpr})");
                        });
                    }
                })
                ->select([
                    'spp.id',
                    'spp.nomor as nomor_spp',
                    'spp.uraian',
                    'spp.src_name as src_name_spp',
                    'spp.status',
                    'spp.submit',
                    'spp.assigned_to',
                    'spp.verify',
                    'spp.rejected_by',
                    'spp.notes',
                    'spp.nominal',
                    'spp.id_unit_kerja',
                    'spp.created_at',
                    'uk.nama as unit_kerja',
                ])
                ->orderByDesc('spp.created_at');

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

                    return $this->statusButton($row, $jabatanId);
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode((int) $row->id);
                    $actions = [];
                    $submitArr = $this->csvToArray($row->submit);
                    $statusArr = $this->csvToArray($row->status);
                    $submitCount = array_count_values($submitArr);
                    $isRejected = ! is_null($row->rejected_by);
                    $canSubmit = $this->canSubmitByFlow($jabatanId, $submitCount, $statusArr);

                    if ($jabatanId === 13) {
                        $actions[] = $btn($enc, 'show-document', 'fa-solid fa-eye text-primary', 'Detail Dokumen', 'data-id="'.$enc.'"');
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if ($jabatanId === 9) {
                        $canEdit = $isRejected || (($submitCount['9'] ?? 0) === 0);
                        if ($canEdit) {
                            $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                            $actions[] = $btn(
                                $enc,
                                'delete',
                                'fas fa-trash text-danger',
                                'Hapus',
                                'data-payment="UP" data-type="SPP"'
                            );
                        }
                    }

                    if ($jabatanId === 9 && ! $isRejected) {
                        if ($canSubmit) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }

                        if (
                            (($submitCount['9'] ?? 0) === 1) &&
                            (($submitCount['5'] ?? 0) >= 1) &&
                            (($submitCount['7'] ?? 0) === 0)
                        ) {
                            $actions[] = $btn(
                                $enc,
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                'data-payment="UP" data-type="SPP"'
                            );
                        }
                    }

                    if ($jabatanId === 5 && ! $isRejected) {
                        if ($canSubmit) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }

                        if ((($submitCount['9'] ?? 0) >= 1) && (($submitCount['5'] ?? 0) === 0)) {
                            $actions[] = $btn(
                                $enc,
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                'data-payment="UP" data-type="SPP"'
                            );
                        }
                    }

                    if (
                        $jabatanId === 7 &&
                        ! $isRejected &&
                        is_null($row->verify) &&
                        (($submitCount['9'] ?? 0) >= 2) &&
                        (($submitCount['5'] ?? 0) >= 1)
                    ) {
                        $actions[] = $btn(
                            $enc,
                            'verify_data',
                            'fas fa-user-check text-success',
                            'Verifikasi',
                            'data-payment="UP" data-type="SPP"'
                        );
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="UP" data-type="SPP"'
                        );
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel(self::LOG_CHANNEL)->debug('SPP UP json success', [
                'duration_ms' => $this->durationMs($start),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('SPP UP json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data SPP UP.',
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
            'nomor_spp' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'file_spp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP UP store blocked: invalid active position', [
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        if ($jabatanId !== 9) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP UP store blocked: forbidden role', [
                'jabatan_id' => $jabatanId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang membuat SPP UP.',
            ], 403);
        }

        $storedFiles = [];
        $createdId = null;

        try {
            DB::transaction(function () use ($request, $user, &$storedFiles, $documentHistoryService, &$createdId) {
                $filename = $this->storeFile($request->file('file_spp'), self::FILE_DIR, $storedFiles);

                $spp = Document::create([
                    'nomor' => $request->input('nomor_spp'),
                    'uraian' => $request->input('uraian'),
                    'nominal' => (float) $request->input('nominal'),
                    'src_name' => $filename,
                    'src_type' => self::SRC_TYPE,
                    'payment_type' => self::PAYMENT_TYPE,
                    'id_unit_kerja' => (int) $user->unitKerja->id,
                    'uploaded_by' => (int) $user->id,
                    'assigned_to' => '9',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $createdId = (int) $spp->id;

                $documentHistoryService->upload(
                    $spp->id,
                    $filename,
                    (int) $spp->id_unit_kerja
                );
            });

            Log::channel(self::LOG_CHANNEL)->info('SPP UP store success', [
                'doc_id' => $createdId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data SPP UP berhasil disimpan.',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('SPP UP store failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal menyimpan data SPP UP.',
            ], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP UP edit invalid hash', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $spp = $this->findSppForCrud($id, $user);
        if (! $spp) {
            return response()->json([
                'status' => 404,
                'message' => 'Data SPP UP tidak ditemukan.',
            ], 404);
        }

        if (! $this->canEditDraftOrRejected($spp)) {
            return response()->json([
                'status' => 409,
                'message' => 'Dokumen SPP UP yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.',
            ], 409);
        }

        return response()->json([
            'status' => 200,
            'data' => $spp,
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
            'nomor_spp' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'file_spp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $sppId = EncryptedId::decode($id);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP UP update invalid hash', [
                'hash' => $id,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $storedFiles = [];

        try {
            DB::transaction(function () use ($request, $sppId, $user, &$storedFiles, $documentHistoryService) {
                $spp = $this->findSppForCrud($sppId, $user, true);
                if (! $spp) {
                    throw new \RuntimeException('Data SPP UP tidak ditemukan.');
                }

                if (! $this->canEditDraftOrRejected($spp)) {
                    throw new \RuntimeException('Dokumen SPP UP yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.');
                }

                $payload = [
                    'nomor' => $request->input('nomor_spp'),
                    'uraian' => $request->input('uraian'),
                    'nominal' => (float) $request->input('nominal'),
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'verify' => null,
                    'assigned_to' => '9',
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_spp')) {
                    $filename = $this->storeFile($request->file('file_spp'), self::FILE_DIR, $storedFiles);
                    $payload['src_name'] = $filename;
                    $payload['uploaded_by'] = (int) $user->id;
                    $payload['status'] = null;
                }

                $spp->update($payload);

                $documentHistoryService->edited(
                    $spp->id,
                    (string) $spp->src_name,
                    (int) $spp->id_unit_kerja
                );
            });

            Log::channel(self::LOG_CHANNEL)->info('SPP UP update success', [
                'doc_id' => $sppId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data SPP UP berhasil diperbarui.',
            ]);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('SPP UP update failed', [
                'doc_id' => $sppId,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal memperbarui data SPP UP.',
            ], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        try {
            $sppId = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->warning('SPP UP submit invalid hash', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        if (! in_array($jabatanId, [5, 9], true)) {
            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang melakukan submit SPP UP.',
            ], 403);
        }

        try {
            $successMessage = 'SPP UP berhasil disubmit.';

            DB::transaction(function () use ($sppId, $jabatanId, $unitKerjaId, $documentHistoryService, &$successMessage) {
                $spp = Document::query()
                    ->with('unitKerja')
                    ->whereKey($sppId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spp) {
                    throw new \RuntimeException('Data SPP UP tidak ditemukan.');
                }

                if (! $this->canAccessForSubmit($spp, $jabatanId, $unitKerjaId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini.');
                }

                if (! is_null($spp->rejected_by)) {
                    throw new \RuntimeException('Dokumen SPP UP sudah ditolak.');
                }

                $statusArr = $this->csvToArray($spp->status);
                if (! in_array((string) $jabatanId, $statusArr, true)) {
                    throw new \RuntimeException('Dokumen SPP UP belum TTE oleh jabatan aktif.');
                }

                $submitArr = $this->csvToArray($spp->submit);
                $submitCount = array_count_values($submitArr);

                $assignedTo = match ($jabatanId) {
                    9 => match (true) {
                        ($submitCount['9'] ?? 0) === 0 => '5',
                        ($submitCount['9'] ?? 0) === 1 && ($submitCount['5'] ?? 0) >= 1 && ($submitCount['7'] ?? 0) === 0 => '7',
                        default => throw new \RuntimeException('Urutan submit BP tidak valid.'),
                    },
                    5 => (($submitCount['9'] ?? 0) >= 1 && ($submitCount['5'] ?? 0) === 0)
                        ? '9'
                        : throw new \RuntimeException('SPP UP belum dapat disubmit oleh PA.'),
                    default => throw new \RuntimeException('User tidak memiliki hak submit.'),
                };

                $newSubmit = $spp->submit
                    ? $spp->submit.','.$jabatanId
                    : (string) $jabatanId;

                $spp->update([
                    'submit' => $newSubmit,
                    'assigned_to' => $assignedTo,
                    'updated_at' => now(),
                ]);

                $documentHistoryService->submit(
                    $spp->id,
                    (string) $spp->src_name,
                    (int) $spp->id_unit_kerja
                );

                $successMessage = match ($jabatanId) {
                    9 => $assignedTo === '5'
                        ? 'SPP UP berhasil disubmit ke PA.'
                        : 'SPP UP berhasil disubmit ke PPK.',
                    5 => 'SPP UP berhasil disubmit kembali ke BP.',
                    default => 'SPP UP berhasil disubmit.',
                };
            });

            Log::channel(self::LOG_CHANNEL)->info('SPP UP submit success', [
                'doc_id' => $sppId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 200,
                'message' => $successMessage,
            ]);
        } catch (\RuntimeException $e) {
            Log::channel(self::LOG_CHANNEL)->info('SPP UP submit blocked', [
                'doc_id' => $sppId,
                'message' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('SPP UP submit failed', [
                'doc_id' => $sppId,
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

    private function statusButton(object $row, int $jabatanId): string
    {
        $enc = EncryptedId::encode((int) $row->id);
        $fileUrl = ! is_null($row->status)
            ? self::FILE_DIR.'/signs/'.$row->src_name_spp
            : self::FILE_DIR.'/'.$row->src_name_spp;

        if (! is_null($row->rejected_by)) {
            return '<span type="button" class="btn btn-sm btn-danger show-document"'
                .' data-id="'.$enc.'"'
                .' data-wenk="'.e((string) ($row->notes ?: 'Dokumen ditolak')).'"'
                .' data-wenk-color="red"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="far fa-file-excel"></i> '.$this->rejectedLabel((int) $row->rejected_by).'</span>';
        }

        if ($jabatanId === 7) {
            if (! is_null($row->verify)) {
                return '<span type="button" class="btn btn-sm btn-success show-document"'
                    .' data-id="'.$enc.'"'
                    .' data-wenk="Telah Verifikasi"'
                    .' data-wenk-color="green"'
                    .' data-toggle="modal" data-target="#FormTTE">'
                    .'<i class="fas fa-user-check"></i> Telah Verifikasi</span>';
            }

            return '<span type="button" class="btn btn-sm btn-warning show-document"'
                .' data-id="'.$enc.'"'
                .' data-wenk="Belum Verifikasi"'
                .' data-wenk-color="orange"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fa-solid fa-triangle-exclamation"></i> Belum Verifikasi</span>';
        }

        if (! is_null($row->verify)) {
            return '<span type="button" class="btn btn-sm btn-success show-document"'
                .' data-id="'.$enc.'"'
                .' data-wenk="Telah Verifikasi"'
                .' data-wenk-color="green"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fas fa-user-check"></i> Telah Verifikasi</span>';
        }

        $submitArr = $this->csvToArray($row->submit);
        $statusArr = $this->csvToArray($row->status);
        $submitCount = array_count_values($submitArr);
        $signedByViewer = in_array((string) $jabatanId, $statusArr, true);
        $submittedByViewer = ($submitCount[(string) $jabatanId] ?? 0) > 0;
        $canSubmit = $this->canSubmitByFlow($jabatanId, $submitCount, $statusArr);

        if ($canSubmit) {
            return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                .' data-id="'.$enc.'"'
                .' data-wenk="Belum Submit"'
                .' data-wenk-color="blue"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>';
        }

        if ($submittedByViewer) {
            return '<span type="button" class="btn btn-sm btn-primary show-document"'
                .' data-id="'.$enc.'"'
                .' data-wenk="Telah Submit"'
                .' data-wenk-color="blue"'
                .' data-toggle="modal" data-target="#FormTTE">'
                .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
        }

        if ($signedByViewer) {
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
    }

    private function canSubmitByFlow(int $jabatanId, array $submitCount, array $statusArr): bool
    {
        if (! in_array((string) $jabatanId, $statusArr, true)) {
            return false;
        }

        return match ($jabatanId) {
            9 => (
                (($submitCount['9'] ?? 0) === 0 && ($submitCount['5'] ?? 0) === 0 && ($submitCount['7'] ?? 0) === 0) ||
                (($submitCount['9'] ?? 0) === 1 && ($submitCount['5'] ?? 0) >= 1 && ($submitCount['7'] ?? 0) === 0)
            ),
            5 => (
                ($submitCount['9'] ?? 0) >= 1 &&
                ($submitCount['5'] ?? 0) === 0 &&
                ($submitCount['7'] ?? 0) === 0
            ),
            default => false,
        };
    }

    private function canEditDraftOrRejected(Document $spp): bool
    {
        if (! is_null($spp->rejected_by)) {
            return true;
        }

        return $this->csvToArray($spp->submit) === [];
    }

    private function canAccessForSubmit(Document $document, int $jabatanId, int $unitKerjaId): bool
    {
        if ($jabatanId === 9) {
            return (int) $document->id_unit_kerja === $unitKerjaId;
        }

        if ($jabatanId === 5) {
            if ((int) $document->id_unit_kerja === $unitKerjaId) {
                return true;
            }

            return (int) ($document->unitKerja?->skpd_id ?? 0) === $unitKerjaId;
        }

        return false;
    }

    private function findSppForCrud(int $id, object $user, bool $lock = false): ?Document
    {
        $query = Document::query()
            ->whereKey($id)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        $jabatanId = (int) ($user->jabatan->id ?? 0);
        if ($jabatanId === 9 && isset($user->unitKerja->id)) {
            $query->where('id_unit_kerja', (int) $user->unitKerja->id);

            return $query->first();
        }

        return null;
    }

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $csv)),
            static fn (string $value): bool => $value !== ''
        ));
    }

    private function rejectedLabel(int $jabatanId): string
    {
        return match ($jabatanId) {
            5 => 'Ditolak PA',
            7 => 'Ditolak PPK',
            9 => 'Ditolak BP',
            default => 'Dokumen Ditolak',
        };
    }

    private function canAccessPage(?object $user): bool
    {
        $jabatanId = (int) ($user->jabatan->id ?? 0);

        return in_array($jabatanId, [5, 7, 9, 13], true);
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
