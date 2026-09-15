<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment\LS;

use App\Http\Controllers\Controller;
use App\Http\Requests\LS\StoreSppRequest;
use App\Http\Requests\LS\UpdateSppRequest;
use App\Models\AnggaranKegiatan;
use App\Models\AnggaranKegiatanTemp;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Payment\LS;
use App\Models\UnitKerja;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SPP extends Controller
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

    public function ButtonStatus($id, $data, $allJabatan)
    {
        $statusArr = $data->status_spp
            ? array_values(array_filter(array_map('trim', explode(',', $data->status_spp)), static fn ($value) => $value !== ''))
            : [];
        $submitArr = $data->submit_spp
            ? array_values(array_filter(array_map('trim', explode(',', $data->submit_spp)), static fn ($value) => $value !== ''))
            : [];

        $status = in_array($id, $statusArr);
        $encript = EncryptedId::encode($data->id);

        $verify = ! is_null($data->verify_spp);

        $dataById = array_column($allJabatan, 'nama', 'id');
        $rejectedBy = $dataById[$data->rejected_by_spp] ?? null;

        if (! is_null($data->denied_billing_at)) {
            return '<span type="button" class="btn btn-sm btn-danger show-document"
                     data-wenk-pos="top"
                     data-id="'.$encript.'"
                     data-wenk="'.e($data->notes_spp).'"
                     data-wenk-color="red"
                     data-toggle="modal"
                     data-target="#FormTTE">
                    <i class="far fa-times-circle"></i> Billing Ditolak
                </span>';
        }

        if (is_null($data->rejected_by_spp)) {

            if (! is_null($data->finished_at)) {
                return '<span type="button" class="btn btn-sm btn-success show-document"
                         data-url="/File_SPP/signs/'.$data->src_name_spp.'"
                         data-files="'.$data->src_name_spp.'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Selesai Pencairan"
                         data-wenk-color="green"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-check-double"></i> Selesai
                    </span>';
            }

            if (in_array($id, [1, 2, 3, 4])) {
                return '<span type="button" class="btn btn-sm btn-info show-document"
                         data-url="/File_SPP/signs/'.$data->src_name_spp.'"
                         data-files="'.$data->src_name_spp.'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="Tampilkan Dokumen"
                         data-wenk-color="blue"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fa-solid fa-eye"></i> Tampilkan
                    </span>';
            }

            $inStatus = in_array($id, $statusArr);
            $inSubmit = in_array($id, $submitArr);
            $submitCounts = array_count_values($submitArr);
            $assignedArr = $data->assigned_to_spp
                ? array_values(array_filter(array_map('trim', explode(',', $data->assigned_to_spp)), static fn ($value) => $value !== ''))
                : [];
            $needsSecondPptkSubmit = in_array($id, [9, 10], true)
                && in_array((string) $id, $assignedArr, true)
                && (($submitCounts[(string) $id] ?? 0) < 2)
                && (in_array('5', $submitArr, true) || in_array('6', $submitArr, true));

            if ($needsSecondPptkSubmit) {
                return '<span type="button" class="btn btn-sm btn-secondary show-document"
                         data-status="'.($status ? 1 : 0).'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Belum di Submit"
                         data-wenk-color="blue"
                         data-files="'.$data->src_name_spp.'"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-hourglass-half"></i> Belum Submit
                    </span>';
            }

            if (! $inSubmit && ! $inStatus) {
                return '<span type="button" class="btn btn-sm btn-warning show-document"
                         data-status="'.($status ? 1 : 0).'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="Belum Tanda Tangan"
                         data-wenk-color="orange"
                         data-files="'.$data->src_name_spp.'"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-file-signature"></i> Belum Tanda Tangan
                    </span>';
            }

            if (! $inSubmit && $inStatus) {
                return '<span type="button" class="btn btn-sm btn-success show-document"
                         data-status="'.($status ? 1 : 0).'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Tanda Tangan"
                         data-wenk-color="green"
                         data-files="'.$data->src_name_spp.'"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-file-contract"></i> Sudah Tanda Tangan
                    </span>';
            }

            if ($inSubmit) {
                if ($verify) {
                    return '<span type="button" class="btn btn-sm btn-success show-document"
                         data-url="/File_SPP/signs/'.$data->src_name_spp.'"
                         data-files="'.$data->src_name_spp.'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Terverifikasi"
                         data-wenk-color="green"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-user-check"></i> Telah Terverifikasi
                    </span>';
                }

                return '<span type="button" class="btn btn-sm btn-primary show-document"
                         data-url="/File_SPP/signs/'.$data->src_name_spp.'"
                         data-files="'.$data->src_name_spp.'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Submit"
                         data-wenk-color="blue"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-paper-plane"></i> Telah Submit
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
                 data-wenk="'.e($data->notes_spp).'"
                 data-wenk-color="red"
                 data-toggle="modal"
                 data-target="#FormTTE">
                <i class="far fa-file-excel"></i> Dokumen Ditolak '.$rejectedBy.'
            </span>';
    }

    protected function auditorStatusBadge(object $data, array $allJabatan): string
    {
        $dataById = array_column($allJabatan, 'nama', 'id');
        $rejectedBy = $dataById[$data->rejected_by_spp] ?? null;

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

        if (! is_null($data->rejected_by_spp)) {
            $label = $rejectedBy
                ? 'Dokumen Ditolak '.$rejectedBy
                : 'Dokumen Ditolak';

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if (! is_null($data->finished_at)) {
            return $badge('btn-success', 'fas fa-check-double', 'Selesai');
        }

        if (! is_null($data->verify_spp)) {
            return $badge('btn-success', 'fas fa-user-check', 'Telah Terverifikasi');
        }

        if (! empty($data->submit_spp)) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if (! empty($data->status_spp)) {
            return $badge('btn-success', 'fas fa-file-contract', 'Sudah Tanda Tangan');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum Tanda Tangan');
    }
    // ######################################################################################################################################################

    public function index()
    {
        return view('Payment.LS.spp');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);
        try {

            $user = $activePosition->get();
            $userJabatan = $user->jabatan->id;
            $userUnit = ($user->unitKerja) ? $user->unitKerja->id : null;
            $userIds = (int) $userJabatan === 8
                ? $this->positionIdentityResolver->equivalentIds(
                    $this->positionIdentityResolver->pptkActorPosition($user),
                )
                : [];

            Log::channel('payment_ls')->debug('SPP LS json request', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            $allJabatan = Jabatan::query()->select('id', 'nama')->get()->toArray();
            $assignedExpr = "REPLACE(COALESCE(document.assigned_to,''), ' ', '')";

            $dataQuery = LS::rootQuery()
                ->tap(fn ($q) => LS::joinSpp($q));

            switch ($userJabatan) {
                case 1:
                case 2:
                case 3:
                case 4:
                case 13:
                    $dataQuery = $dataQuery;
                    break;
                case 5:
                case 6:
                    $dataQuery = $dataQuery
                        ->where('document.id_unit_kerja', $userUnit)
                        ->where(function ($query) use ($assignedExpr) {
                            $query->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['9'])
                                ->orWhereRaw("FIND_IN_SET(?, {$assignedExpr})", ['7'])
                                ->orWhereRaw("FIND_IN_SET(?, {$assignedExpr})", ['10'])
                                ->orWhere(function ($q) use ($assignedExpr) {
                                    $q->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['5'])
                                        ->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['6']);
                                });
                        });
                    break;
                case 8:
                    $dataQuery = $dataQuery
                        ->where('document.id_unit_kerja', $userUnit)
                        ->where(function ($query) use ($assignedExpr) {
                            $query->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['9'])
                                ->orWhereRaw("FIND_IN_SET(?, {$assignedExpr})", ['7'])
                                ->orWhereRaw("FIND_IN_SET(?, {$assignedExpr})", ['10'])
                                ->orWhereRaw("FIND_IN_SET(?, {$assignedExpr})", ['8'])
                                ->orWhere(function ($q) use ($assignedExpr) {
                                    $q->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['5'])
                                        ->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['6']);
                                });
                        })
                        ->whereIn('document.users_to', $userIds);
                    break;
                case 7:
                    $dataQuery = $dataQuery
                        ->where(function ($query) use ($userUnit) {
                            $query->where('document.id_unit_kerja', $userUnit)
                                ->orWhere('unit_kerja_spp.skpd_id', $userUnit);
                        })
                        ->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['7']);
                    break;
                case 9:
                case 10:
                    $dataQuery = $dataQuery->where('document.id_unit_kerja', $userUnit);
                    break;
                default:
                    Log::channel('payment_ls')->warning('SPP LS json unhandled jabatan', [
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);
                    $dataQuery = LS::rootQuery()->whereRaw('1=0');
                    break;
            }

            $btn = static function (
                string $value,
                string $class,
                string $icon,
                string $title,
                string $extra = ''
            ): string {
                return sprintf(
                    '<button value="%s" class="btn p-2 m-1 %s" title="%s" %s>
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
                ->addColumn('status', function ($data) use ($userJabatan, $allJabatan, $start) {
                    if ((int) $userJabatan === 13) {
                        return $this->auditorStatusBadge($data, $allJabatan);
                    }

                    $jabatanId = match ($userJabatan) {
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        5 => 5,
                        4 => 4,
                        6 => 6,
                        7 => 8,
                        8 => 8,
                        9 => 9,
                        10 => 10,
                        default => null,
                    };

                    if (is_null($jabatanId)) {
                        Log::channel('payment_ls')->error('SPP LS json invalid jabatan mapping', [
                            'document_id' => $data->id ?? null,
                            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                        ]);

                        return '<span type="button" class="btn btn-sm btn-danger"
                                data-status=""
                                data-wenk-pos="top"
                                data-wenk="Data Error, Hub Developer"
                                data-wenk-color="red">
                                <i class="far fa-file-excel"></i> Error Data
                            </span>';
                    }

                    return $this->ButtonStatus($jabatanId, $data, $allJabatan);
                })

                ->addColumn('action', function ($document) use ($userJabatan, $btn) {

                    $enc = EncryptedId::encode($document->id);

                    $statusArr = $document->status_spp ? explode(',', $document->status_spp) : [];
                    $submitArr = $document->submit_spp ? explode(',', $document->submit_spp) : [];
                    $assignedArr = $document->assigned_to_spp ? explode(',', $document->assigned_to_spp) : [];

                    $actions = [];

                    switch ($userJabatan) {
                        case 13:
                            $actions[] = $btn(
                                $enc,
                                'show-document',
                                'fa-solid fa-eye text-primary',
                                'Detail Dokumen',
                                'data-id="'.$enc.'"'
                            );
                            $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            break;

                        case 7:
                            if ($document->rejected_by_spp || $document->verify_spp) {
                                $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            } else {
                                $actions[] = $btn(
                                    $enc,
                                    'verify_data',
                                    'ni ni-like-2 text-success',
                                    'Verifikasi',
                                    'data-payment="LS" data-type="SPP"'
                                );
                                $actions[] = $btn(
                                    $enc,
                                    'denied',
                                    'far fa-file-excel text-danger',
                                    'Menolak Data',
                                    'data-payment="LS" data-type="SPP" '
                                );
                                $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            }
                            break;

                            /* ================= ADMIN / PPTK ================= */
                        case 1:
                        case 2:
                        case 3:
                        case 4:
                            $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            break;
                        case 9:
                        case 10:

                            if ($document->denied_billing_at) {
                                $actions[] = $btn(
                                    $enc,
                                    'edit_billing',
                                    'fas fa-edit text-primary',
                                    'Edit',
                                    'data-payment="'.$document->payment_type_spp.'"'
                                );
                            } elseif ($document->rejected_by_spp) {
                                $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                                $actions[] = $btn(
                                    $enc,
                                    'delete',
                                    'fas fa-trash text-danger',
                                    'Menolak Data',
                                    'data-payment="'.$document->payment_type_spp.'" data-type="'.$document->src_type_spp.'"'
                                );
                            } elseif (
                                (in_array('9', $submitArr) || in_array('10', $submitArr)) &&
                                (array_intersect(['9', '10'], $assignedArr)) &&
                                (array_intersect(['5', '6'], $submitArr))
                            ) {
                                $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-warning', 'Submit');
                                $actions[] = $btn(
                                    $enc,
                                    'denied',
                                    'far fa-file-excel text-danger',
                                    'Menolak Data',
                                    'data-payment="'.$document->payment_type_spp.'" data-type="'.$document->src_type_spp.'"'
                                );
                            } elseif ((in_array('9', $statusArr) && ! in_array('9', $submitArr)) || (! in_array('10', $submitArr) && in_array('10', $statusArr))) {
                                $actions[] = $btn($enc, 'submit_pptk', 'ni ni-send text-success', 'Submit');
                                $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                                $actions[] = $btn(
                                    $enc,
                                    'delete',
                                    'fas fa-trash text-danger',
                                    'Delete Data',
                                    'data-payment="LS" data-type="SPP" '
                                );
                            } elseif (! in_array('9', $submitArr) && ! in_array('10', $submitArr)) {
                                $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                                $actions[] = $btn(
                                    $enc,
                                    'delete',
                                    'fas fa-trash text-danger',
                                    'Delete Data',
                                    'data-payment="LS" data-type="SPP" '
                                );
                            }

                            $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            break;

                        case 5:
                        case 6:
                            if (array_intersect(['5', '6'], $submitArr)) {
                                $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            } else {
                                if (! $document->rejected_by_spp && array_intersect(['5', '6'], $statusArr)) {
                                    $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                                }
                                if (! $document->rejected_by_spp) {
                                    $actions[] = $btn(
                                        $enc,
                                        'denied',
                                        'fas fa-trash text-danger',
                                        'Menolak Data',
                                        'data-payment="'.$document->payment_type_spp.'" data-type="'.$document->src_type_spp.'"'
                                    );
                                }
                                $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            }

                            break;

                        case 8:
                            if (
                                ! in_array('8', $submitArr) &&
                                ! $document->rejected_by_spp
                            ) {
                                if (in_array('8', $statusArr)) {
                                    $actions[] = $btn($enc, 'submit_data', 'ni ni-send text-success', 'Submit');
                                }
                                $actions[] = $btn(
                                    $enc,
                                    'denied',
                                    'far fa-file-excel text-danger',
                                    'Menolak Data',
                                    'data-payment="'.$document->payment_type_spp.'" data-type="'.$document->src_type_spp.'"'
                                );
                            }

                            $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            break;

                        default:
                            $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');
                            break;
                    }

                    return implode('', $actions);
                })

                ->filterColumn('unit_kerja', function ($query, $keyword) {
                    $query->where('unit_kerjas.nama', 'like', "%{$keyword}%");
                })
                ->rawColumns(['action', 'status'])
                ->make(true);

            Log::channel('payment_ls')->debug('SPP LS json success', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return $response;
        } catch (Throwable $e) {

            Log::channel('payment_ls')->error('SPP LS json failed', [
                'exception' => class_basename($e),
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'draw' => request('draw'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Terjadi kesalahan saat memuat data',
            ], 500);
        }
    }

    public function store(
        StoreSppRequest $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        $sppId = null;

        Log::channel('payment_ls')->info('SPP LS Store request', [
            'nomor_spp' => $request->nomor_spp,
            'nominal' => $request->nominal,
            'belanja' => $request->belanja,
            'rekening_count' => count($request->rekening ?? []),
            'has_spp' => $request->hasFile('file_spp'),
            'has_spj' => $request->hasFile('file_spj'),
            'has_billing' => $request->hasFile('file_billing'),
            'has_bmd' => $request->hasFile('file_bmd'),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $user = $activePosition->get();
        $userId = $user->id;
        $unitKerja = $user?->unitKerja?->id;
        $rekeningMap = [];
        $storedFiles = [];

        try {
            $filename_spp = $this->storeFile($request->file('file_spp'), '/File_SPP', $storedFiles);
            $filename_spj = $this->storeFile($request->file('file_spj'), '/File_SPJ', $storedFiles);
            $filename_billing = $request->hasFile('file_billing')
                ? $this->storeFile($request->file('file_billing'), '/File_Billing', $storedFiles)
                : null;
            $filename_bmd = $request->hasFile('file_bmd')
                ? $this->storeFile($request->file('file_bmd'), '/File_BMD', $storedFiles)
                : null;
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_ls')->warning('SPP LS Store file upload failed', [
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal mengunggah file',
            ], 400);
        }

        foreach ($request->rekening as $r) {
            $rekeningMap[$r['id']] = (float) $r['nominal'];
        }

        $tempData = AnggaranKegiatanTemp::tahunAktif()
            ->whereIn('id_rekening', array_keys($rekeningMap))
            ->get()
            ->keyBy('id_rekening');

        try {
            DB::transaction(function () use (
                $request,
                $filename_spp,
                $filename_spj,
                $filename_billing,
                $filename_bmd,
                $userId,
                $unitKerja,
                $rekeningMap,
                $tempData,
                $documentHistoryService,
                $start,
                &$sppId
            ) {
                Log::channel('payment_ls')->debug('SPP LS Store transaction start', [
                    'nomor_spp' => $request->nomor_spp,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                $spp = $this->saveDocumentData([
                    'nomor' => $request->nomor_spp,
                    'src_name' => $filename_spp,
                    'src_type' => 'SPP',
                    'payment_type' => 'LS',
                    'id_unit_kerja' => $unitKerja,
                    'uploaded_by' => $userId,
                    'nominal' => $request->nominal,
                    'uraian' => $request->uraian,
                    'expenditure_type' => $request->belanja,
                ]);
                $sppId = $spp->id;
                $documentHistoryService->upload($spp->id, $filename_spp, $unitKerja);
                $spj = $this->saveDocumentData([
                    'reference_id' => $spp->id,
                    'src_name' => $filename_spj,
                    'src_type' => 'SPJ',
                    'payment_type' => 'LS',
                    'id_unit_kerja' => $unitKerja,
                    'uploaded_by' => $userId,
                    'billing' => $filename_billing,
                ]);
                $documentHistoryService->upload($spj->id, $filename_spj, $unitKerja);
                if ($filename_bmd) {
                    $bmd = $this->saveDocumentData([
                        'reference_id' => $spp->id,
                        'src_name' => $filename_bmd,
                        'src_type' => 'BMD',
                        'payment_type' => 'LS',
                        'id_unit_kerja' => $unitKerja,
                        'uploaded_by' => $userId,
                    ]);
                    $documentHistoryService->upload($bmd->id, $filename_bmd, $unitKerja);
                }
                $now = now();
                $insertData = [];
                foreach ($rekeningMap as $idRekening => $nominal) {
                    if (! isset($tempData[$idRekening])) {
                        continue;
                    }
                    $row = $tempData[$idRekening];
                    $insertData[] = [
                        'tahun' => $row->tahun,
                        'kode_urusan' => $row->kode_urusan,
                        'nama_urusan' => $row->nama_urusan,
                        'kode_skpd' => $row->kode_skpd,
                        'nama_skpd' => $row->nama_skpd,
                        'kode_sub_unit' => $row->kode_sub_unit,
                        'nama_sub_unit' => $row->nama_sub_unit,
                        'kode_bidang_urusan' => $row->kode_bidang_urusan,
                        'nama_bidang_urusan' => $row->nama_bidang_urusan,
                        'kode_program' => $row->kode_program,
                        'nama_program' => $row->nama_program,
                        'kode_kegiatan' => $row->kode_kegiatan,
                        'nama_kegiatan' => $row->nama_kegiatan,
                        'kode_sub_kegiatan' => $row->kode_sub_kegiatan,
                        'nama_sub_kegiatan' => $row->nama_sub_kegiatan,
                        'kode_sumber_dana' => $row->kode_sumber_dana,
                        'nama_sumber_dana' => $row->nama_sumber_dana,
                        'kode_rekening' => $row->kode_rekening,
                        'nama_rekening' => $row->nama_rekening,
                        'id_rekening' => $row->id_rekening,
                        'nominal' => $nominal,
                        'pagu' => $row->pagu,
                        'id_spp' => $spp->id,
                        'id_unit_kerja' => $unitKerja,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                AnggaranKegiatan::where('id_spp', $spp->id)->delete();
                AnggaranKegiatan::insert($insertData);
            });

            Log::channel('payment_ls')->info('SPP LS Store success', [
                'id_spp' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data Tersimpan...',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_ls')->error('SPP LS Store failed', [
                'id_spp' => $sppId,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal menyimpan data',
            ], 400);
        }
    }

    public function edit(
        Request $request,
        ActivePositionService $activePosition
    ) {
        $start = microtime(true);

        Log::channel('payment_ls')->debug('SPP LS Edit request', [
            'hash' => $request->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        try {
            $position = $activePosition->get();

            if (! $position || ! $position->unitKerja) {
                throw new \RuntimeException('Active position not resolved');
            }

            try {
                $decryptedId = EncryptedId::decode($request->id);
            } catch (Throwable $e) {
                Log::channel('payment_ls')->warning('SPP LS Edit invalid encrypted ID', [
                    'hash' => $request->id,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 400,
                    'message' => 'ID tidak valid',
                ], 400);
            }

            $data = Document::where('id', $decryptedId)
                ->where('id_unit_kerja', $position->unitKerja->id)
                ->first();

            if (! $data) {
                Log::channel('payment_ls')->warning('SPP LS Edit document not found or forbidden', [
                    'id_spp' => $decryptedId,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 404,
                    'message' => 'Dokumen SPP tidak ditemukan',
                ], 404);
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
                ->where('id_spp', $decryptedId)
                ->where('id_unit_kerja', $position->unitKerja->id)
                ->get();

            $hasBmd = Document::query()
                ->where('reference_id', $decryptedId)
                ->where('src_type', 'BMD')
                ->where('payment_type', 'LS')
                ->whereNull('deleted_at')
                ->exists();

            if ($rekening->isEmpty()) {
                Log::channel('payment_ls')->notice('SPP LS Edit rekening empty', [
                    'id_spp' => $decryptedId,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);
            }

            Log::channel('payment_ls')->debug('SPP LS Edit success', [
                'id_spp' => $decryptedId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'data' => $data,
                'has_bmd' => $hasBmd,
                'rekening' => $rekening,
            ]);
        } catch (Throwable $e) {

            Log::channel('payment_ls')->error('SPP LS Edit failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan saat memuat data',
            ], 500);
        }
    }

    public function update(
        UpdateSppRequest $request,
        $id,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        Log::channel('payment_ls')->info('SPP LS Update request', [
            'hash' => $id,
            'has_spp' => $request->has('nomor_spp'),
            'has_nominal' => $request->has('nominal'),
            'has_belanja' => $request->has('belanja'),
            'rekening_count' => count($request->rekening ?? []),
            'has_spp' => $request->hasFile('file_spp'),
            'has_spj' => $request->hasFile('file_spj'),
            'has_billing' => $request->hasFile('file_billing'),
            'has_bmd' => $request->hasFile('file_bmd'),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $user = $activePosition->get();
        $unitKerjaId = $user->unitKerja->id;

        try {
            $decryptedId = EncryptedId::decode($id);
        } catch (\Throwable $e) {
            Log::channel('payment_ls')->warning('SPP LS Update invalid ID', [
                'hash' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'ID tidak valid',
            ], 400);
        }

        try {
            $storedFiles = [];
            DB::transaction(function () use (
                $request,
                $decryptedId,
                $unitKerjaId,
                $user,
                $documentHistoryService,
                $start,
                &$storedFiles
            ) {
                Log::channel('payment_ls')->debug('SPP LS Update transaction start', [
                    'id_spp' => $decryptedId,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                $dataSpp = [
                    'nomor' => $request->nomor_spp,
                    'nominal' => $request->nominal,
                    'uraian' => $request->uraian,
                    'expenditure_type' => $request->belanja,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $user->id,
                    'updated_at' => now(),
                    'rejected_by' => null,
                    'notes' => null,
                    'assigned_to' => null,
                    'submit' => null,
                    'users_to' => null,
                ];

                if ($request->hasFile('file_spp')) {
                    $dataSpp['src_name'] = $this->storeFile($request->file('file_spp'), '/File_SPP', $storedFiles);
                    $dataSpp['status'] = null;
                }

                $spp = $this->updateDocumentData($decryptedId, $dataSpp);
                $documentHistoryService->edited($spp->id, $spp->src_name);

                $billing = $request->hasFile('file_billing')
                    ? $this->storeFile($request->file('file_billing'), '/File_Billing', $storedFiles)
                    : Document::where('reference_id', $decryptedId)->value('billing');

                $dataSpj = [
                    'billing' => $billing,
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $user->id,
                    'updated_at' => now(),
                    'rejected_by' => null,
                    'notes' => null,
                    'assigned_to' => null,
                    'submit' => null,
                    'users_to' => null,
                ];

                if ($request->hasFile('file_spj')) {
                    $dataSpj['src_name'] = $this->storeFile($request->file('file_spj'), '/File_SPJ', $storedFiles);
                    $dataSpj['status'] = null;
                }

                $spj = Document::updateOrCreate(
                    ['reference_id' => $decryptedId, 'src_type' => 'SPJ', 'payment_type' => 'LS'],
                    $dataSpj
                );
                $documentHistoryService->edited($spj->id, $spj->src_name);

                $belanja = array_map('intval', explode(',', (string) $request->belanja));

                if (count(array_intersect($belanja, [1, 2])) > 0) {
                    $dataBmd = [
                        'id_unit_kerja' => $unitKerjaId,
                        'uploaded_by' => $user->id,
                        'status' => null,
                        'rejected_by' => null,
                        'notes' => null,
                        'assigned_to' => null,
                        'submit' => null,
                        'users_to' => null,
                    ];

                    if ($request->hasFile('file_bmd')) {
                        $dataBmd['src_name'] = $this->storeFile($request->file('file_bmd'), '/File_BMD', $storedFiles);
                    }

                    $bmd = Document::updateOrCreate(
                        ['reference_id' => $decryptedId, 'src_type' => 'BMD', 'payment_type' => 'LS'],
                        $dataBmd
                    );

                    $documentHistoryService->edited($bmd->id, $bmd->src_name);
                }

                $rekeningInput = collect($request->rekening)->keyBy('id');

                $existing = AnggaranKegiatan::where('tahun', session('tahun_aktif'))
                    ->where('id_spp', $decryptedId)
                    ->get()
                    ->keyBy('id_rekening');

                $deleteIds = $existing->keys()->diff($rekeningInput->keys());

                if ($deleteIds->isNotEmpty()) {
                    AnggaranKegiatan::where('tahun', session('tahun_aktif'))
                        ->where('id_spp', $decryptedId)
                        ->whereIn('id_rekening', $deleteIds)
                        ->delete();
                }

                $tempData = AnggaranKegiatanTemp::where('tahun', session('tahun_aktif'))
                    ->whereIn('id_rekening', $rekeningInput->keys())
                    ->get()
                    ->keyBy('id_rekening');

                $updateData = [];
                $insertData = [];

                foreach ($rekeningInput as $idRekening => $item) {
                    $nominal = (float) $item['nominal'];

                    if ($existing->has($idRekening)) {
                        $updateData[] = [
                            'id_rekening' => $idRekening,
                            'nominal' => $nominal,
                            'updated_at' => now(),
                        ];
                    } else {
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
                            'nominal' => $nominal,
                            'pagu' => $temp->pagu,
                            'id_spp' => $decryptedId,
                            'id_unit_kerja' => $unitKerjaId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                foreach ($updateData as $row) {
                    AnggaranKegiatan::where('tahun', session('tahun_aktif'))
                        ->where('id_spp', $decryptedId)
                        ->where('id_rekening', $row['id_rekening'])
                        ->update([
                            'nominal' => $row['nominal'],
                            'updated_at' => $row['updated_at'],
                        ]);
                }

                if (! empty($insertData)) {
                    AnggaranKegiatan::insert($insertData);
                }
            });

            Log::channel('payment_ls')->info('SPP LS Update success', [
                'id_spp' => $decryptedId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Update SPP Berhasil',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles ?? []);

            Log::channel('payment_ls')->error('SPP LS Update failed', [
                'id_spp' => $decryptedId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal update SPP',
            ], 400);
        }
    }

    // ######################################################################################################################################################
    public function submit(Request $request, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $start = microtime(true);
        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }
        $userLevel = (int) $user->jabatan->id;
        $userUnit = $user->unitKerja->id ?? null;

        Log::channel('payment_ls')->info('SPP LS Submit request', [
            'hash' => $request->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_ls')->warning('SPP LS Submit Invalid ID', [
                'hash' => $request->id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid.',
            ], 400);
        }

        try {
            DB::transaction(function () use ($docId, $userLevel, $userUnit, $documentHistoryService, $start) {
                $docs = Document::select('id', 'src_name', 'submit', 'assigned_to', 'rejected_by', 'src_type', 'id_unit_kerja')
                    ->where(function ($q) use ($docId) {
                        $q->where('id', $docId)
                            ->orWhere('reference_id', $docId);
                    })
                    ->whereIn('src_type', ['SPP', 'SPJ', 'BMD'])
                    ->lockForUpdate()
                    ->get();

                if ($docs->isEmpty()) {
                    Log::channel('payment_ls')->warning('SPP LS Submit document not found', [
                        'doc_id' => $docId,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);
                    throw new \RuntimeException('Dokumen tidak ditemukan');
                }

                $mainDoc = $docs->firstWhere('id', $docId) ?? $docs->first();

                if (! $this->canAccessForSubmit($mainDoc, $userLevel, $userUnit)) {
                    Log::channel('payment_ls')->warning('SPP LS Submit forbidden document access', [
                        'doc_id' => $docId,
                        'doc_unit' => $mainDoc->id_unit_kerja ?? null,
                        'assigned_to' => $mainDoc->assigned_to ?? null,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini');
                }

                if ($mainDoc->rejected_by !== null) {
                    Log::channel('payment_ls')->info('SPP LS Submit already rejected', [
                        'doc_id' => $docId,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);
                    throw new \RuntimeException('Dokumen sudah ditolak');
                }

                $submitList = $mainDoc->submit
                    ? array_values(array_filter(
                        array_map('intval', array_map('trim', explode(',', trim((string) $mainDoc->submit, ',')))),
                        static fn ($value) => $value > 0
                    ))
                    : [];
                $assignedList = array_values(array_filter(
                    array_map('trim', explode(',', (string) $mainDoc->assigned_to)),
                    static fn ($value) => $value !== ''
                ));

                Log::channel('payment_ls')->debug('SPP LS Submit state', [
                    'doc_id' => $docId,
                    'submit_list' => $submitList,
                    'assigned_to' => $assignedList,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                if (! in_array((string) $userLevel, $assignedList, true)) {
                    $message = in_array($userLevel, $submitList, true)
                        ? 'Data telah disubmit sebelumnya. Silakan periksa status dokumen.'
                        : 'Dokumen belum ditugaskan ke jabatan Anda.';

                    Log::channel('payment_ls')->info('SPP LS Submit invalid assignment', [
                        'doc_id' => $docId,
                        'assigned_to' => $assignedList,
                        'submit_list' => $submitList,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    throw new \RuntimeException($message);
                }

                $submitCount = array_count_values($submitList);
                $currentCount = $submitCount[$userLevel] ?? 0;

                $allowTwice = [9, 10];
                $maxAllowed = in_array($userLevel, $allowTwice, true) ? 2 : 1;

                if ($currentCount >= $maxAllowed) {
                    Log::channel('payment_ls')->info('SPP LS Submit limit reached', [
                        'doc_id' => $docId,
                        'count' => $currentCount,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    throw new \RuntimeException('Data telah disubmit sebelumnya. Silakan periksa status dokumen.');
                }

                $has5or6 = in_array(5, $submitList, true) || in_array(6, $submitList, true);

                $assignedTo = match ($userLevel) {
                    8 => '5,6',
                    5 => '9',
                    6 => '10',
                    9, 10 => $has5or6
                        ? '7'
                        : throw new \RuntimeException('Belum melewati verifikator 5 atau 6'),
                    default => throw new \RuntimeException('User tidak memiliki hak submit'),
                };

                Log::channel('payment_ls')->debug('SPP LS Submit next assigned', [
                    'doc_id' => $docId,
                    'assigned_to' => $assignedTo,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                $submitList[] = $userLevel;
                $newSubmit = implode(',', $submitList);

                Document::whereIn('id', $docs->pluck('id'))
                    ->update([
                        'submit' => $newSubmit,
                        'assigned_to' => $assignedTo,
                        'updated_at' => now(),
                    ]);

                foreach ($docs as $doc) {
                    $documentHistoryService->edited(
                        $doc->id,
                        $doc->src_name,
                        $userUnit
                    );
                }

                Log::channel('payment_ls')->info('SPP LS Submit persisted', [
                    'doc_id' => $docId,
                    'submit' => $newSubmit,
                    'assigned_to' => $assignedTo,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);
            });

            Log::channel('payment_ls')->info('SPP LS Submit success', [
                'doc_id' => $docId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Dokumen berhasil disubmit',
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_ls')->info('SPP LS Submit blocked', [
                'doc_id' => $docId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_ls')->error('SPP LS Submit system error', [
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

    public function submit_pptk(Request $request, ActivePositionService $activePosition, DocumentHistoryService $documentHistoryService)
    {
        $start = microtime(true);
        Log::channel('payment_ls')->info('SPP LS Submit PPTK request', [
            'hash' => $request->id,
            'users_to' => $request->users_to,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
            $userTo = EncryptedId::decode($request->users_to);

            Log::channel('payment_ls')->debug('SPP LS  Submit PPTK ID decoded', [
                'doc_id' => $docId,
                'user_to' => $userTo,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
        } catch (\Throwable) {

            Log::channel('payment_ls')->warning('SPP LS Submit PPTK invalid parameter', [
                'hash' => $request->id,
                'users_to' => $request->users_to,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
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
        $userJabatan = (int) $user->jabatan->id;
        $userUnit = $user->unitKerja->id ?? null;

        try {
            return DB::transaction(function () use ($docId, $userTo, $userJabatan, $user, $userUnit, $documentHistoryService, $start) {

                $docs = Document::select(
                    'id',
                    'src_name',
                    'submit',
                    'assigned_to',
                    'rejected_by',
                    'id_unit_kerja'
                )
                    ->where(function ($q) use ($docId) {
                        $q->where('id', $docId)
                            ->orWhere('reference_id', $docId);
                    })
                    ->whereIn('src_type', ['SPP', 'SPJ', 'BMD'])
                    ->lockForUpdate()
                    ->get();

                if ($docs->isEmpty()) {
                    Log::channel('payment_ls')->warning('SPP LS Submit PPTK document not found', [
                        'doc_id' => $docId,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json([
                        'status' => 404,
                        'message' => 'Dokumen tidak ditemukan.',
                    ], 404);
                }

                if (! in_array($userJabatan, [9, 10], true)) {
                    Log::channel('payment_ls')->warning('SPP LS Submit PPTK forbidden role', [
                        'doc_id' => $docId,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json([
                        'status' => 403,
                        'message' => 'Anda tidak berwenang melakukan aksi ini.',
                    ], 403);
                }

                $firstDoc = $docs->first();

                if (! $this->canAccessForSubmit($firstDoc, $userJabatan, $userUnit)) {
                    Log::channel('payment_ls')->warning('SPP LS Submit PPTK forbidden document access', [
                        'doc_id' => $docId,
                        'doc_unit' => $firstDoc->id_unit_kerja ?? null,
                        'assigned_to' => $firstDoc->assigned_to ?? null,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json([
                        'status' => 403,
                        'message' => 'Anda tidak berwenang mengakses dokumen ini.',
                    ], 403);
                }

                Log::channel('payment_ls')->debug('SPP LS Submit PPTK document state', [
                    'doc_id' => $docId,
                    'submit' => $firstDoc->submit,
                    'rejected_by' => $firstDoc->rejected_by,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                if (! is_null($firstDoc->rejected_by)) {
                    Log::channel('payment_ls')->info('SPP LS Submit PPTK document rejected', [
                        'doc_id' => $docId,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json([
                        'status' => 400,
                        'message' => 'Dokumen sudah ditolak.',
                    ], 400);
                }

                if (! is_null($firstDoc->submit)) {
                    Log::channel('payment_ls')->info('SPP LS Submit PPTK already submitted', [
                        'doc_id' => $docId,
                        'submit' => $firstDoc->submit,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);

                    return response()->json([
                        'status' => 400,
                        'message' => 'Dokumen sudah pernah disubmit.',
                    ], 400);
                }

                foreach ($docs as $doc) {
                    $documentHistoryService->edited(
                        $doc->id,
                        $doc->src_name,
                        $user->unitKerja->id
                    );
                }

                Document::whereIn('id', $docs->pluck('id'))
                    ->update([
                        'submit' => $userJabatan,
                        'assigned_to' => 8,
                        'users_to' => $userTo,
                    ]);

                Log::channel('payment_ls')->info('SPP LS Submit PPTK success', [
                    'doc_id' => $docId,
                    'assigned_to' => 8,
                    'users_to' => $userTo,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 200,
                    'message' => 'Dokumen berhasil disubmit ke PPTK.',
                ], 200);
            });
        } catch (\Throwable $e) {

            Log::channel('payment_ls')->error('SPP LS Submit PPTK system failure', [
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
}
