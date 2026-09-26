<?php

namespace App\Http\Controllers\Payment\TU;

use App\Http\Controllers\Controller;
use App\Models\AnggaranKegiatan;
use App\Models\AnggaranKegiatanTemp;
use App\Models\Document;
use App\Models\Payment\TU as PaymentTU;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SPP extends Controller
{
    public function __construct(private readonly PositionIdentityResolver $positionIdentityResolver) {}

    private const PAYMENT_TYPE = 'TU';

    private const SRC_TYPE = 'SPP';

    private const FILE_DIR = '/File_SPP';

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
                8 => 'Ditolak PPTK',
                9 => 'Ditolak BP',
                10 => 'Ditolak BPP',
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

        if (! empty($this->csvToArray($row->status))) {
            return $badge('btn-secondary', 'fas fa-file-signature', 'Sudah TTE');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum TTE');
    }

    public function index()
    {
        return view('Payment.TU.spp');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel('payment_tu')->warning('SPP TU json blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $unitKerjaId = (int) $user->unitKerja->id;
            $actorPptkId = (int) $this->positionIdentityResolver->pptkActorPosition($user)->getKey();
            $assignedExpr = "REPLACE(COALESCE(spp.assigned_to,''), ' ', '')";
            $submitExpr = "REPLACE(COALESCE(spp.submit,''), ' ', '')";

            Log::channel('payment_tu')->debug('SPP TU json request');

            $query = PaymentTU::rootQueryAlias('spp')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'spp.id_unit_kerja')
                ->leftJoin('document as pengajuan', function ($join) {
                    $join->whereRaw('(pengajuan.id = spp.reference_id OR pengajuan.reference_id = spp.id)')
                        ->where('pengajuan.src_type', 'PENGAJUAN')
                        ->where('pengajuan.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('pengajuan.deleted_at');
                })
                ->where('spp.src_type', self::SRC_TYPE)
                ->where('spp.payment_type', self::PAYMENT_TYPE)
                ->when(! in_array($jabatanId, [1, 13], true), function ($q) use ($jabatanId, $unitKerjaId, $actorPptkId, $assignedExpr, $submitExpr) {
                    if (! in_array($jabatanId, [9, 10, 8, 5, 6, 7], true)) {
                        $q->whereRaw('1 = 0');

                        return;
                    }

                    if (in_array($jabatanId, [9, 10], true)) {
                        $q->where('spp.id_unit_kerja', $unitKerjaId);

                        $q->where(function ($scope) use ($jabatanId, $assignedExpr, $submitExpr) {
                            $scope->whereRaw("FIND_IN_SET(?, {$assignedExpr})", [(string) $jabatanId])
                                ->orWhereRaw("FIND_IN_SET(?, {$submitExpr})", [(string) $jabatanId]);
                        });

                        return;
                    }

                    if ($jabatanId === 8) {
                        $q->where('spp.id_unit_kerja', $unitKerjaId);

                        $q->where(function ($scope) use ($assignedExpr, $submitExpr, $actorPptkId) {
                            $scope->whereIn('spp.users_to', $this->positionIdentityResolver->equivalentIds($actorPptkId))
                                ->where(function ($ownedFlow) use ($assignedExpr, $submitExpr) {
                                    $ownedFlow->whereRaw("FIND_IN_SET('8', {$assignedExpr})")
                                        ->orWhereRaw("FIND_IN_SET('8', {$submitExpr})");
                                });
                        });

                        return;
                    }

                    if ($jabatanId === 5) {
                        $q->where('spp.id_unit_kerja', $unitKerjaId);

                        $q->where(function ($scope) use ($assignedExpr, $submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('5', {$assignedExpr})")
                                ->orWhereRaw("FIND_IN_SET('5', {$submitExpr})");
                        });

                        return;
                    }

                    if ($jabatanId === 6) {
                        $q->where('spp.id_unit_kerja', $unitKerjaId);
                        $q->where(function ($scope) use ($assignedExpr, $submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('6', {$assignedExpr})")
                                ->orWhereRaw("FIND_IN_SET('6', {$submitExpr})");
                        });

                        return;
                    }

                    if ($jabatanId === 7) {
                        $q->where(function ($qs) use ($unitKerjaId) {
                            $qs->where('spp.id_unit_kerja', $unitKerjaId)
                                ->orWhere('uk.skpd_id', $unitKerjaId);
                        });
                        $q->where(function ($scope) use ($assignedExpr, $submitExpr) {
                            $scope->whereRaw("FIND_IN_SET('7', {$assignedExpr})")
                                ->orWhere(function ($history) use ($submitExpr) {
                                    $history->whereNotNull('spp.verify')
                                        ->where(function ($flow) use ($submitExpr) {
                                            $flow->whereRaw("FIND_IN_SET('5', {$submitExpr})")
                                                ->orWhereRaw("FIND_IN_SET('6', {$submitExpr})");
                                        });
                                });
                        });
                    }
                })
                ->select([
                    'spp.id',
                    'spp.reference_id',
                    'spp.nomor as nomor_spp',
                    'spp.uraian',
                    'spp.src_name as src_name_spp',
                    'spp.status',
                    'spp.submit',
                    'spp.assigned_to',
                    'spp.users_to',
                    'spp.verify',
                    'spp.uploaded_by',
                    'spp.rejected_by',
                    'spp.notes',
                    'spp.nominal',
                    'spp.created_at',
                    'uk.nama as unit_kerja',
                    'pengajuan.nomor as nomor_pengajuan',
                ])
                ->orderByDesc('spp.created_at');

            $btn = static function (string $value, string $class, string $icon, string $title, string $extra = ''): string {
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
                ->addColumn('status_button', function ($row) use ($jabatanId, $actorPptkId) {
                    $encRef = EncryptedId::encode($row->id);
                    $fileUrl = ! is_null($row->status)
                        ? '/File_SPP/signs/'.$row->src_name_spp
                        : '/File_SPP/'.$row->src_name_spp;
                    $submitArr = $this->csvToArray($row->submit);
                    $statusArr = $this->csvToArray($row->status);
                    $assignedArr = $this->csvToArray($row->assigned_to);
                    $submitCount = array_count_values($submitArr);
                    $signedByViewer = in_array((string) $jabatanId, $statusArr, true);
                    $submittedByViewer = in_array((string) $jabatanId, $submitArr, true);
                    $isAssignedToOtherPptk = $jabatanId === 8
                        && in_array('8', $assignedArr, true)
                        && ! is_null($row->users_to)
                        && ! $this->positionIdentityResolver->contains($actorPptkId, (int) $row->users_to);
                    $isFlowWithBpp = in_array('10', $submitArr, true) || in_array('10', $assignedArr, true);
                    $canSubmit = $this->canSubmitByFlow($jabatanId, $submitCount, $submitArr, $signedByViewer, $isFlowWithBpp);

                    if ($jabatanId === 13) {
                        return $this->auditorStatusBadge($row);
                    }

                    if (! is_null($row->rejected_by)) {
                        $label = match ((int) $row->rejected_by) {
                            5 => 'Ditolak PA',
                            6 => 'Ditolak KPA',
                            7 => 'Ditolak PPK',
                            8 => 'Ditolak PPTK',
                            9 => 'Ditolak BP',
                            10 => 'Ditolak BPP',
                            default => 'Ditolak',
                        };

                        return '<span type="button" class="btn btn-sm btn-danger show-document"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="'.e((string) $row->notes).'"'
                            .' data-wenk-color="red"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="far fa-file-excel"></i> '.$label.'</span>';
                    }

                    if ($jabatanId === 7) {
                        if (! is_null($row->verify)) {
                            return '<span type="button" class="btn btn-sm btn-success show-document"'
                                .' data-id="'.$encRef.'"'
                                .' data-wenk="Telah Verifikasi"'
                                .' data-wenk-color="green"'
                                .' data-toggle="modal" data-target="#FormTTE">'
                                .'<i class="fas fa-user-check"></i> Telah Verifikasi</span>';
                        }

                        return '<span type="button" class="btn btn-sm btn-warning show-document"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="Belum Verifikasi"'
                            .' data-wenk-color="orange"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-triangle-exclamation"></i> Belum Verifikasi</span>';
                    }

                    if ($isAssignedToOtherPptk) {
                        return '<span type="button" class="btn btn-sm btn-info show-document"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="Tampilkan Dokumen"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                    }

                    if ($submittedByViewer && ! $canSubmit) {
                        return '<span type="button" class="btn btn-sm btn-primary show-document"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="Telah Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-paper-plane"></i> Telah Submit</span>';
                    }

                    if ($canSubmit) {
                        return '<span type="button" class="btn btn-sm btn-secondary show-document"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="Belum Submit"'
                            .' data-wenk-color="blue"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-hourglass-half"></i> Belum Submit</span>';
                    }

                    if (! $signedByViewer && in_array($jabatanId, [9, 10, 8, 5, 6], true)) {
                        return '<span type="button" class="btn btn-sm btn-warning show-document"'
                            .' data-status="0"'
                            .' data-id="'.$encRef.'"'
                            .' data-wenk="Belum TTE"'
                            .' data-wenk-color="orange"'
                            .' data-toggle="modal" data-target="#FormTTE">'
                            .'<i class="fas fa-file-signature"></i> Belum TTE</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-info show-document"'
                        .' data-id="'.$encRef.'"'
                        .' data-wenk="Tampilkan Dokumen"'
                        .' data-wenk-color="blue"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                })
                ->addColumn('action', function ($row) use ($btn, $jabatanId, $actorPptkId) {
                    $enc = EncryptedId::encode($row->id);
                    $actions = [];
                    $submitArr = $this->csvToArray($row->submit);
                    $statusArr = $this->csvToArray($row->status);
                    $submitCount = array_count_values($submitArr);
                    $assignedArr = $this->csvToArray($row->assigned_to);
                    $signedByViewer = in_array((string) $jabatanId, $statusArr, true);
                    $isRejected = ! is_null($row->rejected_by);
                    $isAssignedToOtherPptk = $jabatanId === 8
                        && in_array('8', $assignedArr, true)
                        && ! is_null($row->users_to)
                        && ! $this->positionIdentityResolver->contains($actorPptkId, (int) $row->users_to);
                    $isFlowWithBpp = in_array('10', $submitArr, true) || in_array('10', $assignedArr, true);
                    $canSubmit = $this->canSubmitByFlow($jabatanId, $submitCount, $submitArr, $signedByViewer, $isFlowWithBpp);

                    if ($jabatanId === 13) {
                        $actions[] = $btn($enc, 'show-document', 'fa-solid fa-eye text-primary', 'Detail Dokumen', 'data-id="'.$enc.'"');
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if (in_array($jabatanId, [9, 10], true)) {
                        if ($isRejected || (($submitCount[(string) $jabatanId] ?? 0) === 0)) {
                            $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                            $actions[] = $btn(
                                $enc,
                                'delete',
                                'fas fa-trash text-danger',
                                'Hapus',
                                'data-payment="TU" data-type="SPP"'
                            );
                        }

                        if (! $isRejected && $canSubmit) {
                            if (($submitCount[(string) $jabatanId] ?? 0) === 0) {
                                $actions[] = $btn($enc, 'submit_pptk', 'ni ni-send text-success', 'Submit PPTK');
                            } else {
                                $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                            }
                            if (($submitCount[(string) $jabatanId] ?? 0) === 1) {
                                $actions[] = $btn(
                                    $enc,
                                    'denied',
                                    'far fa-file-excel text-danger',
                                    'Menolak Data',
                                    'data-payment="TU" data-type="SPP"'
                                );
                            }
                        }
                    }

                    if ($jabatanId === 8 && ! $isRejected && ! $isAssignedToOtherPptk) {
                        if ($canSubmit) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }

                        if (($submitCount['8'] ?? 0) === 0 && (in_array('9', $submitArr, true) || in_array('10', $submitArr, true))) {
                            $actions[] = $btn(
                                $enc,
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                'data-payment="TU" data-type="SPP"'
                            );
                        }
                    }

                    if (in_array($jabatanId, [5, 6], true) && ! $isRejected) {
                        if ($canSubmit) {
                            $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                        }

                        if (
                            (($jabatanId === 5 && in_array('8', $submitArr, true) && in_array('9', $submitArr, true) && ($submitCount['5'] ?? 0) === 0)) ||
                            (($jabatanId === 6 && in_array('8', $submitArr, true) && in_array('10', $submitArr, true) && ($submitCount['6'] ?? 0) === 0))
                        ) {
                            $actions[] = $btn(
                                $enc,
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                'data-payment="TU" data-type="SPP"'
                            );
                        }
                    }

                    if (
                        $jabatanId === 7 &&
                        ! $isRejected &&
                        is_null($row->verify) &&
                        (
                            (in_array('9', $submitArr, true) && ($submitCount['9'] ?? 0) >= 2 && in_array('5', $submitArr, true)) ||
                            (in_array('10', $submitArr, true) && ($submitCount['10'] ?? 0) >= 2 && in_array('6', $submitArr, true))
                        )
                    ) {
                        $actions[] = $btn(
                            $enc,
                            'verify_data',
                            'fas fa-user-check text-success',
                            'Verifikasi',
                            'data-payment="TU" data-type="SPP"'
                        );
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="TU" data-type="SPP"'
                        );
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status_button', 'action'])
                ->make(true);

            Log::channel('payment_tu')->debug('SPP TU json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('SPP TU json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat data SPP.',
            ], 500);
        }
    }

    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan || ! $user->unitKerja) {
                Log::channel('payment_tu')->warning('SPP TU formJson blocked: invalid active position', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            if (! in_array((int) $user->jabatan->id, [1, 9, 10], true)) {
                Log::channel('payment_tu')->warning('SPP TU formJson blocked: forbidden role', [
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return DataTables::of(collect())->make(true);
            }

            $isEdited = $request->edited === 'true';
            $currentReferenceId = null;

            Log::channel('payment_tu')->debug('SPP TU formJson request', [
                'edited' => $isEdited,
                'has_data' => filled($request->data),
            ]);

            if ($request->data) {
                try {
                    $sppId = EncryptedId::decode($request->data);
                } catch (\Throwable $e) {
                    Log::channel('payment_tu')->warning('SPP TU formJson invalid hash', [
                        'hash' => $request->data,
                        'error' => $e->getMessage(),
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
                }

                $currentReferenceId = $this->resolveLinkedPengajuanId($sppId);
            }

            $query = PaymentTU::rootQueryAlias('document')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'document.id_unit_kerja')
                ->where('document.src_type', 'PENGAJUAN')
                ->where('document.payment_type', self::PAYMENT_TYPE)
                ->whereNull('document.rejected_by')
                ->where('document.verify', 1)
                ->whereRaw("FIND_IN_SET('2', REPLACE(COALESCE(document.status,''), ' ', ''))")
                ->whereRaw("FIND_IN_SET('4', REPLACE(COALESCE(document.submit,''), ' ', ''))")
                ->where(function ($q) use ($isEdited, $currentReferenceId) {
                    $q->where(function ($available) {
                        $available->whereNull('document.reference_id')
                            ->whereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('document as spp')
                                    ->whereColumn('spp.reference_id', 'document.id')
                                    ->where('spp.src_type', self::SRC_TYPE)
                                    ->where('spp.payment_type', self::PAYMENT_TYPE)
                                    ->whereNull('spp.deleted_at');
                            });
                    });

                    if ($isEdited && $currentReferenceId) {
                        $q->orWhere('document.id', $currentReferenceId);
                    }
                })
                ->select([
                    'document.id',
                    'document.nomor',
                    'document.src_name',
                    'document.status',
                    'document.created_at',
                    'uk.nama as unit_kerja',
                ])
                ->orderByDesc('document.created_at');

            if ((int) $user->jabatan->id !== 1) {
                $query->where('document.id_unit_kerja', (int) $user->unitKerja->id);
                $query->where(function ($scope) use ($user, $isEdited, $currentReferenceId) {
                    $scope->whereRaw(
                        "FIND_IN_SET(?, REPLACE(COALESCE(document.assigned_to,''), ' ', ''))",
                        [(string) $user->jabatan->id]
                    );

                    if ($isEdited && $currentReferenceId) {
                        $scope->orWhere('document.id', $currentReferenceId);
                    }
                });
            }

            $response = DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($row) {
                    $id = EncryptedId::encode($row->id);
                    $url = ! is_null($row->status)
                        ? '/File_PENGAJUAN/signs/'.$row->src_name
                        : '/File_PENGAJUAN/'.$row->src_name;

                    return '<span type="button" class="btn btn-sm btn-info show-document"'
                        .' data-id="'.$id.'"'
                        .' data-wenk="Klik untuk menampilkan dokumen"'
                        .' data-wenk-color="blue"'
                        .' data-toggle="modal" data-target="#FormTTE">'
                        .'<i class="fa-solid fa-eye"></i> Tampilkan</span>';
                })
                ->addColumn('action', function ($row) use ($isEdited, $currentReferenceId) {
                    $id = EncryptedId::encode($row->id);
                    $checked = ($isEdited && (int) $currentReferenceId === (int) $row->id) ? 'checked' : '';

                    return '<div class="d-flex justify-content-center"><div class="btn-group btn-group-sm">'
                        .'<input type="radio" class="btn-check" name="selected_pengajuan" id="pengajuan_'.$id.'"'
                        .' value="'.$id.'" '.$checked.'>'
                        .'<label class="btn btn-outline-primary" for="pengajuan_'.$id.'"'
                        .' data-wenk="Pilih data" data-wenk-color="green"><i class="fa-solid fa-check"></i></label>'
                        .'</div></div>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);

            Log::channel('payment_tu')->debug('SPP TU formJson success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('SPP TU formJson failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Gagal memuat daftar pengajuan.',
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
            'selected_pengajuan' => ['required', 'string'],
            'nomor_spp' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string'],
            'file_spp' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'file_bmd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'belanja' => ['nullable', 'string'],
            'rekening' => ['required', 'array', 'min:1'],
            'rekening.*.id' => ['required'],
            'rekening.*.nominal' => ['required'],
        ]);

        Log::channel('payment_tu')->info('SPP TU store request', [
            'nomor' => $request->input('nomor_spp'),
            'has_file' => $request->hasFile('file_spp'),
            'has_bmd' => $request->hasFile('file_bmd'),
            'rekening_count' => count($request->input('rekening', [])),
        ]);

        try {
            $pengajuanId = EncryptedId::decode($request->selected_pengajuan);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPP TU store invalid selected_pengajuan', [
                'hash' => $request->selected_pengajuan,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Data pengajuan tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPP TU store blocked: invalid active position', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        if (! in_array((int) $user->jabatan->id, [1, 9, 10], true)) {
            Log::channel('payment_tu')->warning('SPP TU store blocked: forbidden role', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat SPP.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $actorJabatanId = (int) $user->jabatan->id;
        $createdSppId = null;

        if ($this->requiresBmdFile($request->input('belanja')) && ! $request->hasFile('file_bmd')) {
            Log::channel('payment_tu')->warning('SPP TU store blocked: missing required BMD', [
                'belanja' => $request->input('belanja'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 422,
                'message' => 'File BMD wajib diunggah jika memilih Belanja Modal atau Persediaan.',
            ], 422);
        }

        try {
            DB::transaction(function () use (
                $request,
                $pengajuanId,
                $actorId,
                $actorJabatanId,
                $unitKerjaId,
                &$storedFiles,
                $documentHistoryService,
                &$createdSppId
            ) {
                $pengajuan = $this->assertPengajuanAvailability(
                    $pengajuanId,
                    null,
                    $unitKerjaId,
                    $actorJabatanId
                );
                $nominal = collect($request->rekening)->sum(fn ($r) => (float) ($r['nominal'] ?? 0));

                $sppFile = $this->storeFile($request->file('file_spp'), self::FILE_DIR, $storedFiles);
                $bmdFile = $request->hasFile('file_bmd')
                    ? $this->storeFile($request->file('file_bmd'), '/File_BMD', $storedFiles)
                    : null;

                $spp = Document::create([
                    'nomor' => $request->input('nomor_spp'),
                    'uraian' => $request->input('uraian'),
                    'expenditure_type' => $request->input('belanja'),
                    'src_name' => $sppFile,
                    'src_type' => self::SRC_TYPE,
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $pengajuan->id,
                    'nominal' => $nominal,
                    'id_unit_kerja' => $pengajuan->id_unit_kerja,
                    'uploaded_by' => $actorId,
                    'assigned_to' => (string) $actorJabatanId,
                    'users_to' => null,
                    'created_at' => now(),
                ]);

                $createdSppId = $spp->id;

                if ($bmdFile) {
                    $bmd = Document::create([
                        'src_name' => $bmdFile,
                        'src_type' => 'BMD',
                        'payment_type' => self::PAYMENT_TYPE,
                        'reference_id' => $pengajuan->id,
                        'id_unit_kerja' => $pengajuan->id_unit_kerja,
                        'uploaded_by' => $actorId,
                        'assigned_to' => (string) $actorJabatanId,
                        'created_at' => now(),
                    ]);
                    $documentHistoryService->upload($bmd->id, $bmdFile, $pengajuan->id_unit_kerja);
                }

                $this->syncRekening($request->rekening, $spp->id, (int) $pengajuan->id_unit_kerja);
                $documentHistoryService->upload($spp->id, $sppFile, $pengajuan->id_unit_kerja);
            });

            Log::channel('payment_tu')->info('SPP TU store success', [
                'doc_id' => $createdSppId,
                'nomor' => $request->input('nomor_spp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPP berhasil disimpan']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->info('SPP TU store blocked', [
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->error('SPP TU store failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Gagal menyimpan data SPP'], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);
        Log::channel('payment_tu')->debug('SPP TU edit request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPP TU edit invalid hash', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPP TU edit blocked: invalid active position', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        if (! in_array($jabatanId, [9, 10], true)) {
            Log::channel('payment_tu')->warning('SPP TU edit blocked: forbidden role', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SPP.'], 403);
        }

        $spp = Document::query()
            ->where('id', $id)
            ->where('src_type', self::SRC_TYPE)
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('id_unit_kerja', (int) $user->unitKerja->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $spp) {
            Log::channel('payment_tu')->warning('SPP TU edit not found/forbidden', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 404, 'message' => 'Data SPP tidak ditemukan.'], 404);
        }

        if (! $this->canAccessForEdit($spp, $jabatanId)) {
            Log::channel('payment_tu')->warning('SPP TU edit blocked: forbidden document access', [
                'doc_id' => $spp->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SPP ini.'], 403);
        }

        if (! $this->canEditDocument($spp, $jabatanId)) {
            Log::channel('payment_tu')->warning('SPP TU edit blocked: invalid state', [
                'doc_id' => $spp->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Dokumen SPP yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.',
            ], 409);
        }

        $rekening = AnggaranKegiatan::tahunAktif()
            ->select([
                'nama_sub_unit',
                'id_rekening as kode',
                'nama_rekening as uraian',
                'kode_rekening as rekening',
                'nominal as jumlah',
                'kode_sub_kegiatan as sub_kegiatan_id',
                'pagu',
                DB::raw('(
                    SELECT COALESCE(SUM(nominal), 0)
                    FROM anggaran_kegiatan ag
                    WHERE ag.id_rekening = anggaran_kegiatan.id_rekening
                      AND ag.id_spp != anggaran_kegiatan.id_spp
                      AND ag.deleted_at IS NULL
                ) as realisasi'),
            ])
            ->where('id_spp', $spp->id)
            ->where('id_unit_kerja', $spp->id_unit_kerja)
            ->get();

        $linkedPengajuanId = $this->resolveLinkedPengajuanId((int) $spp->id, $spp->reference_id);
        $bmd = $this->findLinkedBmd($spp, $linkedPengajuanId);

        Log::channel('payment_tu')->debug('SPP TU edit success', [
            'doc_id' => $spp->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return response()->json([
            'status' => 200,
            'data' => [
                'spp' => $spp,
                'bmd' => $bmd,
                'selected_pengajuan' => $linkedPengajuanId ? EncryptedId::encode($linkedPengajuanId) : null,
            ],
            'rekening' => $rekening,
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
            'selected_pengajuan' => ['required', 'string'],
            'nomor_spp' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string'],
            'file_spp' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'file_bmd' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'belanja' => ['nullable', 'string'],
            'rekening' => ['required', 'array', 'min:1'],
            'rekening.*.id' => ['required'],
            'rekening.*.nominal' => ['required'],
        ]);

        Log::channel('payment_tu')->info('SPP TU update request', [
            'hash' => $id,
            'nomor' => $request->input('nomor_spp'),
            'has_file' => $request->hasFile('file_spp'),
            'has_bmd' => $request->hasFile('file_bmd'),
            'rekening_count' => count($request->input('rekening', [])),
        ]);

        try {
            $sppId = EncryptedId::decode($id);
            $pengajuanId = EncryptedId::decode($request->selected_pengajuan);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPP TU update invalid hash', [
                'hash' => $id,
                'selected_pengajuan' => $request->selected_pengajuan,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPP TU update blocked: invalid active position', [
                'doc_id' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        if (! in_array($jabatanId, [9, 10], true)) {
            Log::channel('payment_tu')->warning('SPP TU update blocked: forbidden role', [
                'doc_id' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SPP.'], 403);
        }

        $storedFiles = [];
        $actorId = (int) $user->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $actorJabatanId = (int) $user->jabatan->id;

        try {
            DB::transaction(function () use (
                $request,
                $sppId,
                $pengajuanId,
                $actorId,
                $actorJabatanId,
                $unitKerjaId,
                &$storedFiles,
                $documentHistoryService
            ) {
                $spp = Document::query()
                    ->where('id', $sppId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->where('id_unit_kerja', $unitKerjaId)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spp) {
                    throw new \RuntimeException('Data SPP tidak ditemukan.');
                }

                if (! $this->canAccessForEdit($spp, $actorJabatanId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengubah SPP ini.');
                }

                if (! $this->canEditDocument($spp, $actorJabatanId)) {
                    throw new \RuntimeException('Dokumen SPP yang sudah disubmit tidak dapat diubah kecuali setelah ditolak.');
                }

                $oldPengajuanId = $this->resolveLinkedPengajuanId((int) $spp->id, $spp->reference_id);
                $pengajuan = $this->assertPengajuanAvailability(
                    $pengajuanId,
                    $spp->id,
                    $unitKerjaId,
                    $actorJabatanId,
                    $oldPengajuanId
                );
                $nominal = collect($request->rekening)->sum(fn ($r) => (float) ($r['nominal'] ?? 0));

                $payload = [
                    'reference_id' => $pengajuan->id,
                    'nomor' => $request->input('nomor_spp'),
                    'uraian' => $request->input('uraian'),
                    'expenditure_type' => $request->input('belanja'),
                    'nominal' => $nominal,
                    'id_unit_kerja' => $pengajuan->id_unit_kerja,
                    'rejected_by' => null,
                    'notes' => null,
                    'submit' => null,
                    'verify' => null,
                    'assigned_to' => (string) $actorJabatanId,
                    'users_to' => null,
                    'updated_at' => now(),
                ];

                if ($request->hasFile('file_spp')) {
                    $sppFile = $this->storeFile($request->file('file_spp'), self::FILE_DIR, $storedFiles);
                    $payload['src_name'] = $sppFile;
                    $payload['uploaded_by'] = $actorId;
                    $payload['status'] = null;
                }

                $spp->update($payload);

                $existingBmd = $this->findLinkedBmd($spp, $oldPengajuanId, true);

                if ($this->isTransitioningToRequiredBmd($spp->expenditure_type, $request->input('belanja')) && ! $request->hasFile('file_bmd')) {
                    throw new \RuntimeException('File BMD wajib diunggah karena belanja diubah ke Belanja Modal atau Persediaan.');
                }

                if (
                    $this->requiresBmdFile($request->input('belanja'))
                    && ! $request->hasFile('file_bmd')
                    && ! $existingBmd
                ) {
                    throw new \RuntimeException('File BMD wajib diunggah jika memilih Belanja Modal atau Persediaan.');
                }

                if ($request->hasFile('file_bmd')) {
                    $bmdFile = $this->storeFile($request->file('file_bmd'), '/File_BMD', $storedFiles);
                    if ($existingBmd) {
                        $existingBmd->update([
                            'reference_id' => $pengajuan->id,
                            'src_name' => $bmdFile,
                            'id_unit_kerja' => $pengajuan->id_unit_kerja,
                            'uploaded_by' => $actorId,
                            'assigned_to' => (string) $actorJabatanId,
                            'rejected_by' => null,
                            'notes' => null,
                            'submit' => null,
                            'verify' => null,
                            'status' => null,
                            'updated_at' => now(),
                        ]);
                        $bmd = $existingBmd;
                    } else {
                        $bmd = Document::create([
                            'src_name' => $bmdFile,
                            'src_type' => 'BMD',
                            'payment_type' => self::PAYMENT_TYPE,
                            'reference_id' => $pengajuan->id,
                            'id_unit_kerja' => $pengajuan->id_unit_kerja,
                            'uploaded_by' => $actorId,
                            'assigned_to' => (string) $actorJabatanId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $documentHistoryService->edited($bmd->id, $bmd->src_name, $bmd->id_unit_kerja);
                } elseif ($existingBmd && $oldPengajuanId !== (int) $pengajuan->id) {
                    $existingBmd->update([
                        'reference_id' => $pengajuan->id,
                        'id_unit_kerja' => $pengajuan->id_unit_kerja,
                        'assigned_to' => (string) $actorJabatanId,
                        'rejected_by' => null,
                        'notes' => null,
                        'submit' => null,
                        'verify' => null,
                        'updated_at' => now(),
                    ]);
                    $documentHistoryService->edited($existingBmd->id, (string) $existingBmd->src_name, $existingBmd->id_unit_kerja);
                }

                $this->syncRekening($request->rekening, $spp->id, (int) $pengajuan->id_unit_kerja);
                $documentHistoryService->edited($spp->id, $spp->src_name, $spp->id_unit_kerja);
            });

            Log::channel('payment_tu')->info('SPP TU update success', [
                'doc_id' => $sppId,
                'nomor' => $request->input('nomor_spp'),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SPP berhasil diperbarui']);
        } catch (\RuntimeException $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->info('SPP TU update blocked', [
                'doc_id' => $sppId,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_tu')->error('SPP TU update failed', [
                'doc_id' => $sppId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Gagal memperbarui data SPP'], 400);
        }
    }

    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        Log::channel('payment_tu')->info('SPP TU submit request', [
            'hash' => $request->id,
        ]);

        try {
            $sppId = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPP TU submit invalid hash', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPP TU submit blocked: invalid active position', [
                'doc_id' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $actorPptkId = (int) $this->positionIdentityResolver->pptkActorPosition($user)->getKey();
        if (! in_array($jabatanId, [1, 9, 10, 8, 5, 6], true)) {
            Log::channel('payment_tu')->warning('SPP TU submit blocked: forbidden role', [
                'doc_id' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan submit SPP.'], 403);
        }

        try {
            DB::transaction(function () use ($sppId, $jabatanId, $unitKerjaId, $actorPptkId, $documentHistoryService) {
                $spp = Document::query()
                    ->where('id', $sppId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spp) {
                    throw new \RuntimeException('Data SPP tidak ditemukan.');
                }

                if (! $this->canAccessForSubmit($spp, $jabatanId, $unitKerjaId, $actorPptkId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini.');
                }

                if (! is_null($spp->rejected_by)) {
                    throw new \RuntimeException('Dokumen sudah ditolak.');
                }

                $submitArr = $this->csvToArray($spp->submit);
                $statusArr = $this->csvToArray($spp->status);
                $assignedArr = $this->csvToArray($spp->assigned_to);
                $submitCount = array_count_values($submitArr);
                $signedByViewer = in_array((string) $jabatanId, $statusArr, true);
                $isFlowWithBpp = in_array('10', $submitArr, true) || in_array('10', $assignedArr, true);

                if (! $signedByViewer && $jabatanId !== 1) {
                    throw new \RuntimeException('Dokumen belum TTE oleh jabatan Anda.');
                }

                if (in_array($jabatanId, [9, 10], true) && (($submitCount[(string) $jabatanId] ?? 0) === 0)) {
                    throw new \RuntimeException('Gunakan Submit PPTK untuk tahap ini.');
                }

                $assignedTo = match ($jabatanId) {
                    9 => function () use ($submitCount) {
                        if (($submitCount['5'] ?? 0) === 0) {
                            throw new \RuntimeException('Dokumen belum disubmit oleh PA.');
                        }
                        if (($submitCount['9'] ?? 0) > 1) {
                            throw new \RuntimeException('Dokumen sudah pernah disubmit ke Verifikator.');
                        }

                        return '7';
                    },
                    10 => function () use ($submitCount) {
                        if (($submitCount['6'] ?? 0) === 0) {
                            throw new \RuntimeException('Dokumen belum disubmit oleh KPA.');
                        }
                        if (($submitCount['10'] ?? 0) > 1) {
                            throw new \RuntimeException('Dokumen sudah pernah disubmit ke Verifikator.');
                        }

                        return '7';
                    },
                    8 => function () use ($submitCount, $submitArr) {
                        if (($submitCount['8'] ?? 0) > 0) {
                            throw new \RuntimeException('Dokumen sudah pernah disubmit oleh PPTK.');
                        }
                        if (($submitCount['9'] ?? 0) === 0 && ($submitCount['10'] ?? 0) === 0) {
                            throw new \RuntimeException('Dokumen belum disubmit dari BP/BPP ke PPTK.');
                        }

                        return in_array('10', $submitArr, true) ? '6' : '5';
                    },
                    5 => function () use ($submitCount) {
                        if (($submitCount['5'] ?? 0) > 0) {
                            throw new \RuntimeException('Dokumen sudah pernah disubmit oleh PA.');
                        }
                        if (($submitCount['9'] ?? 0) === 0) {
                            throw new \RuntimeException('Dokumen belum melalui BP.');
                        }

                        return '9';
                    },
                    6 => function () use ($submitCount) {
                        if (($submitCount['6'] ?? 0) > 0) {
                            throw new \RuntimeException('Dokumen sudah pernah disubmit oleh KPA.');
                        }
                        if (($submitCount['10'] ?? 0) === 0) {
                            throw new \RuntimeException('Dokumen belum melalui BPP.');
                        }

                        return '10';
                    },
                    default => throw new \RuntimeException('Aksi submit tidak valid.'),
                };

                $assignedTo = is_callable($assignedTo) ? $assignedTo() : $assignedTo;

                $newSubmit = $spp->submit
                    ? $spp->submit.','.$jabatanId
                    : (string) $jabatanId;

                $spp->update([
                    'submit' => $newSubmit,
                    'assigned_to' => $assignedTo,
                    'updated_at' => now(),
                ]);

                $documentHistoryService->submit($spp->id, (string) $spp->src_name, $spp->id_unit_kerja);
            });

            Log::channel('payment_tu')->info('SPP TU submit success', [
                'doc_id' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'SPP berhasil disubmit.']);
        } catch (\RuntimeException $e) {
            Log::channel('payment_tu')->info('SPP TU submit blocked', [
                'doc_id' => $sppId,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('SPP TU submit failed', [
                'doc_id' => $sppId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 500, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    public function submit_pptk(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        Log::channel('payment_tu')->info('SPP TU submit_pptk request', [
            'hash' => $request->id,
            'users_to' => $request->users_to,
        ]);

        try {
            $sppId = EncryptedId::decode($request->id);
            $pptkTo = EncryptedId::decode($request->users_to);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->warning('SPP TU submit_pptk invalid hash', [
                'hash' => $request->id,
                'users_to' => $request->users_to,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan || ! $user->unitKerja) {
            Log::channel('payment_tu')->warning('SPP TU submit_pptk blocked: invalid active position', [
                'doc_id' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $unitKerjaId = (int) $user->unitKerja->id;
        $actorPptkId = (int) $this->positionIdentityResolver->pptkActorPosition($user)->getKey();
        if (! in_array($jabatanId, [1, 9, 10], true)) {
            Log::channel('payment_tu')->warning('SPP TU submit_pptk blocked: forbidden role', [
                'doc_id' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang melakukan aksi ini.'], 403);
        }

        try {
            DB::transaction(function () use (
                $sppId,
                $pptkTo,
                $jabatanId,
                $unitKerjaId,
                $actorPptkId,
                $documentHistoryService
            ) {
                $spp = Document::query()
                    ->where('id', $sppId)
                    ->where('src_type', self::SRC_TYPE)
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $spp) {
                    throw new \RuntimeException('Data SPP tidak ditemukan.');
                }

                if (! $this->canAccessForSubmit($spp, $jabatanId, $unitKerjaId, $actorPptkId)) {
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini.');
                }

                $isValidPptk = DB::table('user_positions')
                    ->where('id', $pptkTo)
                    ->where('jabatan_id', 8)
                    ->where('unit_kerja_id', $spp->id_unit_kerja)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $isValidPptk) {
                    throw new \RuntimeException('PPTK yang dipilih tidak valid untuk unit kerja dokumen ini.');
                }

                if (! is_null($spp->rejected_by)) {
                    throw new \RuntimeException('Dokumen sudah ditolak.');
                }

                $submitArr = $this->csvToArray($spp->submit);
                $statusArr = $this->csvToArray($spp->status);
                $assignedArr = $this->csvToArray($spp->assigned_to);
                $submitCount = array_count_values($submitArr);
                $signedByViewer = in_array((string) $jabatanId, $statusArr, true);
                $isFlowWithBpp = in_array('10', $submitArr, true) || in_array('10', $assignedArr, true);

                if (($submitCount[(string) $jabatanId] ?? 0) !== 0) {
                    throw new \RuntimeException('Tahap submit ke PPTK sudah dilakukan.');
                }

                if (! $this->canSubmitByFlow($jabatanId, $submitCount, $submitArr, $signedByViewer, $isFlowWithBpp)) {
                    throw new \RuntimeException('Dokumen belum dapat disubmit ke PPTK pada tahap ini.');
                }

                $newSubmit = $spp->submit
                    ? $spp->submit.','.$jabatanId
                    : (string) $jabatanId;

                $spp->update([
                    'submit' => $newSubmit,
                    'assigned_to' => '8',
                    'users_to' => $pptkTo,
                    'updated_at' => now(),
                ]);

                $documentHistoryService->submit($spp->id, (string) $spp->src_name, $spp->id_unit_kerja);
            });

            Log::channel('payment_tu')->info('SPP TU submit_pptk success', [
                'doc_id' => $sppId,
                'users_to' => $pptkTo,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 200, 'message' => 'SPP berhasil disubmit ke PPTK.']);
        } catch (\RuntimeException $e) {
            Log::channel('payment_tu')->info('SPP TU submit_pptk blocked', [
                'doc_id' => $sppId,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_tu')->error('SPP TU submit_pptk failed', [
                'doc_id' => $sppId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json(['status' => 500, 'message' => 'Terjadi kesalahan sistem.'], 500);
        }
    }

    private function canSubmitByFlow(
        int $jabatanId,
        array $submitCount,
        array $submitArr,
        bool $signedByViewer,
        bool $isFlowWithBpp
    ): bool {
        return match ($jabatanId) {
            9 => $signedByViewer && (
                (($submitCount['9'] ?? 0) === 0 && ($submitCount['8'] ?? 0) >= 0)
                || (($submitCount['9'] ?? 0) === 1 && ($submitCount['5'] ?? 0) >= 1 && ($submitCount['7'] ?? 0) === 0)
            ) && ! $isFlowWithBpp,
            10 => $signedByViewer && (
                (($submitCount['10'] ?? 0) === 0 && ($submitCount['8'] ?? 0) >= 0)
                || (($submitCount['10'] ?? 0) === 1 && ($submitCount['6'] ?? 0) >= 1 && ($submitCount['7'] ?? 0) === 0)
            ) && $isFlowWithBpp,
            8 => $signedByViewer
                && (($submitCount['8'] ?? 0) === 0)
                && (($submitCount['9'] ?? 0) >= 1 || ($submitCount['10'] ?? 0) >= 1),
            5 => $signedByViewer
                && (($submitCount['5'] ?? 0) === 0)
                && (($submitCount['9'] ?? 0) >= 1)
                && ! $isFlowWithBpp,
            6 => $signedByViewer
                && (($submitCount['6'] ?? 0) === 0)
                && (($submitCount['10'] ?? 0) >= 1)
                && $isFlowWithBpp,
            default => false,
        };
    }

    private function canAccessForEdit(Document $document, int $jabatanId): bool
    {
        $assigned = $this->csvToArray($document->assigned_to);
        $submit = $this->csvToArray($document->submit);

        return in_array((string) $jabatanId, $assigned, true)
            || in_array((string) $jabatanId, $submit, true);
    }

    private function canEditDocument(Document $document, int $jabatanId): bool
    {
        if (! $this->canAccessForEdit($document, $jabatanId)) {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return true;
        }

        $submitCount = array_count_values($this->csvToArray($document->submit));

        return ($submitCount[(string) $jabatanId] ?? 0) === 0;
    }

    private function canAccessForSubmit(Document $document, int $jabatanId, int $unitKerjaId, ?int $actorPptkId = null): bool
    {
        if ($jabatanId === 1) {
            return true;
        }

        if ((int) $document->id_unit_kerja !== $unitKerjaId) {
            return false;
        }

        $assigned = $this->csvToArray($document->assigned_to);
        $submit = $this->csvToArray($document->submit);

        if (
            $jabatanId === 8
            && ! is_null($document->users_to)
            && $actorPptkId
            && ! $this->positionIdentityResolver->contains($actorPptkId, (int) $document->users_to)
        ) {
            return false;
        }

        if (in_array((string) $jabatanId, $assigned, true)) {
            return true;
        }

        if (in_array((string) $jabatanId, $submit, true) && in_array($jabatanId, [9, 10], true)) {
            return true;
        }

        return false;
    }

    private function assertPengajuanAvailability(
        int $pengajuanId,
        ?int $currentSppId,
        int $unitKerjaId,
        int $jabatanId,
        ?int $currentReferenceId = null
    ): Document {
        $document = Document::query()
            ->where('id', $pengajuanId)
            ->where('src_type', 'PENGAJUAN')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('id_unit_kerja', $unitKerjaId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if (! $document) {
            throw new \RuntimeException('Data pengajuan tidak ditemukan dalam unit kerja yang sama.');
        }

        if (! is_null($document->rejected_by)) {
            throw new \RuntimeException('Data pengajuan yang ditolak tidak dapat dipilih.');
        }

        if ((int) $document->verify !== 1) {
            throw new \RuntimeException('Data pengajuan belum diverifikasi.');
        }

        if (! in_array('2', $this->csvToArray($document->status), true)) {
            throw new \RuntimeException('Data pengajuan belum TTE oleh BUD.');
        }

        if (! in_array('4', $this->csvToArray($document->submit), true)) {
            throw new \RuntimeException('Data pengajuan belum disubmit verifikator.');
        }

        $isCurrentReference = ! is_null($currentReferenceId) && $pengajuanId === $currentReferenceId;
        if ((int) $jabatanId !== 1 && ! $isCurrentReference) {
            $assignedTo = $this->csvToArray($document->assigned_to);

            if (! in_array((string) $jabatanId, $assignedTo, true)) {
                throw new \RuntimeException('Data pengajuan tidak tersedia untuk posisi aktif saat ini.');
            }
        }

        $used = Document::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($pengajuanId) {
                $query->where(function ($newPattern) use ($pengajuanId) {
                    $newPattern->where('src_type', self::SRC_TYPE)
                        ->where('payment_type', self::PAYMENT_TYPE)
                        ->where('reference_id', $pengajuanId);
                })->orWhere(function ($legacyPattern) use ($pengajuanId) {
                    $legacyPattern->where('src_type', 'PENGAJUAN')
                        ->where('payment_type', self::PAYMENT_TYPE)
                        ->where('id', $pengajuanId)
                        ->whereNotNull('reference_id');
                });
            })
            ->when($currentSppId, function ($q) use ($currentSppId, $pengajuanId) {
                $q->where(function ($scope) use ($currentSppId, $pengajuanId) {
                    $scope->where(function ($newPattern) use ($currentSppId) {
                        $newPattern->where('src_type', self::SRC_TYPE)
                            ->where('payment_type', self::PAYMENT_TYPE)
                            ->where('id', '!=', $currentSppId);
                    })->orWhere(function ($legacyPattern) use ($currentSppId, $pengajuanId) {
                        $legacyPattern->where('src_type', 'PENGAJUAN')
                            ->where('payment_type', self::PAYMENT_TYPE)
                            ->where('id', $pengajuanId)
                            ->where('reference_id', '!=', $currentSppId);
                    });
                });
            })
            ->exists();

        if ($used) {
            throw new \RuntimeException('Data pengajuan sudah digunakan pada dokumen SPP lain.');
        }

        return $document;
    }

    private function syncRekening(array $rekening, int $sppId, int $unitKerjaId): void
    {
        AnggaranKegiatan::where('tahun', session('tahun_aktif'))
            ->where('id_spp', $sppId)
            ->delete();

        $rekeningInput = collect($rekening)->keyBy('id');
        $tempData = AnggaranKegiatanTemp::where('tahun', session('tahun_aktif'))
            ->whereIn('id_rekening', $rekeningInput->keys())
            ->get()
            ->keyBy('id_rekening');

        $insertData = [];
        foreach ($rekeningInput as $idRekening => $item) {
            if (! isset($tempData[$idRekening])) {
                throw new \RuntimeException('Data rekening tidak ditemukan pada data referensi.');
            }

            $temp = $tempData[$idRekening];
            $insertData[] = [
                'tahun' => $temp->tahun,
                'kode_urusan' => $temp->kode_urusan,
                'nama_urusan' => $temp->nama_urusan,
                'kode_skpd' => $temp->kode_skpd,
                'nama_skpd' => $temp->nama_skpd,
                'kode_sub_unit' => $temp->kode_sub_unit,
                'nama_sub_unit' => $temp->nama_sub_unit,
                'kode_bidang_urusan' => $temp->kode_bidang_urusan,
                'nama_bidang_urusan' => $temp->nama_bidang_urusan,
                'kode_program' => $temp->kode_program,
                'nama_program' => $temp->nama_program,
                'kode_kegiatan' => $temp->kode_kegiatan,
                'nama_kegiatan' => $temp->nama_kegiatan,
                'kode_sub_kegiatan' => $temp->kode_sub_kegiatan,
                'nama_sub_kegiatan' => $temp->nama_sub_kegiatan,
                'kode_sumber_dana' => $temp->kode_sumber_dana,
                'nama_sumber_dana' => $temp->nama_sumber_dana,
                'kode_rekening' => $temp->kode_rekening,
                'nama_rekening' => $temp->nama_rekening,
                'id_rekening' => $idRekening,
                'nominal' => (float) ($item['nominal'] ?? 0),
                'pagu' => $temp->pagu,
                'id_spp' => $sppId,
                'id_unit_kerja' => $unitKerjaId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($insertData)) {
            AnggaranKegiatan::insert($insertData);
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

    private function requiresBmdFile(?string $belanja): bool
    {
        if (! $belanja) {
            return false;
        }

        $selected = array_map(
            'intval',
            array_filter(explode(',', (string) $belanja), fn ($v) => trim((string) $v) !== '')
        );

        return count(array_intersect($selected, [1, 2])) > 0;
    }

    private function isTransitioningToRequiredBmd(?string $currentBelanja, ?string $newBelanja): bool
    {
        return ! $this->requiresBmdFile($currentBelanja) && $this->requiresBmdFile($newBelanja);
    }

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== ''));
    }

    private function resolveLinkedPengajuanId(int $sppId, mixed $referenceId = null): ?int
    {
        $referenceId = is_numeric($referenceId) ? (int) $referenceId : null;

        if ($referenceId) {
            $currentId = Document::query()
                ->where('id', $referenceId)
                ->where('src_type', 'PENGAJUAN')
                ->where('payment_type', self::PAYMENT_TYPE)
                ->whereNull('deleted_at')
                ->value('id');

            if ($currentId) {
                return (int) $currentId;
            }
        }

        $legacyId = Document::query()
            ->where('src_type', 'PENGAJUAN')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $sppId)
            ->whereNull('deleted_at')
            ->value('id');

        return $legacyId ? (int) $legacyId : null;
    }

    private function findLinkedBmd(Document $spp, ?int $pengajuanId = null, bool $lockForUpdate = false): ?Document
    {
        $referenceIds = array_values(array_unique(array_filter([
            $pengajuanId,
            (int) $spp->id,
        ], static fn ($id) => ! is_null($id) && (int) $id > 0)));

        if (empty($referenceIds)) {
            return null;
        }

        $query = Document::query()
            ->where('src_type', 'BMD')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereIn('reference_id', $referenceIds)
            ->whereNull('deleted_at');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        if ($pengajuanId) {
            $query->orderByRaw('CASE WHEN reference_id = ? THEN 0 ELSE 1 END', [$pengajuanId]);
        }

        return $query->first();
    }
}
