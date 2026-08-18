<?php

namespace App\Http\Controllers\Payment\UP;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\UP;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SPM extends Controller
{
    private const PAYMENT_TYPE = 'UP';

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

        if (
            ! empty($this->csvToArray($row->status))
            || ! empty($this->csvToArray($row->status_sptjm))
            || ! empty($this->csvToArray($row->status_sp_pengajuan))
            || ! empty($this->csvToArray($row->status_sp))
        ) {
            return $badge('btn-success', 'fas fa-file-contract', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function index(ActivePositionService $activePosition)
    {
        abort_unless($this->canAccessPage($activePosition->get()), 403);

        Log::channel(self::LOG_CHANNEL)->debug('SPM UP index accessed');

        return view('Payment.UP.spm');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel(self::LOG_CHANNEL)->warning('SPM UP json blocked: invalid active position', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            $submitExpr = "REPLACE(COALESCE(spm.submit,''), ' ', '')";
            $sppSubmitExpr = "REPLACE(COALESCE(spp.submit,''), ' ', '')";

            if (! in_array($jabatanId, [4, 5, 7, 13], true)) {
                Log::channel(self::LOG_CHANNEL)->warning('SPM UP json blocked: forbidden role', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return DataTables::of(collect())->make(true);
            }

            Log::channel(self::LOG_CHANNEL)->debug('SPM UP json request');

            $query = UP::rootQueryAlias('spm')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'spm.id_unit_kerja')
                ->leftJoin('document as spp', function ($join) {
                    $join->on('spp.id', '=', 'spm.reference_id')
                        ->where('spp.src_type', 'SPP')
                        ->where('spp.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('spp.deleted_at');
                })
                ->leftJoin('document as sptjm', function ($join) {
                    $join->on('sptjm.reference_id', '=', 'spm.reference_id')
                        ->where('sptjm.src_type', 'SPTJM')
                        ->where('sptjm.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('sptjm.deleted_at');
                })
                ->leftJoin('document as sp', function ($join) {
                    $join->on('sp.reference_id', '=', 'spm.reference_id')
                        ->where('sp.src_type', 'SP')
                        ->where('sp.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('sp.deleted_at');
                })
                ->leftJoin('document as sp_pengajuan', function ($join) {
                    $join->on('sp_pengajuan.reference_id', '=', 'spm.reference_id')
                        ->where('sp_pengajuan.src_type', 'SP_PENGAJUAN')
                        ->where('sp_pengajuan.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('sp_pengajuan.deleted_at');
                })
                ->where('spm.src_type', 'SPM')
                ->where('spm.payment_type', self::PAYMENT_TYPE)
                ->when(true, function ($q) use ($unitKerjaId, $jabatanId, $submitExpr, $sppSubmitExpr) {
                    if ($jabatanId === 13) {
                        return;
                    }

                    if ($jabatanId === 7) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where('spm.id_unit_kerja', $unitKerjaId)
                                ->orWhere('uk.skpd_id', $unitKerjaId);
                        });

                        return;
                    }

                    if ($jabatanId === 5) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where('spm.id_unit_kerja', $unitKerjaId)
                                ->orWhere('uk.skpd_id', $unitKerjaId);
                        })
                            ->whereRaw("FIND_IN_SET('7', {$submitExpr})")
                            ->whereRaw("FIND_IN_SET('9', {$sppSubmitExpr})");

                        return;
                    }

                    if ($jabatanId === 4) {
                        $q->whereRaw("FIND_IN_SET('5', {$submitExpr})");

                        return;
                    }

                    $q->whereRaw('1 = 0');
                })
                ->select([
                    'spm.id',
                    'spm.nomor as nomor_spm',
                    'spm.src_name as src_name_spm',
                    'spm.reference_id',
                    'spm.status',
                    'spm.submit',
                    'spm.assigned_to',
                    'spm.rejected_by',
                    'spm.notes',
                    'spm.verify',
                    'spm.created_at',
                    'uk.nama as unit_kerja',
                    'spp.nomor as nomor_spp',
                    'spp.submit as spp_submit',
                    'spp.uraian',
                    'spp.nominal',
                    'sptjm.nomor as nomor_sptjm',
                    'sp.status as status_sp',
                    'sp.src_name as src_name_sp',
                    'sptjm.status as status_sptjm',
                    'sptjm.src_name as src_name_sptjm',
                    'sp_pengajuan.status as status_sp_pengajuan',
                    'sp_pengajuan.src_name as src_name_sp_pengajuan',
                ])
                ->orderByDesc('spm.created_at');

            $btn = static function (string $value, string $class, string $icon, string $title, string $extra = ''): string {
                return sprintf('<button value="%s" class="btn p-2 m-1 %s" title="%s" %s><i class="%s fa-lg"></i></button>', $value, $class, $title, $extra, $icon);
            };

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_button', function ($row) use ($jabatanId) {
                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row);
                    }

