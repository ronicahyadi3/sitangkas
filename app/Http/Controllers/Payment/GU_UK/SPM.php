<?php

namespace App\Http\Controllers\Payment\GU_UK;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\GU_UK as PaymentGU_UK;
use App\Models\UnitKerja;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SPM extends Controller
{
    private const PAYMENT_TYPE = 'GU_UK';

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

        if (! empty($this->csvToArray($row->status))
            || ! empty($this->csvToArray($row->status_sptjm))
            || ! empty($this->csvToArray($row->status_sp_pengajuan))
            || ! empty($this->csvToArray($row->status_sp))) {
            return $badge('btn-success', 'fas fa-file-signature', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function index()
    {
        return view('Payment.GU_UK.spm');
    }

    public function json(ActivePositionService $activePosition)
    {
        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = $user->unitKerja?->id;
        $submitExpr = "REPLACE(COALESCE(spm.submit,''), ' ', '')";

        $query = PaymentGU_UK::rootQueryAlias('spm')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'spm.id_unit_kerja')
            ->leftJoin('document as lpj', function ($join) {
                $join->on('lpj.id', '=', 'spm.reference_id')
                    ->where('lpj.src_type', 'LPJ')
                    ->where('lpj.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('lpj.deleted_at');
            })
            ->leftJoin('document as spp', function ($join) {
                $join->on('spp.reference_id', '=', 'lpj.id')
                    ->where('spp.src_type', 'SPP')
                    ->where('spp.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('spp.deleted_at');
            })
            ->leftJoin('document as sptjm', function ($join) {
                $join->on('sptjm.reference_id', '=', 'lpj.id')
                    ->where('sptjm.src_type', 'SPTJM')
                    ->where('sptjm.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('sptjm.deleted_at');
            })
            ->leftJoin('document as sp', function ($join) {
                $join->on('sp.reference_id', '=', 'lpj.id')
                    ->where('sp.src_type', 'SP')
                    ->where('sp.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('sp.deleted_at');
            })
            ->leftJoin('document as sp_pengajuan', function ($join) {
                $join->on('sp_pengajuan.reference_id', '=', 'lpj.id')
                    ->where('sp_pengajuan.src_type', 'SP_PENGAJUAN')
                    ->where('sp_pengajuan.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('sp_pengajuan.deleted_at');
            })
            ->where('spm.src_type', 'SPM')
            ->where('spm.payment_type', self::PAYMENT_TYPE)
            ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($unitKerjaId, $jabatanId, $submitExpr) {
                if ($jabatanId === 7) {
                    $q->where('spm.id_unit_kerja', $unitKerjaId);
                } elseif ($jabatanId === 5) {
                    $q->where(function ($scope) use ($unitKerjaId) {
                        $scope->where('spm.id_unit_kerja', $unitKerjaId)
                            ->orWhere('uk.skpd_id', $unitKerjaId);
                    });
                    $q->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['7']);
                } elseif ($jabatanId === 4) {
                    $q->whereRaw("FIND_IN_SET(?, {$submitExpr})", ['5']);
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
                'lpj.id as id_lpj',
                'lpj.nomor as nomor_lpj',
                'lpj.uraian',
                'lpj.nominal',
                'spp.nomor as nomor_spp',
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

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status_button', function ($row) use ($jabatanId) {
                $enc = EncryptedId::encode($row->id_lpj ?: $row->id);
                $statusSpm = $this->csvToArray($row->status);
                $statusSptjm = $this->csvToArray($row->status_sptjm);
                $statusSpPengajuan = $this->csvToArray($row->status_sp_pengajuan);
                $submitArr = $this->csvToArray($row->submit);

                if ($jabatanId === 13) {
                    return $this->auditorStatusBadge($row);
                }

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

                if (! is_null($row->rejected_by)) {
                    $label = match ((int) $row->rejected_by) {
                        5 => 'Ditolak PA',
                        7 => 'Ditolak PPK',
                        4 => 'Ditolak Verifikator',
                        default => 'Ditolak',
                    };

                    return '<span type="button" class="btn btn-sm btn-danger show-document" data-id="'.$enc.'" data-wenk="'.e((string) $row->notes).'" data-wenk-color="red" data-toggle="modal" data-target="#FormTTE"><i class="far fa-file-excel"></i> '.$label.'</span>';
                }

                if (! is_null($row->verify)) {
                    return '<span type="button" class="btn btn-sm btn-success show-document" data-id="'.$enc.'" data-wenk="Telah Verifikasi" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-user-check"></i> Telah Verifikasi</span>';
                }

                if (! in_array($jabatanId, [4, 5, 7], true)) {
                    return '<span type="button" class="btn btn-sm btn-info show-document" data-id="'.$enc.'" data-wenk="Tampilkan Dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>';
                }

                if ($jabatanId === 7) {
                    if (in_array('7', $submitArr, true)) {
                        return '<span type="button" class="btn btn-sm btn-primary show-document" data-id="'.$enc.'" data-wenk="Telah Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-paper-plane"></i> Telah Submit</span>';
                    }

                    if (! empty($row->status_sp) && in_array('7', $this->csvToArray($row->status_sp), true)) {
                        return '<span type="button" class="btn btn-sm btn-secondary show-document" data-status="1" data-id="'.$enc.'" data-wenk="Sudah TTE" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-id="'.$enc.'" data-wenk="Belum TTE" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum TTE</span>';
                }

                if ($jabatanId === 4) {
                    return '<span type="button" class="btn btn-sm btn-warning show-document" data-id="'.$enc.'" data-wenk="Belum Terverifikasi" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-triangle-exclamation"></i> Belum Verifikasi</span>';
                }

                $paSignedAll = in_array('5', $statusSpm, true)
                    && in_array('5', $statusSptjm, true)
                    && in_array('5', $statusSpPengajuan, true);

                if (in_array('5', $submitArr, true)) {
                    return '<span type="button" class="btn btn-sm btn-primary show-document" data-id="'.$enc.'" data-wenk="Telah Submit" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-paper-plane"></i> Telah Submit</span>';
                }

                if ($jabatanId === 5 && $paSignedAll) {
                    return '<span type="button" class="btn btn-sm btn-secondary show-document" data-status="1" data-id="'.$enc.'" data-wenk="Sudah TTE" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                }

                return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-id="'.$enc.'" data-wenk="Belum TTE" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum TTE</span>';
            })
            ->addColumn('action', function ($row) use ($btn, $jabatanId) {
                $enc = EncryptedId::encode($row->id);
                $encRef = EncryptedId::encode($row->id_lpj ?: $row->id);
                $actions = [];
                $submitArr = $this->csvToArray($row->submit);
                $statusSpm = $this->csvToArray($row->status);
                $statusSptjm = $this->csvToArray($row->status_sptjm);
                $statusSpPengajuan = $this->csvToArray($row->status_sp_pengajuan);
                $paSignedAll = in_array('5', $statusSpm, true)
                    && in_array('5', $statusSptjm, true)
                    && in_array('5', $statusSpPengajuan, true);

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

                if (! in_array($jabatanId, [4, 5, 7], true)) {
                    $actions[] = $btn($encRef, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                }

                if ($jabatanId === 7 && ! is_null($row->rejected_by)) {
                    $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                    $actions[] = $btn($enc, 'delete', 'fas fa-trash text-danger', 'Hapus', 'data-payment="GU_UK" data-type="SPM"');
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
                    $actions[] = $btn($enc, 'delete', 'fas fa-trash text-danger', 'Hapus', 'data-payment="GU_UK" data-type="SPM"');
                }

                if (
                    $jabatanId === 5 &&
                    is_null($row->rejected_by) &&
                    in_array('7', $submitArr, true) &&
                    ! in_array('5', $submitArr, true) &&
                    $paSignedAll
                ) {
                    $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                }

                if (
                    $jabatanId === 5 &&
                    is_null($row->rejected_by) &&
                    in_array('7', $submitArr, true) &&
                    ! in_array('5', $submitArr, true)
                ) {
                    $actions[] = $btn(
                        $enc,
                        'denied',
                        'far fa-file-excel text-danger',
                        'Menolak Data',
                        'data-payment="GU_UK" data-type="SPM"'
                    );
                }

                if (
                    $jabatanId === 4 &&
                    is_null($row->rejected_by) &&
                    is_null($row->verify) &&
                    in_array('5', $submitArr, true)
                ) {
                    $actions[] = $btn($enc, 'verify_data', 'fas fa-user-check text-success', 'Verifikasi');
                    $actions[] = $btn(
                        $enc,
                        'denied',
                        'far fa-file-excel text-danger',
                        'Menolak Data',
                        'data-payment="GU_UK" data-type="SPM"'
                    );
                }

                $actions[] = $btn($encRef, 'history_data', 'ni ni-collection text-info', 'History');

                return implode('', $actions);
            })
            ->rawColumns(['status_button', 'action'])
            ->make(true);
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        try {
            $spmId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! in_array((int) $user->jabatan->id, [1, 7, 5], true)) {
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
                    $newSubmit = $spm->submit ? $spm->submit.',7' : '7';
                    $assignedTo = '5';
                } else {
                    if (! in_array('7', $submitArr, true)) {
                        throw new \RuntimeException('Dokumen belum disubmit oleh PPK.');
                    }
                    if (in_array('5', $submitArr, true)) {
                        throw new \RuntimeException('Dokumen sudah disubmit oleh PA.');
                    }
                    if (
                        ! $this->hasStatusForJabatan($spm->status, 5) ||
                        ! $sptjm || ! $this->hasStatusForJabatan($sptjm->status, 5) ||
                        ! $spPengajuan || ! $this->hasStatusForJabatan($spPengajuan->status, 5)
                    ) {
                        throw new \RuntimeException('Dokumen SPM, SPTJM, dan SP Pengajuan belum TTE lengkap oleh PA.');
                    }
                    $newSubmit = $spm->submit ? $spm->submit.',5' : '5';
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

            return response()->json([
                'status' => 200,
                'message' => (int) $user->jabatan->id === 7
                    ? 'SPM berhasil disubmit ke PA.'
                    : 'SPM berhasil disubmit ke verifikator.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['status' => 500, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            return DataTables::of(collect())->make(true);
        }

        $unitKerjaId = $user->unitKerja?->id;
        $isEdited = $request->edited === 'true';
        $spmId = null;
        if ($request->data) {
            try {
                $spmId = EncryptedId::decode($request->data);
            } catch (\Throwable) {
                return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
            }
        }

        $currentLpjId = null;
        if ($spmId) {
            $currentLpjId = Document::query()
                ->where('id', $spmId)
                ->where('src_type', 'SPM')
                ->where('payment_type', self::PAYMENT_TYPE)
                ->whereNull('deleted_at')
                ->value('reference_id');

            if (! $currentLpjId) {
                return response()->json(['status' => 404, 'message' => 'Referensi LPJ tidak ditemukan.'], 404);
            }
        }

        $query = PaymentGU_UK::rootQueryAlias('lpj')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'lpj.id_unit_kerja')
            ->leftJoin('document as spp', function ($join) {
                $join->on('spp.reference_id', '=', 'lpj.id')
                    ->where('spp.src_type', 'SPP')
                    ->where('spp.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('spp.deleted_at');
            })
            ->where('lpj.src_type', 'LPJ')
            ->where('lpj.payment_type', self::PAYMENT_TYPE)
            ->whereNull('lpj.rejected_by')
            ->where('lpj.verify', 1)
            ->where(function ($q) use ($unitKerjaId) {
                $q->where('lpj.id_unit_kerja', $unitKerjaId)
                    ->orWhere('uk.skpd_id', $unitKerjaId);
            })
            ->where(function ($q) use ($isEdited, $currentLpjId) {
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('document as spm')
                        ->whereColumn('spm.reference_id', 'lpj.id')
                        ->where('spm.src_type', 'SPM')
                        ->where('spm.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('spm.deleted_at');
                });

                if ($isEdited && $currentLpjId) {
                    $q->orWhere('lpj.id', $currentLpjId);
                }
            })
            ->select([
                'lpj.id',
                'lpj.nomor as nomor_lpj',
                'lpj.src_name as src_name_lpj',
                'lpj.nominal',
                'lpj.uraian',
                'lpj.rejected_by',
                'lpj.created_at',
                'uk.nama as unit_kerja',
                'spp.nomor as nomor_spp',
            ])
            ->orderByDesc('lpj.created_at');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status', function ($data) {
                $url = '/File_LPJ/'.$data->src_name_lpj;

                return '<button type="button" class="btn btn-sm btn-info show-document" data-id="'.EncryptedId::encode($data->id).'" data-wenk="Klik untuk menampilkan dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</button>';
            })
            ->addColumn('action', function ($data) use ($currentLpjId) {
                $id = EncryptedId::encode($data->id);
                $checked = ($currentLpjId && (int) $currentLpjId === (int) $data->id) ? 'checked' : '';
                $deniedButton = $checked ? '' : '<button type="button" class="btn btn-outline-danger denied" value="'.$id.'" data-wenk="Menolak data" data-payment="GU_UK" data-type="LPJ" data-wenk-color="red"><i class="fa-solid fa-ban"></i></button>';

                return '<div class="d-flex justify-content-center"><div class="btn-group btn-group-sm"><input type="radio" class="btn-check" name="selected_lpj" id="lpj_'.$id.'" value="'.$id.'" '.$checked.'><label class="btn btn-outline-primary" for="lpj_'.$id.'" data-wenk="Pilih data" data-wenk-color="green"><i class="fa-solid fa-check"></i></label>'.$deniedButton.'</div></div>';
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function store(Request $request, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $request->validate([
            'selected_lpj' => ['required', 'string'],
            'nomor_spm' => ['required', 'string', 'max:255'],
            'nomor_sptjm' => ['required', 'string', 'max:255'],
            'file_spm' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sptjm' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp_pengajuan' => ['required', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $lpjId = EncryptedId::decode($request->selected_lpj);
        } catch (\Throwable) {
            return response()->json(['status' => 400, 'message' => 'Data LPJ tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat data SPM.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;
        $unitKerjaId = (int) $user->unitKerja->id;

        try {
            DB::transaction(function () use ($request, $lpjId, $actorId, $unitKerjaId, &$storedFiles, $documentHistoryService, $user) {
                $this->assertLpjAvailability($lpjId, null, $user);

                $spmFile = $this->storeFile($request->file('file_spm'), '/File_SPM', $storedFiles);
                $sptjmFile = $this->storeFile($request->file('file_sptjm'), '/File_SPTJM', $storedFiles);
                $spFile = $this->storeFile($request->file('file_sp'), '/File_SP', $storedFiles);
                $spPengajuanFile = $this->storeFile($request->file('file_sp_pengajuan'), '/File_SP_PENGAJUAN', $storedFiles);

                $spm = Document::create([
                    'nomor' => $request->input('nomor_spm'),
                    'src_name' => $spmFile,
                    'src_type' => 'SPM',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $lpjId,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $actorId,
                    'assigned_to' => '9',
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
                        'reference_id' => $lpjId,
                        'id_unit_kerja' => $unitKerjaId,
                        'uploaded_by' => $actorId,
                        'assigned_to' => '9',
                        'created_at' => now(),
                    ]);
                    $documentHistoryService->upload($doc->id, $doc->src_name, $doc->id_unit_kerja);
                }

                $documentHistoryService->upload($spm->id, $spm->src_name, $spm->id_unit_kerja);
            });

            return response()->json(['status' => 200, 'message' => 'Data SPM berhasil disimpan']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal menyimpan data SPM'], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        try {
            $spmId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
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
            return response()->json(['status' => 404, 'message' => 'Data SPM tidak ditemukan.'], 404);
        }

        $children = Document::query()
            ->where('reference_id', $spm->reference_id)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereIn('src_type', ['SP', 'SPTJM', 'SP_PENGAJUAN'])
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('src_type');

        return response()->json([
            'status' => 200,
            'data' => [
                'spm' => $spm,
                'sp' => $children->get('SP'),
                'sptjm' => $children->get('SPTJM'),
                'sp_pengajuan' => $children->get('SP_PENGAJUAN'),
                'selected_lpj' => EncryptedId::encode((int) $spm->reference_id),
            ],
        ]);
    }

    public function update(Request $request, string $id, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $request->validate([
            'selected_lpj' => ['required', 'string'],
            'nomor_spm' => ['required', 'string', 'max:255'],
            'nomor_sptjm' => ['required', 'string', 'max:255'],
            'file_spm' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sptjm' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_sp_pengajuan' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        try {
            $spmId = EncryptedId::decode($id);
            $lpjId = EncryptedId::decode($request->selected_lpj);
        } catch (\Throwable) {
            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }
        if (! $this->canCrud($user)) {
            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang memperbarui data SPM.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;

        try {
            DB::transaction(function () use ($request, $spmId, $lpjId, $actorId, &$storedFiles, $documentHistoryService, $user) {
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

                $oldLpjReferenceId = (int) $spm->reference_id;
                $this->assertLpjAvailability($lpjId, $spm->id, $user);

                $spmPayload = [
                    'nomor' => $request->input('nomor_spm'),
                    'reference_id' => $lpjId,
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'verify' => null,
                    'users_to' => null,
                    'assigned_to' => '9',
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_spm')) {
                    $spmPayload['src_name'] = $this->storeFile($request->file('file_spm'), '/File_SPM', $storedFiles);
                    $spmPayload['uploaded_by'] = $actorId;
                    $spmPayload['status'] = null;
                }

                $spm->update($spmPayload);
                $documentHistoryService->edited($spm->id, $spm->src_name, $spm->id_unit_kerja);

                $this->updateChildDocument($spm, $oldLpjReferenceId, $lpjId, 'SPTJM', $request->input('nomor_sptjm'), $request->file('file_sptjm'), '/File_SPTJM', $actorId, $storedFiles, $documentHistoryService);
                $this->updateChildDocument($spm, $oldLpjReferenceId, $lpjId, 'SP', null, $request->file('file_sp'), '/File_SP', $actorId, $storedFiles, $documentHistoryService);
                $this->updateChildDocument($spm, $oldLpjReferenceId, $lpjId, 'SP_PENGAJUAN', null, $request->file('file_sp_pengajuan'), '/File_SP_PENGAJUAN', $actorId, $storedFiles, $documentHistoryService);
            });

            return response()->json(['status' => 200, 'message' => 'Data SPM berhasil diperbarui']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            report($e);

            return response()->json(['status' => 400, 'message' => 'Gagal memperbarui data SPM'], 400);
        }
    }

    private function updateChildDocument(Document $spm, int $oldLpjReferenceId, int $newLpjReferenceId, string $srcType, ?string $nomor, $file, string $directory, int $actorId, array &$storedFiles, DocumentHistoryService $documentHistoryService): void
    {
        $doc = Document::query()
            ->where('reference_id', $oldLpjReferenceId)
            ->where('src_type', $srcType)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (! $doc && $oldLpjReferenceId !== $newLpjReferenceId) {
            $doc = Document::query()
                ->where('reference_id', $newLpjReferenceId)
                ->where('src_type', $srcType)
                ->where('payment_type', self::PAYMENT_TYPE)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
        }

        $payload = [
            'nomor' => $nomor,
            'reference_id' => $newLpjReferenceId,
            'rejected_by' => null,
            'notes' => null,
            'submit' => null,
            'verify' => null,
            'users_to' => null,
            'assigned_to' => '9',
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
            'reference_id' => $newLpjReferenceId,
            'id_unit_kerja' => $spm->id_unit_kerja,
            'uploaded_by' => $actorId,
            'created_at' => now(),
        ]));

        $documentHistoryService->upload($doc->id, (string) $doc->src_name, $doc->id_unit_kerja);
    }

    private function assertLpjAvailability(int $lpjId, ?int $currentSpmId, $user): void
    {
        $lpj = Document::query()
            ->where('id', $lpjId)
            ->where('src_type', 'LPJ')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->whereNull('rejected_by')
            ->where('verify', 1)
            ->where(function ($q) use ($user) {
                $unitKerjaId = $user->unitKerja?->id;
                $q->where('id_unit_kerja', $unitKerjaId);

                if ($unitKerjaId) {
                    $q->orWhereIn('id_unit_kerja', function ($sub) use ($unitKerjaId) {
                        $sub->select('id')
                            ->from('unit_kerjas')
                            ->where('skpd_id', $unitKerjaId);
                    });
                }
            })
            ->lockForUpdate()
            ->first();

        if (! $lpj) {
            throw new \RuntimeException('Data LPJ tidak valid atau belum diverifikasi.');
        }

        $used = Document::query()
            ->where('src_type', 'SPM')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $lpjId)
            ->whereNull('deleted_at')
            ->when($currentSpmId, fn ($q) => $q->where('id', '!=', $currentSpmId))
            ->exists();

        if ($used) {
            throw new \RuntimeException('Data LPJ sudah digunakan pada dokumen SPM lain.');
        }
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
        if (! $user || ! $user->jabatan) {
            return false;
        }

        return (int) $user->jabatan->id === 7;
    }
}
