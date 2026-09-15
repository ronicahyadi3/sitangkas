<?php

namespace App\Http\Controllers\Payment\TU;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\TU as PaymentTU;
use App\Models\UnitKerja;
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

        if (! is_null($row->rejected_by)) {
            $label = match ((int) $row->rejected_by) {
                5 => 'Ditolak PA',
                6 => 'Ditolak KPA',
                7 => 'Ditolak PPK',
                4 => 'Ditolak Verifikator',
                default => 'Ditolak',
            };

            return $badge('btn-danger', 'far fa-file-excel', $label);
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
            return $badge('btn-secondary', 'fas fa-file-signature', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function index()
    {
        return view('Payment.TU.spm');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel('payment_tu')->warning('SPM TU json blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            $submitExpr = "REPLACE(COALESCE(spm.submit,''), ' ', '')";

            Log::channel('payment_tu')->debug('SPM TU json request');

            $query = PaymentTU::rootQueryAlias('spm')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'spm.id_unit_kerja')
                ->leftJoin('document as pengajuan', function ($join) {
                    $join->whereRaw('(pengajuan.id = spm.reference_id OR pengajuan.reference_id = spm.reference_id)')
                        ->where('pengajuan.src_type', 'PENGAJUAN')
                        ->where('pengajuan.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('pengajuan.deleted_at');
                })
                ->leftJoin('document as spp', function ($join) {
                    $join->whereRaw('(spp.reference_id = spm.reference_id OR spp.id = spm.reference_id)')
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
                ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($unitKerjaId, $jabatanId, $submitExpr) {
                    if ($jabatanId === 7) {
                        $q->where('spm.id_unit_kerja', $unitKerjaId);
                    } elseif (in_array($jabatanId, [5], true)) {

                        $q->where('spm.id_unit_kerja', $unitKerjaId);

                        $q->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['7']);
                    } elseif (in_array($jabatanId, [6], true)) {
                        $q->where(function ($scope) use ($unitKerjaId) {
                            $scope->where('spp.id_unit_kerja', $unitKerjaId)
                                ->orWhere('pengajuan.id_unit_kerja', $unitKerjaId)
                                ->orWhere(function ($fallback) use ($unitKerjaId) {
                                    $fallback->whereNull('spp.id')
                                        ->whereNull('pengajuan.id')
                                        ->where('spm.id_unit_kerja', $unitKerjaId);
                                });
                        });

                        $q->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['7']);
                    } elseif ($jabatanId === 4) {
                        $q->where(function ($scope) use ($submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('5', {$submitExpr})")
                                ->orWhereRaw("FIND_IN_SET('6', {$submitExpr})");
                        });
                    }
                })
                ->select([
                    'spm.id',
                    'spm.nomor as nomor_spm',
                    'spm.src_name as src_name_spm',
                    'spm.status',
                    'spm.submit',
                    'spm.rejected_by',
                    'spm.notes',
                    'spm.verify',
                    'spm.created_at',
                    'uk.nama as unit_kerja',
                    'pengajuan.id as id_lpj',
                    'spp.nomor as nomor_spp',
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
                    $enc = EncryptedId::encode($row->id_lpj ?: $row->id);
                    $statusSpm = $this->csvToArray($row->status);
                    $statusSptjm = $this->csvToArray($row->status_sptjm);
                    $statusSpPengajuan = $this->csvToArray($row->status_sp_pengajuan);
                    $submitArr = $this->csvToArray($row->submit);

                    if ($jabatanId === 7) {
                        $statusSigned = ! empty($row->status_sp) && in_array('7', $this->csvToArray($row->status_sp), true);
                        $fileUrl = $statusSigned
                            ? '/File_SP/signs/'.$row->src_name_sp
                            : '/File_SP/'.$row->src_name_sp;
                    } else {
                        $statusSigned = ! is_null($row->status);
                        $fileUrl = $statusSigned
                            ? '/File_SPM/signs/'.$row->src_name_spm
                            : '/File_SPM/'.$row->src_name_spm;
                    }

                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row);
                    }

                    if (! is_null($row->rejected_by)) {
                        $label = match ((int) $row->rejected_by) {
                            5 => 'Ditolak PA',
                            6 => 'Ditolak KPA',
                            7 => 'Ditolak PPK',
                            4 => 'Ditolak Verifikator',
                            default => 'Ditolak',
                        };

                        return '<span type="button" class="btn btn-sm btn-danger show-document" data-url="/File_SPM/'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="'.e((string) $row->notes).'" data-wenk-color="red" data-toggle="modal" data-target="#FormTTE"><i class="far fa-file-excel"></i> '.$label.'</span>';
                    }

                    if (! is_null($row->verify)) {
                        return '<span type="button" class="btn btn-sm btn-success show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Telah Verifikasi" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-user-check"></i> Telah Verifikasi</span>';
                    }

                    if (! in_array($jabatanId, [4, 5, 6, 7], true)) {
                        return '<span type="button" class="btn btn-sm btn-info show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Tampilkan Dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>';
                    }

                    if ($jabatanId === 7) {
                        if (in_array('7', $submitArr, true)) {
                            return '<span type="button" class="btn btn-sm btn-primary show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_sp.'" data-id="'.$enc.'" data-wenk="Telah Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-paper-plane"></i> Telah Submit</span>';
                        }

                        if (! empty($row->status_sp) && in_array('7', $this->csvToArray($row->status_sp), true)) {
                            return '<span type="button" class="btn btn-sm btn-secondary show-document" data-status="1" data-url="'.$fileUrl.'" data-files="'.$row->src_name_sp.'" data-id="'.$enc.'" data-wenk="Sudah TTE" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                        }

                        return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-url="'.$fileUrl.'" data-files="'.$row->src_name_sp.'" data-id="'.$enc.'" data-wenk="Belum TTE" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum TTE</span>';
                    }

                    if ($jabatanId === 4) {
                        return '<span type="button" class="btn btn-sm btn-warning show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Belum Terverifikasi" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-triangle-exclamation"></i> Belum Verifikasi</span>';
                    }

                    $parafByActor = in_array((string) $jabatanId, $statusSpm, true)
                        && in_array((string) $jabatanId, $statusSptjm, true)
                        && in_array((string) $jabatanId, $statusSpPengajuan, true);

                    if (in_array((string) $jabatanId, $submitArr, true)) {
                        return '<span type="button" class="btn btn-sm btn-primary show-document" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Telah Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-paper-plane"></i> Telah Submit</span>';
                    }

                    if (in_array($jabatanId, [5, 6], true) && $parafByActor) {
                        return '<span type="button" class="btn btn-sm btn-secondary show-document" data-status="1" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Sudah TTE" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-url="'.$fileUrl.'" data-files="'.$row->src_name_spm.'" data-id="'.$enc.'" data-wenk="Belum TTE" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum TTE</span>';
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                    $enc = EncryptedId::encode($row->id);
                    $encRef = EncryptedId::encode($row->id_lpj ?: $row->id);
                    $actions = [];
                    $submitArr = $this->csvToArray($row->submit);
                    $statusSpm = $this->csvToArray($row->status);
                    $statusSptjm = $this->csvToArray($row->status_sptjm);
                    $statusSpPengajuan = $this->csvToArray($row->status_sp_pengajuan);
                    $parafByActor = in_array((string) $jabatanId, $statusSpm, true)
                        && in_array((string) $jabatanId, $statusSptjm, true)
                        && in_array((string) $jabatanId, $statusSpPengajuan, true);

                    if ($jabatanId === 13) {
                        $actions[] = $btn($encRef, 'show-document', 'fa-solid fa-eye text-primary', 'Detail Dokumen', 'data-id="'.$encRef.'"');
                        $actions[] = $btn($encRef, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (! in_array($jabatanId, [4, 5, 6, 7], true)) {
                        $actions[] = $btn($encRef, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if ($jabatanId === 7 && ! is_null($row->rejected_by)) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn($enc, 'delete', 'fas fa-trash text-danger', 'Hapus', 'data-payment="TU" data-type="SPM"');
                    }

                    if (
                        $jabatanId === 7 &&
                        is_null($row->rejected_by) &&
                        is_null($row->submit) &&
                        ! empty($row->status_sp) &&
                        in_array('7', $this->csvToArray($row->status_sp), true)
                    ) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if ($jabatanId === 7 && is_null($row->submit) && is_null($row->rejected_by)) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn($enc, 'delete', 'fas fa-trash text-danger', 'Hapus', 'data-payment="TU" data-type="SPM"');
                    }

                    if (
                        in_array($jabatanId, [5, 6], true) &&
                        is_null($row->rejected_by) &&
                        in_array('7', $submitArr, true) &&
                        ! in_array((string) $jabatanId, $submitArr, true) &&
                        $parafByActor
                    ) {
                        $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                    }

                    if (
                        in_array($jabatanId, [5, 6], true) &&
                        is_null($row->rejected_by) &&
                        in_array('7', $submitArr, true) &&
                        ! in_array((string) $jabatanId, $submitArr, true)
                    ) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="TU" data-type="SPM"'
                        );
                    }

                    if (
                        $jabatanId === 4 &&
                        is_null($row->rejected_by) &&
                        is_null($row->verify) &&
                        (in_array('5', $submitArr, true) || in_array('6', $submitArr, true))
                    ) {
                        $actions[] = $btn($enc, 'verify_data', 'fas fa-user-check text-success', 'Verifikasi');
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="TU" data-type="SPM"'
                        );
                    }

                    $actions[] = $btn($encRef, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel('payment_tu')->debug('SPM TU json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('SPM TU json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
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
        Log::channel('payment_tu')->info('SPM TU submit request', [
            'hash' => $request->id,
        ]);

        try {
            $spmId = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPM TU submit invalid hash', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPM TU submit blocked: invalid active position', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! in_array((int) $user->jabatan->id, [1, 7, 5, 6], true)) {
            Log::channel('payment_tu')->warning('SPM TU submit blocked: forbidden role', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan submit SPM.'], 403);
        }

        try {
            DB::transaction(function () use ($spmId, $user, $documentHistoryService) {
                $spm = Document::query()
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
                    ->where('reference_id', $spm->reference_id)
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

                if ((int) $user->jabatan->id === 7) {
                    if (in_array('7', $submitArr, true)) {
                        throw new \RuntimeException('Dokumen sudah disubmit oleh PPK.');
                    }

                    if (! $sp || ! $this->hasStatusForJabatan($sp->status, 7)) {
                        throw new \RuntimeException('Dokumen SP belum TTE oleh PPK.');
                    }
                    if (! $spp) {
                        throw new \RuntimeException('Referensi SPP tidak ditemukan.');
                    }
                    $newSubmit = $spm->submit ? $spm->submit.',7' : '7';
                    $assignedTo = $this->isFlowWithKpa($spp) ? '6' : '5';
                } else {
                    if (! in_array('7', $submitArr, true)) {
                        throw new \RuntimeException('Dokumen belum disubmit oleh PPK.');
                    }
                    $actorJabatan = (string) $user->jabatan->id;
                    if (in_array($actorJabatan, $submitArr, true)) {
                        throw new \RuntimeException('Dokumen sudah disubmit oleh jabatan Anda.');
                    }
                    if (
                        ! $this->hasStatusForJabatan($spm->status, (int) $actorJabatan) ||
                        ! $sptjm || ! $this->hasStatusForJabatan($sptjm->status, (int) $actorJabatan) ||
                        ! $spPengajuan || ! $this->hasStatusForJabatan($spPengajuan->status, (int) $actorJabatan)
                    ) {
                        throw new \RuntimeException('Dokumen SPM, SPTJM, dan SP Pengajuan belum TTE lengkap oleh jabatan Anda.');
                    }
                    $newSubmit = $spm->submit ? $spm->submit.','.$actorJabatan : $actorJabatan;
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
            });

            Log::channel('payment_tu')->info('SPM TU submit success', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => (int) $user->jabatan->id === 7
                    ? 'SPM berhasil disubmit ke PA/KPA.'
                    : 'SPM berhasil disubmit ke verifikator.',
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_tu')->info('SPM TU submit blocked', [
                'doc_id' => $spmId,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('SPM TU submit failed', [
                'doc_id' => $spmId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
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
                Log::channel('payment_tu')->warning('SPM TU formJson blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }
            if (! $this->canCrud($user)) {
                Log::channel('payment_tu')->warning('SPM TU formJson blocked: forbidden role', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return DataTables::of(collect())->make(true);
            }

            $unitKerjaId = (int) $user->unitKerja->id;
            $isEdited = $request->edited === 'true';
            $spmId = null;

            Log::channel('payment_tu')->debug('SPM TU formJson request', [
                'edited' => $isEdited,
                'has_data' => filled($request->data),
            ]);

            if ($request->data) {
                try {
                    $spmId = EncryptedId::decode($request->data);
                } catch (\Throwable $e) {
                    Log::channel('payment_tu')->warning('SPM TU formJson invalid hash', [
                        'hash' => $request->data,
                        'error' => $e->getMessage(),
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
                }
            }

            $currentSppId = null;
            if ($spmId) {
                $currentReferenceId = Document::query()
                    ->where('id', $spmId)
                    ->where('src_type', 'SPM')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->value('reference_id');

                if (! $currentReferenceId) {
                    Log::channel('payment_tu')->warning('SPM TU formJson blocked: missing reference SPP', [
                        'doc_id' => $spmId,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json(['status' => 404, 'message' => 'Referensi SPP tidak ditemukan.'], 404);
                }

                $currentSppId = $this->resolveCurrentSppId((int) $currentReferenceId);
            }

            $query = PaymentTU::rootQueryAlias('spp')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'spp.id_unit_kerja')
                ->where('spp.src_type', 'SPP')
                ->where('spp.payment_type', self::PAYMENT_TYPE)
                ->whereNull('spp.rejected_by')
                ->where('spp.verify', 1)
                // ->where('spp.id_unit_kerja', $unitKerjaId)
                ->where(function ($q) use ($unitKerjaId) {
                    $q->where('spp.id_unit_kerja', $unitKerjaId)
                        ->orWhere('uk.skpd_id', $unitKerjaId);
                })
                ->where(function ($q) use ($isEdited, $currentSppId) {
                    $q->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('document as spm')
                            ->where(function ($linked) {
                                $linked->whereColumn('spm.reference_id', 'spp.reference_id')
                                    ->orWhereColumn('spm.reference_id', 'spp.id');
                            })
                            ->where('spm.src_type', 'SPM')
                            ->where('spm.payment_type', self::PAYMENT_TYPE)
                            ->whereNull('spm.deleted_at');
                    });

                    if ($isEdited && $currentSppId) {
                        $q->orWhere('spp.id', $currentSppId);
                    }
                })
                ->select([
                    'spp.id',
                    'spp.nomor as nomor_spp',
                    'spp.src_name as src_name_spp',
                    'spp.nominal',
                    'spp.uraian',
                    'spp.rejected_by',
                    'spp.created_at',
                    'uk.nama as unit_kerja',
                ])
                ->orderByDesc('spp.created_at');

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($data) {
                    $url = '/File_SPP/'.$data->src_name_spp;

                    return '<button type="button" class="btn btn-sm btn-info show-document" data-url="'.$url.'" data-id="'.EncryptedId::encode($data->id).'" data-wenk="Klik untuk menampilkan dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</button>';
                })
                ->addColumn('action', function ($data) use ($currentSppId) {
                    $id = EncryptedId::encode($data->id);
                    $checked = ($currentSppId && (int) $currentSppId === (int) $data->id) ? 'checked' : '';
                    $deniedButton = $checked ? '' : '<button type="button" class="btn btn-outline-danger denied" value="'.$id.'" data-wenk="Menolak data" data-payment="TU" data-type="SPP" data-wenk-color="red"><i class="fa-solid fa-ban"></i></button>';

                    return '<div class="d-flex justify-content-center"><div class="btn-group btn-group-sm"><input type="radio" class="btn-check" name="selected_spp" id="spp_'.$id.'" value="'.$id.'" '.$checked.'><label class="btn btn-outline-primary" for="spp_'.$id.'" data-wenk="Pilih data" data-wenk-color="green"><i class="fa-solid fa-check"></i></label>'.$deniedButton.'</div></div>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);

            Log::channel('payment_tu')->debug('SPM TU formJson success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('SPM TU formJson failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat daftar SPP.',
            ], 500);
        }
    }

    public function store(Request $request, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $start = microtime(true);
        $request->validate([
            'selected_spp' => ['required', 'string'],
            'nomor_spm' => ['required', 'string', 'max:255'],
            'nomor_sptjm' => ['required', 'string', 'max:255'],
            'file_spm' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sptjm' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp_pengajuan' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        Log::channel('payment_tu')->info('SPM TU store request', [
            'nomor_spm' => $request->input('nomor_spm'),
            'nomor_sptjm' => $request->input('nomor_sptjm'),
            'has_file_spm' => $request->hasFile('file_spm'),
            'has_file_sptjm' => $request->hasFile('file_sptjm'),
            'has_file_sp' => $request->hasFile('file_sp'),
            'has_file_sp_pengajuan' => $request->hasFile('file_sp_pengajuan'),
        ]);

        try {
            $sppId = EncryptedId::decode($request->selected_spp);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPM TU store invalid selected_spp', [
                'hash' => $request->selected_spp,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Data SPP tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPM TU store blocked: invalid active position', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            Log::channel('payment_tu')->warning('SPM TU store blocked: forbidden role', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat data SPM.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $createdSpmId = null;

        try {
            DB::transaction(function () use ($request, $sppId, $actorId, $unitKerjaId, &$storedFiles, $documentHistoryService, $user, &$createdSpmId) {
                $spp = $this->assertSppAvailability($sppId, null, $user);
                $pengajuanId = $this->resolvePackageReferenceId($spp);

                $spmFile = $this->storeFile($request->file('file_spm'), '/File_SPM', $storedFiles);
                $sptjmFile = $this->storeFile($request->file('file_sptjm'), '/File_SPTJM', $storedFiles);
                $spFile = $this->storeFile($request->file('file_sp'), '/File_SP', $storedFiles);
                $spPengajuanFile = $this->storeFile($request->file('file_sp_pengajuan'), '/File_SP_PENGAJUAN', $storedFiles);

                $spm = Document::create([
                    'nomor' => $request->input('nomor_spm'),
                    'src_name' => $spmFile,
                    'src_type' => 'SPM',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $pengajuanId,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '7',
                    'created_at' => now(),
                ]);
                $createdSpmId = $spm->id;

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
                        'reference_id' => $pengajuanId,
                        'id_unit_kerja' => $unitKerjaId,
                        'uploaded_by' => $actorId,
                        'assigned_to' => '7',
                        'created_at' => now(),
                    ]);
                    $documentHistoryService->upload($doc->id, $doc->src_name, $doc->id_unit_kerja);
                }

                $documentHistoryService->upload($spm->id, $spm->src_name, $spm->id_unit_kerja);
            });

            Log::channel('payment_tu')->info('SPM TU store success', [
                'doc_id' => $createdSpmId,
                'nomor_spm' => $request->input('nomor_spm'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPM berhasil disimpan']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->info('SPM TU store blocked', [
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->error('SPM TU store failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal menyimpan data SPM'], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);
        Log::channel('payment_tu')->debug('SPM TU edit request', [
            'hash' => $request->id,
        ]);

        try {
            $spmId = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPM TU edit invalid hash', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPM TU edit blocked: invalid active position', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            Log::channel('payment_tu')->warning('SPM TU edit blocked: forbidden role', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah data SPM.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $assignedExpr = "REPLACE(COALESCE(assigned_to,''), ' ', '')";

        $spm = Document::query()
            ->where('id', $spmId)
            ->where('src_type', 'SPM')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->when($jabatanId !== 1, function ($q) use ($jabatanId, $unitKerjaId, $assignedExpr) {
                $q->where(function ($scope) use ($jabatanId, $unitKerjaId, $assignedExpr) {
                    $scope->where('id_unit_kerja', $unitKerjaId)
                        ->orWhereRaw("FIND_IN_SET(?, {$assignedExpr})", [(string) $jabatanId]);
                });
            })
            ->first();

        if (! $spm) {
            Log::channel('payment_tu')->warning('SPM TU edit not found/forbidden', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 404, 'message' => 'Data SPM tidak ditemukan.'], 404);
        }

        if (! $this->canEditDocument($spm)) {
            Log::channel('payment_tu')->warning('SPM TU edit blocked: invalid state', [
                'doc_id' => $spm->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
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

        $spp = $this->findSppByReferenceId((int) $spm->reference_id);

        Log::channel('payment_tu')->debug('SPM TU edit success', [
            'doc_id' => $spm->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'spm' => $spm,
                'sp' => $children->get('SP'),
                'sptjm' => $children->get('SPTJM'),
                'sp_pengajuan' => $children->get('SP_PENGAJUAN'),
                'selected_spp' => $spp ? EncryptedId::encode((int) $spp->id) : null,
            ],
        ]);
    }

    public function update(Request $request, string $id, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $start = microtime(true);
        $request->validate([
            'selected_spp' => ['required', 'string'],
            'nomor_spm' => ['required', 'string', 'max:255'],
            'nomor_sptjm' => ['required', 'string', 'max:255'],
            'file_spm' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sptjm' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp_pengajuan' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        Log::channel('payment_tu')->info('SPM TU update request', [
            'hash' => $id,
            'nomor_spm' => $request->input('nomor_spm'),
            'nomor_sptjm' => $request->input('nomor_sptjm'),
            'has_file_spm' => $request->hasFile('file_spm'),
            'has_file_sptjm' => $request->hasFile('file_sptjm'),
            'has_file_sp' => $request->hasFile('file_sp'),
            'has_file_sp_pengajuan' => $request->hasFile('file_sp_pengajuan'),
        ]);

        try {
            $spmId = EncryptedId::decode($id);
            $sppId = EncryptedId::decode($request->selected_spp);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPM TU update invalid hash', [
                'hash' => $id,
                'selected_spp' => $request->selected_spp,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPM TU update blocked: invalid active position', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            Log::channel('payment_tu')->warning('SPM TU update blocked: forbidden role', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang memperbarui data SPM.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;

        try {
            DB::transaction(function () use ($request, $spmId, $sppId, $actorId, &$storedFiles, $documentHistoryService, $user) {
                $spm = Document::query()
                    ->where('id', $spmId)
                    ->where('src_type', 'SPM')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spm) {
                    throw new \RuntimeException('Data SPM tidak ditemukan.');
                }

                if (! $this->canEditDocument($spm)) {
                    throw new \RuntimeException('Dokumen SPM yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.');
                }

                $oldPengajuanReferenceId = (int) $spm->reference_id;
                $spp = $this->assertSppAvailability($sppId, $spm->id, $user);
                $newPengajuanReferenceId = $this->resolvePackageReferenceId($spp);

                $spmPayload = [
                    'nomor' => $request->input('nomor_spm'),
                    'reference_id' => $newPengajuanReferenceId,
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'verify' => null,
                    'users_to' => null,
                    'assigned_to' => '7',
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_spm')) {
                    $spmPayload['src_name'] = $this->storeFile($request->file('file_spm'), '/File_SPM', $storedFiles);
                    $spmPayload['uploaded_by'] = $actorId;
                    $spmPayload['status'] = null;
                }

                $spm->update($spmPayload);
                $documentHistoryService->edited($spm->id, $spm->src_name, $spm->id_unit_kerja);

                $this->updateChildDocument($spm, $oldPengajuanReferenceId, $newPengajuanReferenceId, 'SPTJM', $request->input('nomor_sptjm'), $request->file('file_sptjm'), '/File_SPTJM', $actorId, $storedFiles, $documentHistoryService);
                $this->updateChildDocument($spm, $oldPengajuanReferenceId, $newPengajuanReferenceId, 'SP', null, $request->file('file_sp'), '/File_SP', $actorId, $storedFiles, $documentHistoryService);
                $this->updateChildDocument($spm, $oldPengajuanReferenceId, $newPengajuanReferenceId, 'SP_PENGAJUAN', null, $request->file('file_sp_pengajuan'), '/File_SP_PENGAJUAN', $actorId, $storedFiles, $documentHistoryService);
            });

            Log::channel('payment_tu')->info('SPM TU update success', [
                'doc_id' => $spmId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPM berhasil diperbarui']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->info('SPM TU update blocked', [
                'doc_id' => $spmId,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->error('SPM TU update failed', [
                'doc_id' => $spmId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal memperbarui data SPM'], 400);
        }
    }

    private function updateChildDocument(Document $spm, int $oldReferenceId, int $newReferenceId, string $srcType, ?string $nomor, $file, string $directory, int $actorId, array &$storedFiles, DocumentHistoryService $documentHistoryService): void
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
            'rejected_by' => null,
            'notes' => null,
            'submit' => null,
            'verify' => null,
            'users_to' => null,
            'assigned_to' => '7',
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
        $unitKerjaId = (int) ($user->unitKerja?->id ?? 0);

        $spp = Document::query()
            ->with('unitKerja:id,skpd_id')
            ->where('id', $sppId)
            ->where('src_type', 'SPP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (! $spp) {
            throw new \RuntimeException('Data SPP tidak ditemukan.');
        }

        $inScope = (int) $spp->id_unit_kerja === $unitKerjaId
            || (int) ($spp->unitKerja?->skpd_id ?? 0) === $unitKerjaId;

        if (! $inScope) {
            throw new \RuntimeException('SPP tidak berada dalam scope unit/SKPD Anda.');
        }

        if (! is_null($spp->rejected_by)) {
            throw new \RuntimeException('SPP ditolak.');
        }

        if ((int) $spp->verify !== 1) {
            throw new \RuntimeException('SPP belum diverifikasi.');
        }

        $packageReferenceId = $this->resolvePackageReferenceId($spp);

        $used = Document::query()
            ->where('src_type', 'SPM')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $packageReferenceId)
            ->whereNull('deleted_at')
            ->when($currentSpmId, fn ($q) => $q->where('id', '!=', $currentSpmId))
            ->exists();

        if ($used) {
            throw new \RuntimeException('Data SPP sudah digunakan pada dokumen SPM lain.');
        }

        return $spp;
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
        if ($jabatanId === 1) {
            return true;
        }

        if (! $unitKerjaId) {
            return false;
        }

        if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
            return true;
        }

        $skpdId = UnitKerja::query()
            ->whereKey($document->id_unit_kerja)
            ->value('skpd_id');

        if ((int) $skpdId === (int) $unitKerjaId) {
            return true;
        }

        $assigned = array_filter(explode(',', (string) $document->assigned_to));

        return in_array((string) $jabatanId, $assigned, true);
    }

    private function isFlowWithKpa(Document $spp): bool
    {
        $submit = $this->csvToArray($spp->submit);
        $assigned = $this->csvToArray($spp->assigned_to);

        return in_array('10', $submit, true) || in_array('10', $assigned, true);
    }

    private function hasStatusForJabatan(?string $status, int $jabatanId): bool
    {
        if (! $status) {
            return false;
        }

        return in_array((string) $jabatanId, $this->csvToArray($status), true);
    }

    private function resolveCurrentSppId(int $referenceId): ?int
    {
        $legacySppId = Document::query()
            ->where('id', $referenceId)
            ->where('src_type', 'SPP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->value('id');

        if ($legacySppId) {
            return (int) $legacySppId;
        }

        $currentSppId = Document::query()
            ->where('reference_id', $referenceId)
            ->where('src_type', 'SPP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->value('id');

        return $currentSppId ? (int) $currentSppId : null;
    }

    private function resolvePackageReferenceId(Document $spp): int
    {
        return ! is_null($spp->reference_id)
            ? (int) $spp->reference_id
            : (int) $spp->id;
    }

    private function findSppByReferenceId(int $referenceId): ?Document
    {
        return Document::query()
            ->where('src_type', 'SPP')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($referenceId) {
                $query->where('id', $referenceId)
                    ->orWhere('reference_id', $referenceId);
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$referenceId])
            ->first();
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
        if (! $user || ! $user->jabatan) {
            return false;
        }

        return (int) $user->jabatan->id === 7;
    }

    private function canEditDocument(Document $document): bool
    {
        if (! is_null($document->rejected_by)) {
            return true;
        }

        return ! in_array('7', $this->csvToArray($document->submit), true);
    }
}