                    $enc = EncryptedId::encode((int) ($row->reference_id ?: $row->id));
                    $statusSpm = $this->csvToArray($row->status);
                    $statusSptjm = $this->csvToArray($row->status_sptjm);
                    $statusSpPengajuan = $this->csvToArray($row->status_sp_pengajuan);
                    $statusSp = $this->csvToArray($row->status_sp);
                    $submitArr = $this->csvToArray($row->submit);

                    $fileUrl = ! is_null($row->status)
                        ? '/File_SPM/signs/'.$row->src_name_spm
                        : '/File_SPM/'.$row->src_name_spm;

                    if (! is_null($row->rejected_by)) {
                        return '<span type="button" class="btn btn-sm btn-danger show-document" data-url="/File_SPM/'.$row->src_name_spm.'" data-files="'.e((string) $row->src_name_spm).'" data-id="'.$enc.'" data-wenk="'.e((string) $row->notes).'" data-wenk-color="red" data-toggle="modal" data-target="#FormTTE"><i class="far fa-file-excel"></i> '.$this->rejectedLabel((int) $row->rejected_by).'</span>';
                    }

                    if (! is_null($row->verify)) {
                        return '<span type="button" class="btn btn-sm btn-success show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Telah Verifikasi" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-user-check"></i> Telah Verifikasi</span>';
                    }

                    if ($jabatanId === 4) {
                        return '<span type="button" class="btn btn-sm btn-warning show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Belum Verifikasi" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-triangle-exclamation"></i> Belum Verifikasi</span>';
                    }

                    if ($jabatanId === 7) {
                        $spFileUrl = ! empty($row->status_sp) && in_array('7', $statusSp, true)
                            ? '/File_SP/signs/'.$row->src_name_sp
                            : '/File_SP/'.$row->src_name_sp;

                        if (in_array('7', $submitArr, true)) {
                            return '<span type="button" class="btn btn-sm btn-primary show-document" data-url="'.$spFileUrl.'" data-files="'.$row->src_name_sp.'" data-id="'.$enc.'" data-wenk="Telah Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-paper-plane"></i> Telah Submit</span>';
                        }

                        if (in_array('7', $statusSp, true)) {
                            return '<span type="button" class="btn btn-sm btn-secondary show-document" data-url="'.$spFileUrl.'" data-files="'.$row->src_name_sp.'" data-id="'.$enc.'" data-wenk="Belum Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                        }

                        return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-url="'.$spFileUrl.'" data-files="'.$row->src_name_sp.'" data-id="'.$enc.'" data-wenk="Belum TTE" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum TTE</span>';
                    }

                    if ($jabatanId === 5) {
                        $signedAll = in_array('5', $statusSpm, true)
                            && in_array('5', $statusSptjm, true)
                            && in_array('5', $statusSpPengajuan, true);

                        if (in_array('5', $submitArr, true)) {
                            return '<span type="button" class="btn btn-sm btn-primary show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Telah Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-paper-plane"></i> Telah Submit</span>';
                        }

                        if ($signedAll) {
                            return '<span type="button" class="btn btn-sm btn-secondary show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Belum Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                        }

                        return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Belum TTE" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum TTE</span>';
                    }

                    if (! in_array($jabatanId, [4, 5, 7], true)) {
                        return '<span type="button" class="btn btn-sm btn-info show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Tampilkan Dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-info show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Tampilkan Dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>';
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $actions = [];
                    $isRejected = ! is_null($row->rejected_by);
                    $isVerified = ! is_null($row->verify);
                    $submitArr = $this->csvToArray($row->submit);
                    $statusSpm = $this->csvToArray($row->status);
                    $statusSptjm = $this->csvToArray($row->status_sptjm);
                    $statusSpPengajuan = $this->csvToArray($row->status_sp_pengajuan);
                    $statusSp = $this->csvToArray($row->status_sp);
                    $paSignedAll = in_array('5', $statusSpm, true) && in_array('5', $statusSptjm, true) && in_array('5', $statusSpPengajuan, true);

                    if ($jabatanId === 13) {
                        $actions[] = $btn($enc, 'show-document', 'fa-solid fa-eye text-primary', 'Detail Dokumen', 'data-id="'.$enc.'"');
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if ($isVerified) {
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if ($jabatanId === 7 && (empty($submitArr) || $isRejected)) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn($enc, 'delete', 'fas fa-trash text-danger', 'Hapus', 'data-payment="UP" data-type="SPM"');
                    }

                    if (
                        $jabatanId === 7 &&
                        ! $isRejected &&
                        ! in_array('7', $submitArr, true) &&
                        in_array('7', $statusSp, true)
                    ) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if (
                        $jabatanId === 5 &&
                        ! $isRejected &&
                        in_array('7', $submitArr, true) &&
                        ! in_array('5', $submitArr, true)
                    ) {
                        $actions[] = $btn($enc, 'denied', 'far fa-file-excel text-danger', 'Menolak Data', 'data-payment="UP" data-type="SPM"');
                        if ($paSignedAll) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }
                    }

                    if (
                        $jabatanId === 4 &&
                        ! $isRejected &&
                        ! $isVerified &&
                        in_array('5', $submitArr, true)
                    ) {
                        $actions[] = $btn($enc, 'verify_data', 'fas fa-user-check text-success', 'Verifikasi', 'data-payment="UP" data-type="SPM"');
                        $actions[] = $btn($enc, 'denied', 'far fa-file-excel text-danger', 'Menolak Data', 'data-payment="UP" data-type="SPM"');
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel(self::LOG_CHANNEL)->debug('SPM UP json success', [
                'duration_ms' => $this->durationMs($start),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('SPM UP json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data SPM.',
            ], 500);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('SPM UP submit request', [
            'hash' => $request->id,
        ]);

        try {
            $spmId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP submit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP submit blocked: invalid active position', [
                'spm_id' => $spmId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! in_array((int) $user->jabatan->id, [7, 5], true)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP submit blocked: forbidden role', [
                'spm_id' => $spmId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan submit SPM.'], 403);
        }

        try {
            $successMessage = 'SPM berhasil disubmit.';

            DB::transaction(function () use ($spmId, $user, $documentHistoryService, &$successMessage) {
                $spm = Document::query()
                    ->with('unitKerja')
                    ->where('id', $spmId)
                    ->where('src_type', 'SPM')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spm) {
                    throw new \RuntimeException('Data SPM tidak ditemukan.');
                }

                if (! $this->canAccessForSubmit($spm, (int) $user->jabatan->id, $user->unitKerja?->id)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini.');
                }

                if (! is_null($spm->rejected_by)) {
                    throw new \RuntimeException('Dokumen sudah ditolak.');
                }

                $submitArr = $this->csvToArray($spm->submit);
                $spp = Document::query()
                    ->where('id', $spm->reference_id)
                    ->where('src_type', 'SPP')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                $sp = Document::query()
                    ->where('reference_id', $spm->reference_id)
                    ->where('src_type', 'SP')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                $sptjm = Document::query()
                    ->where('reference_id', $spm->reference_id)
                    ->where('src_type', 'SPTJM')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                $spPengajuan = Document::query()
                    ->where('reference_id', $spm->reference_id)
                    ->where('src_type', 'SP_PENGAJUAN')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                $jabatanId = (int) $user->jabatan->id;

                if ($jabatanId === 7) {
                    if (in_array('7', $submitArr, true)) {
                        throw new \RuntimeException('Dokumen sudah disubmit oleh jabatan ini.');
                    }

                    if (! $sp || ! $this->hasStatusForJabatan($sp->status, 7)) {
                        throw new \RuntimeException('Dokumen SP belum TTE oleh PPK.');
                    }

                    if (! $this->resolveApproverFromSpp($spp?->submit)) {
                        throw new \RuntimeException('SPM hanya dapat dibuat dari alur SPP yang sudah melalui PA.');
                    }

                    $newSubmit = $spm->submit ? $spm->submit.',7' : '7';
                    $assignedTo = '5';
                } else {
                    if (! in_array('7', $submitArr, true)) {
                        throw new \RuntimeException('Dokumen belum disubmit oleh PPK.');
                    }

                    if (in_array((string) $jabatanId, $submitArr, true)) {
                        throw new \RuntimeException('Dokumen sudah disubmit oleh jabatan ini.');
                    }

                    if (
                        ! $this->hasStatusForJabatan($spm->status, $jabatanId) ||
                        ! $sptjm || ! $this->hasStatusForJabatan($sptjm->status, $jabatanId) ||
                        ! $spPengajuan || ! $this->hasStatusForJabatan($spPengajuan->status, $jabatanId)
                    ) {
                        throw new \RuntimeException('Dokumen SPM, SPTJM, dan SP Pengajuan belum TTE lengkap oleh jabatan aktif.');
                    }

                    $newSubmit = $spm->submit ? $spm->submit.','.$jabatanId : (string) $jabatanId;
                    $assignedTo = '4';
                }

                $docIds = Document::query()
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where(function ($q) use ($spm) {
                        $q->where('id', $spm->id)
                            ->orWhere(function ($sub) use ($spm) {
                                $sub->where('reference_id', $spm->reference_id)
                                    ->whereIn('src_type', ['SP', 'SPTJM', 'SP_PENGAJUAN']);
                            });
                    })
                    ->whereNull('deleted_at')
                    ->pluck('id');

                Document::query()
                    ->whereIn('id', $docIds)
                    ->update([
                        'submit' => $newSubmit,
                        'assigned_to' => $assignedTo,
                        'updated_at' => now(),
                    ]);

                $docs = Document::query()->whereIn('id', $docIds)->get();
                foreach ($docs as $doc) {
                    $documentHistoryService->submit($doc->id, (string) $doc->src_name, $doc->id_unit_kerja);
                }

                $successMessage = match ($jabatanId) {
                    7 => 'SPM berhasil disubmit ke PA.',
                    5 => 'SPM berhasil disubmit ke Verifikator.',
                    default => 'SPM berhasil disubmit.',
                };
            });

            Log::channel(self::LOG_CHANNEL)->info('SPM UP submit success', [
                'spm_id' => $spmId,
                'message' => $successMessage,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 200, 'message' => $successMessage]);
        } catch (\RuntimeException $e) {
            Log::channel(self::LOG_CHANNEL)->info('SPM UP submit blocked', [
                'spm_id' => $spmId ?? null,
                'message' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('SPM UP submit failed', [
                'spm_id' => $spmId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json(['status' => 500, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel(self::LOG_CHANNEL)->warning('SPM UP formJson blocked: invalid active position', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }
            if (! $this->canCrud($user)) {
                Log::channel(self::LOG_CHANNEL)->warning('SPM UP formJson blocked: forbidden role', [
                    'duration_ms' => $this->durationMs($start),
                ]);

                return DataTables::of(collect())->make(true);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            $currentSppId = null;
            if ($request->data) {
                try {
                    $spmId = EncryptedId::decode($request->data);
                    $currentSppId = Document::query()
                        ->where('id', $spmId)
                        ->where('src_type', 'SPM')
                        ->where('payment_type', self::PAYMENT_TYPE)
                        ->whereNull('deleted_at')
                        ->value('reference_id');
                } catch (\Throwable) {
                    Log::channel(self::LOG_CHANNEL)->warning('SPM UP formJson invalid hash', [
                        'hash' => $request->data,
                        'duration_ms' => $this->durationMs($start),
                    ]);

                    return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
                }
            }

            Log::channel(self::LOG_CHANNEL)->debug('SPM UP formJson request', [
                'hash' => $request->data,
                'current_spp_id' => $currentSppId,
            ]);

            $query = UP::rootQueryAlias('spp')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'spp.id_unit_kerja')
                ->where('spp.src_type', 'SPP')
                ->where('spp.payment_type', self::PAYMENT_TYPE)
                ->whereNull('spp.rejected_by')
                ->where('spp.verify', 1)
                ->where(function ($q) use ($currentSppId) {
                    $q->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('document as spm')
                            ->whereColumn('spm.reference_id', 'spp.id')
                            ->where('spm.src_type', 'SPM')
                            ->where('spm.payment_type', self::PAYMENT_TYPE)
                            ->whereNull('spm.deleted_at');
                    });

                    if ($currentSppId) {
                        $q->orWhere('spp.id', $currentSppId);
                    }
                })
                ->when($jabatanId === 7, function ($q) use ($unitKerjaId) {
                    $q->where(function ($scope) use ($unitKerjaId) {
                        $scope->where('spp.id_unit_kerja', $unitKerjaId)
                            ->orWhere('uk.skpd_id', $unitKerjaId);
                    });
                })
                ->select([
                    'spp.id',
                    'spp.nomor as nomor_spp',
                    'spp.src_name as src_name_spp',
                    'spp.nominal',
                    'spp.uraian',
                    'spp.created_at',
                    'uk.nama as unit_kerja',
                ])
                ->orderByDesc('spp.created_at');

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($data) {
                    $url = '/File_SPP/signs/'.$data->src_name_spp;

                    return '<button type="button" class="btn btn-sm btn-info show-document" data-url="'.$url.'" data-id="'.EncryptedId::encode($data->id).'" data-files="'.e((string) $data->src_name_spp).'" data-wenk="Klik untuk menampilkan dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</button>';
                })
                ->addColumn('action', function ($data) use ($currentSppId) {
                    $id = EncryptedId::encode($data->id);
                    $checked = ($currentSppId && (int) $currentSppId === (int) $data->id) ? 'checked' : '';
                    $deniedButton = $checked ? '' : '<button type="button" class="btn btn-outline-danger denied" value="'.$id.'" data-wenk="Menolak data" data-payment="UP" data-type="SPP" data-wenk-color="red"><i class="fa-solid fa-ban"></i></button>';

                    return '<div class="d-flex justify-content-center"><div class="btn-group btn-group-sm"><input type="radio" class="btn-check" name="selected_spp" id="spp_'.$id.'" value="'.$id.'" '.$checked.'><label class="btn btn-outline-primary" for="spp_'.$id.'" data-wenk="Pilih data" data-wenk-color="green"><i class="fa-solid fa-check"></i></label>'.$deniedButton.'</div></div>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);

            Log::channel(self::LOG_CHANNEL)->debug('SPM UP formJson success', [
                'current_spp_id' => $currentSppId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel(self::LOG_CHANNEL)->error('SPM UP formJson failed', [
                'hash' => $request->data,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat pilihan SPP untuk SPM.',
            ], 500);
        }
    }

    public function store(Request $request, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('SPM UP store request', [
            'selected_spp' => $request->input('selected_spp'),
            'nomor_spm' => $request->input('nomor_spm'),
            'nomor_sptjm' => $request->input('nomor_sptjm'),
            'has_file_spm' => $request->hasFile('file_spm'),
            'has_file_sptjm' => $request->hasFile('file_sptjm'),
            'has_file_sp' => $request->hasFile('file_sp'),
            'has_file_sp_pengajuan' => $request->hasFile('file_sp_pengajuan'),
        ]);

        $request->validate([
            'selected_spp' => ['required', 'string'],
            'nomor_spm' => ['required', 'string', 'max:255'],
            'nomor_sptjm' => ['required', 'string', 'max:255'],
            'file_spm' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sptjm' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp_pengajuan' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $sppId = EncryptedId::decode($request->selected_spp);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP store blocked: invalid selected SPP', [
                'selected_spp' => $request->input('selected_spp'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'Data SPP tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP store blocked: invalid active position', [
                'selected_spp' => $sppId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP store blocked: forbidden role', [
                'selected_spp' => $sppId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat data SPM.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;
        try {
            DB::transaction(function () use ($request, $sppId, $actorId, &$storedFiles, $documentHistoryService, $user) {
                $spp = $this->assertSppAvailability($sppId, null, $user);

                $spmFile = $this->storeFile($request->file('file_spm'), '/File_SPM', $storedFiles);
                $sptjmFile = $this->storeFile($request->file('file_sptjm'), '/File_SPTJM', $storedFiles);
                $spFile = $this->storeFile($request->file('file_sp'), '/File_SP', $storedFiles);
                $spPengajuanFile = $this->storeFile($request->file('file_sp_pengajuan'), '/File_SP_PENGAJUAN', $storedFiles);

                $spm = Document::create([
                    'nomor' => $request->input('nomor_spm'),
                    'src_name' => $spmFile,
                    'src_type' => 'SPM',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $spp->id,
                    'id_unit_kerja' => $spp->id_unit_kerja,
                    'uploaded_by' => $actorId,
                    'assigned_to' => $this->resolveAssignedTo($user),
                    'users_to' => $actorId,
                    'created_at' => now(),
                ]);

                foreach (
                    [
                        ['type' => 'SPTJM', 'nomor' => $request->input('nomor_sptjm'), 'file' => $sptjmFile],
                        ['type' => 'SP', 'nomor' => null, 'file' => $spFile],
                        ['type' => 'SP_PENGAJUAN', 'nomor' => null, 'file' => $spPengajuanFile],
                    ] as $payload
                ) {
                    $doc = Document::create([
                        'nomor' => $payload['nomor'],
                        'src_name' => $payload['file'],
                        'src_type' => $payload['type'],
                        'payment_type' => self::PAYMENT_TYPE,
                        'reference_id' => $spp->id,
                        'id_unit_kerja' => $spp->id_unit_kerja,
                        'uploaded_by' => $actorId,
                        'assigned_to' => $this->resolveAssignedTo($user),
                        'users_to' => $actorId,
                        'created_at' => now(),
                    ]);
                    $documentHistoryService->upload($doc->id, $doc->src_name, $doc->id_unit_kerja);
                }

                $documentHistoryService->upload($spm->id, $spm->src_name, $spm->id_unit_kerja);
            });

            Log::channel(self::LOG_CHANNEL)->info('SPM UP store success', [
                'selected_spp' => $sppId,
                'nomor_spm' => $request->input('nomor_spm'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPM berhasil disimpan']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->info('SPM UP store blocked', [
                'selected_spp' => $sppId ?? null,
                'message' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('SPM UP store failed', [
                'selected_spp' => $sppId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal menyimpan data SPM'], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->debug('SPM UP edit request', [
            'hash' => $request->id,
        ]);

        try {
            $spmId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP edit invalid hash', [
                'hash' => $request->id,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP edit blocked: invalid active position', [
                'spm_id' => $spmId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP edit blocked: forbidden role', [
                'spm_id' => $spmId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah data SPM.'], 403);
        }

        $spm = $this->findSpmForCrud($spmId, $user);

        if (! $spm) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP edit not found/forbidden', [
                'spm_id' => $spmId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 404, 'message' => 'Data SPM tidak ditemukan.'], 404);
        }

        if (! $this->canEditDraftOrRejected($spm)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP edit blocked: submitted document', [
                'spm_id' => $spm->id,
                'submit' => $spm->submit,
                'rejected_by' => $spm->rejected_by,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Dokumen SPM yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.',
            ], 409);
        }

        $children = Document::query()
            ->where('reference_id', $spm->reference_id)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereIn('src_type', ['SP', 'SPTJM', 'SP_PENGAJUAN'])
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('src_type');

        Log::channel(self::LOG_CHANNEL)->debug('SPM UP edit success', [
            'spm_id' => $spm->id,
            'selected_spp' => $spm->reference_id,
            'duration_ms' => $this->durationMs($start),
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'spm' => $spm,
                'sp' => $children->get('SP'),
                'sptjm' => $children->get('SPTJM'),
                'sp_pengajuan' => $children->get('SP_PENGAJUAN'),
                'selected_spp' => EncryptedId::encode((int) $spm->reference_id),
            ],
        ]);
    }

    public function update(Request $request, string $id, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $start = microtime(true);

        Log::channel(self::LOG_CHANNEL)->info('SPM UP update request', [
            'hash' => $id,
            'selected_spp' => $request->input('selected_spp'),
            'nomor_spm' => $request->input('nomor_spm'),
            'nomor_sptjm' => $request->input('nomor_sptjm'),
            'has_file_spm' => $request->hasFile('file_spm'),
            'has_file_sptjm' => $request->hasFile('file_sptjm'),
            'has_file_sp' => $request->hasFile('file_sp'),
            'has_file_sp_pengajuan' => $request->hasFile('file_sp_pengajuan'),
        ]);

        $request->validate([
            'selected_spp' => ['required', 'string'],
            'nomor_spm' => ['required', 'string', 'max:255'],
            'nomor_sptjm' => ['required', 'string', 'max:255'],
            'file_spm' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sptjm' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp_pengajuan' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $spmId = EncryptedId::decode($id);
            $sppId = EncryptedId::decode($request->selected_spp);
        } catch (\Throwable) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP update invalid hash', [
                'hash' => $id,
                'selected_spp' => $request->input('selected_spp'),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP update blocked: invalid active position', [
                'spm_id' => $spmId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            Log::channel(self::LOG_CHANNEL)->warning('SPM UP update blocked: forbidden role', [
                'spm_id' => $spmId ?? null,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang memperbarui data SPM.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;

        try {
            DB::transaction(function () use ($request, $spmId, $sppId, $actorId, &$storedFiles, $documentHistoryService, $user) {
                $spm = $this->findSpmForCrud($spmId, $user, true);

                if (! $spm) {
                    throw new \RuntimeException('Data SPM tidak ditemukan.');
                }

                if (! $this->canEditDraftOrRejected($spm)) {
                    throw new \RuntimeException('Dokumen SPM yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.');
                }

                $oldReferenceId = (int) $spm->reference_id;
                $spp = $this->assertSppAvailability($sppId, $spm->id, $user);

                $spmPayload = [
                    'nomor' => $request->input('nomor_spm'),
                    'reference_id' => $spp->id,
                    'id_unit_kerja' => $spp->id_unit_kerja,
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'verify' => null,
                    'users_to' => null,
                    'assigned_to' => $this->resolveAssignedTo($user),
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_spm')) {
                    $spmPayload['src_name'] = $this->storeFile($request->file('file_spm'), '/File_SPM', $storedFiles);
                    $spmPayload['uploaded_by'] = $actorId;
                    $spmPayload['status'] = null;
                }

                $spm->update($spmPayload);
                $documentHistoryService->edited($spm->id, $spm->src_name, $spm->id_unit_kerja);

                $this->updateChildDocument($spm, $oldReferenceId, $spp->id, 'SPTJM', $request->input('nomor_sptjm'), $request->file('file_sptjm'), '/File_SPTJM', $actorId, $storedFiles, $documentHistoryService, $user);
                $this->updateChildDocument($spm, $oldReferenceId, $spp->id, 'SP', null, $request->file('file_sp'), '/File_SP', $actorId, $storedFiles, $documentHistoryService, $user);
                $this->updateChildDocument($spm, $oldReferenceId, $spp->id, 'SP_PENGAJUAN', null, $request->file('file_sp_pengajuan'), '/File_SP_PENGAJUAN', $actorId, $storedFiles, $documentHistoryService, $user);
            });

            Log::channel(self::LOG_CHANNEL)->info('SPM UP update success', [
                'spm_id' => $spmId,
                'selected_spp' => $sppId,
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPM berhasil diperbarui']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            $statusCode = $e->getMessage() === 'Dokumen SPM yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.'
                ? 409
                : 400;

            Log::channel(self::LOG_CHANNEL)->info('SPM UP update blocked', [
                'spm_id' => $spmId ?? null,
                'selected_spp' => $sppId ?? null,
                'message' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);

            return response()->json(['status' => $statusCode, 'message' => $e->getMessage()], $statusCode);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel(self::LOG_CHANNEL)->error('SPM UP update failed', [
                'spm_id' => $spmId ?? null,
                'selected_spp' => $sppId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($start),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal memperbarui data SPM'], 400);
        }
    }

    private function updateChildDocument(Document $spm, int $oldReferenceId, int $newReferenceId, string $srcType, ?string $nomor, $file, string $directory, int $actorId, array &$storedFiles, DocumentHistoryService $documentHistoryService, $user): void
    {
        $doc = Document::query()
            ->where('reference_id', $oldReferenceId)
            ->where('src_type', $srcType)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (! $doc && $oldReferenceId !== $newReferenceId) {
            $doc = Document::query()
                ->where('reference_id', $newReferenceId)
                ->where('src_type', $srcType)
                ->where('payment_type', self::PAYMENT_TYPE)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
        }

        $payload = [
            'nomor' => $nomor,
            'reference_id' => $newReferenceId,
            'id_unit_kerja' => $spm->id_unit_kerja,
            'rejected_by' => null,
            'notes' => null,
            'submit' => null,
            'verify' => null,
            'users_to' => null,
            'assigned_to' => $this->resolveAssignedTo($user),
            'updated_at' => now(),
        ];

        if ($file) {
            $payload['src_name'] = $this->storeFile($file, $directory, $storedFiles);
            $payload['uploaded_by'] = $actorId;
            $payload['status'] = null;
        }

        if ($doc) {
            $doc->update($payload);
            $documentHistoryService->edited($doc->id, $doc->src_name, $doc->id_unit_kerja);

            return;
        }

        if (! $file) {
            throw new \RuntimeException('Dokumen '.$srcType.' tidak ditemukan. Upload ulang file diperlukan.');
        }

        $doc = Document::create(array_merge($payload, [
            'src_name' => $payload['src_name'] ?? null,
            'src_type' => $srcType,
            'payment_type' => self::PAYMENT_TYPE,
            'reference_id' => $newReferenceId,
            'id_unit_kerja' => $spm->id_unit_kerja,
            'uploaded_by' => $actorId,
            'created_at' => now(),
        ]));

        $documentHistoryService->upload($doc->id, (string) $doc->src_name, $doc->id_unit_kerja);
    }

    private function assertSppAvailability(int $sppId, ?int $currentSpmId, $user): Document
    {
        $jabatanId = (int) ($user->jabatan->id ?? 0);
        $unitKerjaId = (int) ($user->unitKerja->id ?? 0);

        $spp = Document::query()
            ->with('unitKerja')
            ->where('id', $sppId)
            ->where('src_type', 'SPP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->whereNull('rejected_by')
            ->where('verify', 1)
            ->lockForUpdate()
            ->first();

        if (! $spp) {
            throw new \RuntimeException('Data SPP tidak valid atau belum diverifikasi.');
        }

        if ($jabatanId === 7) {
            $sameScope = (int) $spp->id_unit_kerja === $unitKerjaId
                || (int) ($spp->unitKerja?->skpd_id ?? 0) === $unitKerjaId;

            if (! $sameScope) {
                throw new \RuntimeException('Data SPP tidak berada dalam cakupan unit kerja aktif.');
            }
        }

        $used = Document::query()
            ->where('src_type', 'SPM')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $sppId)
            ->whereNull('deleted_at')
            ->when($currentSpmId, fn ($q) => $q->where('id', '!=', $currentSpmId))
            ->exists();

        if ($used) {
            throw new \RuntimeException('Data SPP sudah digunakan pada dokumen SPM lain.');
        }

        return $spp;
    }

    private function findSpmForCrud(int $id, $user, bool $lock = false): ?Document
    {
        $jabatanId = (int) ($user->jabatan->id ?? 0);
        $unitKerjaId = (int) ($user->unitKerja->id ?? 0);

        if ($jabatanId !== 7 || ! $unitKerjaId) {
            return null;
        }

        $query = Document::query()
            ->with('unitKerja')
            ->where('id', $id)
            ->where('src_type', 'SPM')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->where(function ($scope) use ($unitKerjaId) {
                $scope->where('id_unit_kerja', $unitKerjaId)
                    ->orWhereHas('unitKerja', function ($uk) use ($unitKerjaId) {
                        $uk->where('skpd_id', $unitKerjaId);
                    });
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function canEditDraftOrRejected(Document $spm): bool
    {
        if (! is_null($spm->rejected_by)) {
            return true;
        }

        return trim((string) $spm->submit) === '';
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

    private function canAccessForSubmit(Document $document, int $jabatanId, ?int $unitKerjaId): bool
    {
        if (! $unitKerjaId) {
            return false;
        }

        if (in_array($jabatanId, [7, 5], true) && (int) $document->id_unit_kerja === (int) $unitKerjaId) {
            return true;
        }

        if ($jabatanId === 7 && (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId) {
            return true;
        }

        if ($jabatanId === 5 && (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId) {
            return true;
        }

        return $jabatanId === 4;
    }

    private function hasStatusForJabatan(?string $status, int $jabatanId): bool
    {
        if (! $status) {
            return false;
        }

        return in_array((string) $jabatanId, $this->csvToArray($status), true);
    }

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== ''));
    }

    private function canCrud($user): bool
    {
        return $user
            && $user->jabatan
            && $user->unitKerja
            && (int) $user->jabatan->id === 7;
    }

    private function canAccessPage(?object $user): bool
    {
        $jabatanId = (int) ($user->jabatan->id ?? 0);

        return in_array($jabatanId, [4, 5, 7, 13], true);
    }

    private function resolveAssignedTo($user): ?string
    {
        $jabatanId = (int) ($user->jabatan->id ?? 0);

        return $jabatanId === 7 ? '7' : null;
    }

    private function resolveApproverFromSpp(?string $submit): bool
    {
        $submitArr = $this->csvToArray($submit);

        return in_array('9', $submitArr, true) && in_array('5', $submitArr, true);
    }

    private function rejectedLabel(int $jabatanId): string
    {
        return match ($jabatanId) {
            4 => 'Ditolak Verifikator',
            5 => 'Ditolak PA',
            7 => 'Ditolak PPK',
            default => 'Ditolak',
        };
    }

    private function durationMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
