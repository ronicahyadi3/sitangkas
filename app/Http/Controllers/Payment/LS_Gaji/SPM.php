<?php

namespace App\Http\Controllers\Payment\LS_Gaji;

use App\Http\Controllers\Controller;
use App\Http\Requests\LS\StoreSpmRequest;
use App\Http\Requests\LS\UpdateSpmRequest;
use App\Models\Document;
use App\Models\Jabatan;
use App\Models\Payment\LS_GAJI;
use App\Models\UnitKerja;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Support\EncryptedId;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SPM extends Controller
{
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

    protected function ButtonStatus($id, $data, $allJabatan)
    {
        if ($id == 5 || $id == 6) {
            $statusArr = array_merge(
                $data->status_spm ? explode(',', $data->status_spm) : [],
                $data->status_sptjm ? explode(',', $data->status_sptjm) : [],
                $data->status_sp_pengajuan ? explode(',', $data->status_sp_pengajuan) : []
            );
        } else {
            $statusArr = $data->status_sp ? explode(',', $data->status_sp) : [];
        }
        $submitArr = $data->submit_sp ? explode(',', $data->submit_sp) : [];

        $status = in_array($id, $statusArr);
        $encript = EncryptedId::encode($data->id);

        $verify = ! is_null($data->verify_sp);

        $dataById = array_column($allJabatan, 'nama', 'id');
        $rejectedBy = $dataById[$data->rejected_by_spm] ?? null;

        if (! is_null($data->denied_billing_at)) {
            return '<span type="button" class="btn btn-sm btn-danger show-document"
                     data-wenk-pos="top"
                     data-id="'.$encript.'"
                     data-wenk="'.e($data->notes).'"
                     data-wenk-color="red"
                     data-toggle="modal"
                     data-target="#FormTTE">
                    <i class="far fa-times-circle"></i> Billing Ditolak
                </span>';
        }

        if (is_null($data->rejected_by_spm)) {

            if (! is_null($data->finished_at)) {
                return '<span type="button" class="btn btn-sm btn-success show-document"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Selesai Pencairan"
                         data-wenk-color="green"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-check-double"></i> Selesai
                    </span>';
            }

            if (in_array($id, [1, 2, 3, 8, 9, 10])) {
                return '<span type="button" class="btn btn-sm btn-info show-document"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="Tampilkan Dokumen"
                         data-wenk-color="blue"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fa-solid fa-eye"></i> Tampilkan
                    </span>';
            }

            $filtered = array_filter($statusArr);

            $inStatus = ($id == 5 || $id == 6) ? count($filtered) === 3 && count(array_unique($filtered)) === 1 : in_array($id, $statusArr);
            $inSubmit = in_array($id, $submitArr);

            if (! $inSubmit && ! $inStatus && $id != 4) {
                return '<span type="button" class="btn btn-sm btn-warning show-document"
                         data-status="'.($status ? 1 : 0).'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="Belum Tanda Tangan"
                         data-wenk-color="orange"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-file-signature"></i> Belum Tanda Tangan
                    </span>';
            }

            if (! $inSubmit && $inStatus && $id != 4) {
                return '<span type="button" class="btn btn-sm btn-secondary show-document"
                         data-status="'.($status ? 1 : 0).'"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Tanda Tangan"
                         data-wenk-color="blue"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-hourglass-half"></i> Belum Submit</span>
                    </span>';
            }

            if ($verify) {
                return '<span type="button" class="btn btn-sm btn-success show-document"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Telah Terverifikasi"
                         data-wenk-color="green"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-user-check"></i> Telah Terverifikasi
                    </span>';
            }

            if (! $verify && $id == 4) {
                return '<span type="button" class="btn btn-sm btn-warning show-document"
                         data-wenk-pos="top"
                         data-id="'.$encript.'"
                         data-wenk="File Belum Terverifikasi"
                         data-wenk-color="orange"
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fa-solid fa-triangle-exclamation"></i> Belum Terverifikasi
                    </span>';
            }

            if ($inSubmit) {
                return '<span type="button" class="btn btn-sm btn-primary show-document"
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
                 data-wenk="'.e($data->notes_sp).'"
                 data-wenk-color="red"
                 data-toggle="modal"
                 data-target="#FormTTE">
                <i class="far fa-file-excel"></i> Dokumen Ditolak '.$rejectedBy.'
            </span>';
    }

    protected function auditorStatusBadge(object $data, array $allJabatan): string
    {
        $dataById = array_column($allJabatan, 'nama', 'id');
        $rejectedBy = $dataById[$data->rejected_by_spm] ?? null;

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

        if (! is_null($data->rejected_by_spm)) {
            $label = $rejectedBy
                ? 'Dokumen Ditolak '.$rejectedBy
                : 'Dokumen Ditolak';

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if (! is_null($data->finished_at)) {
            return $badge('btn-success', 'fas fa-check-double', 'Selesai');
        }

        if (! is_null($data->verify_sp)) {
            return $badge('btn-success', 'fas fa-user-check', 'Telah Terverifikasi');
        }

        if (! empty($data->submit_sp)) {
            return $badge('btn-primary', 'fas fa-paper-plane', 'Telah Submit');
        }

        if (! empty($data->status_spm) || ! empty($data->status_sptjm) || ! empty($data->status_sp_pengajuan) || ! empty($data->status_sp)) {
            return $badge('btn-success', 'fas fa-file-contract', 'Sudah Tanda Tangan');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum Tanda Tangan');
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

    // ######################################################################################################################################################

    public function index()
    {
        return view('Payment.LS_Gaji.spm');
    }

    public function json(ActivePositionService $activePosition)
    {
        $start = microtime(true);
        $user = $activePosition->get();

        if (! $user || ! $user->jabatan) {
            Log::channel('payment_ls_gaji')->warning('SPM LS Gaji json invalid active position', [
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $allJabatan = Jabatan::query()->select('id', 'nama')->get()->toArray();
        $unitKerja = $user->unitKerja?->id;
        $tahun = (int) (session('tahun_aktif') ?? date('Y'));

        Log::channel('payment_ls_gaji')->debug('SPM LS Gaji json request', [
            'tahun' => $tahun,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $dataQuery = LS_GAJI::applySpmJsonScope(
            LS_GAJI::spmJsonQuery($tahun),
            $jabatanId,
            $unitKerja
        );

        $hasCsv = static function (?string $csv, string $id, ?bool $pre = null): bool {
            if ($pre !== null) {
                return $pre;
            }
            if (! $csv) {
                return false;
            }

            return strpos(','.$csv.',', ','.$id.',') !== false;
        };

        $btn = static function (
            string $class,
            string $icon,
            string $title,
            string $value,
            string $extra = ''
        ): string {
            return sprintf(
                '<button value="%s" class="btn p-2 m-0 %s" title="%s" %s>
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
            ->addColumn('status', function ($row) use ($jabatanId, $allJabatan) {
                if ($jabatanId === 13) {
                    return $this->auditorStatusBadge($row, $allJabatan);
                }

                if (! $jabatanId) {
                    return '<span class="btn btn-sm btn-danger">
                        <i class="far fa-file-excel"></i> Error Data
                    </span>';
                }

                return $this->ButtonStatus($jabatanId, $row, $allJabatan);
            })

            ->addColumn('action', function ($row) use ($jabatanId, $hasCsv, $btn) {

                $encSpp = EncryptedId::encode($row->id);
                $encSpm = EncryptedId::encode($row->id_spm);

                $actions = [];
                $assignedSp = array_values(array_filter(array_map('trim', explode(',', (string) ($row->assigned_to_sp ?? ''))), static fn ($value) => $value !== ''));

                $spSubmit7 = $hasCsv($row->submit_sp ?? '', '7', $row->sp_submit_has_7 ?? null);
                $spStatus7 = $hasCsv($row->status_sp ?? '', '7', $row->sp_status_has_7 ?? null);

                $sp5 = $hasCsv($row->submit_sp ?? '', '5', $row->sp_submit_has_5 ?? null);
                $sp6 = $hasCsv($row->submit_sp ?? '', '6', $row->sp_submit_has_6 ?? null);

                $all5 = (
                    $hasCsv($row->status_spm ?? '', '5', $row->spm_status_has_5 ?? null) &&
                    $hasCsv($row->status_sptjm ?? '', '5', $row->sptjm_status_has_5 ?? null) &&
                    $hasCsv($row->status_sp_pengajuan ?? '', '5', $row->sp_pengajuan_status_5 ?? null)
                );

                $all6 = (
                    $hasCsv($row->status_spm ?? '', '6', $row->spm_status_has_6 ?? null) &&
                    $hasCsv($row->status_sptjm ?? '', '6', $row->sptjm_status_has_6 ?? null) &&
                    $hasCsv($row->status_sp_pengajuan ?? '', '6', $row->sp_pengajuan_status_6 ?? null)
                );

                switch ($jabatanId) {
                    case 13:
                        $actions[] = $btn('show-document', 'fa-solid fa-eye text-primary', 'Detail Dokumen', $encSpp, 'data-id="'.$encSpp.'"');
                        $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                        break;

                    case 1:
                    case 2:
                    case 3:
                    case 8:
                    case 9:
                    case 10:
                        $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                        break;
                    case 4:
                        if (! $row->verify_sp && ! $row->rejected_by_spm) {
                            $actions[] = $btn(
                                'verify_data',
                                'ni ni-like-2 text-success',
                                'Verifikasi',
                                $encSpm,
                                'data-payment="LS_GAJI" data-type="SPM" '
                            );
                            $actions[] = $btn(
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                $encSpm,
                                'data-payment="LS_GAJI" data-type="SPM" '
                            );
                        }
                        $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                        break;
                    case 7:
                        if ($row->rejected_by_spm) {
                            $actions[] = $btn('edit_data', 'fas fa-user-cog text-primary', 'Edit', $encSpp);
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                            $actions[] = $btn('delete', 'fas fa-trash text-danger', 'Hapus', $encSpm);
                        } elseif ($spSubmit7) {
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                        } elseif ($spStatus7) {
                            $actions[] = $btn('submit_data', 'ni ni-send text-success', 'Submit', $encSpp);
                            $actions[] = $btn('edit_data', 'fas fa-user-cog text-primary', 'Edit', $encSpp);
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                            $actions[] = $btn('delete', 'fas fa-trash text-danger', 'Hapus', $encSpm);
                        } else {
                            $actions[] = $btn('edit_data', 'fas fa-user-cog text-primary', 'Edit', $encSpp);
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                            $actions[] = $btn('delete', 'fas fa-trash text-danger', 'Hapus', $encSpm);
                        }
                        break;

                    case 5:
                    case 6:
                        if (in_array((string) $jabatanId, ['5', '6'], true) && ! in_array((string) $jabatanId, $assignedSp, true)) {
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                        } else {
                            if (($all5 || $all6) && ! $row->rejected_by_spm) {
                                $actions[] = $btn('submit_data', 'ni ni-send text-success', 'Submit', $encSpp);
                            }
                            if (! $row->rejected_by_spm) {
                                $actions[] = $btn(
                                    'denied',
                                    'far fa-file-excel text-danger',
                                    'Menolak Data',
                                    $encSpm,
                                    'data-payment="LS_GAJI" data-type="SPM"'
                                );
                            }
                            $actions[] = $btn('history_data', 'ni ni-collection text-info', 'History', $encSpp);
                        }
                        break;
                }

                return implode('', $actions);
            })

            /* ================= FILTER ================= */
            ->filterColumn('unit_kerja_spm', function ($query, $keyword) {
                $query->where('unit_kerjas_spm.nama', 'like', "%{$keyword}%");
            })

            ->rawColumns(['action', 'status'])
            ->make(true);

        Log::channel('payment_ls_gaji')->debug('SPM LS Gaji json success', [
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return $response;
    }

    public function store(
        StoreSpmRequest $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        Log::channel('payment_ls_gaji')->info('SPM LS Gaji store request', [
            'selected_spp' => $request->selected_spp,
            'has_sp' => $request->hasFile('file_sp'),
            'has_spm' => $request->hasFile('file_spm'),
            'has_sptjm' => $request->hasFile('file_sptjm'),
            'has_sp_pengajuan' => $request->hasFile('file_sp_pengajuan'),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }

        try {
            $idSpp = EncryptedId::decode($request->selected_spp);
        } catch (\Throwable) {
            return response()->json([
                'status' => 400,
                'message' => 'Data SPP tidak valid.',
            ], 400);
        }
        $userId = $user->id;
        $unitKerja = $user?->unitKerja?->id;
        if (! $unitKerja) {
            return response()->json([
                'status' => 403,
                'message' => 'Unit kerja tidak valid.',
            ], 403);
        }
        $storedFiles = [];

        $files = ['sp', 'spm', 'sptjm', 'sp_pengajuan'];

        try {
            DB::transaction(function () use (
                $request,
                $files,
                $idSpp,
                $userId,
                $unitKerja,
                &$storedFiles,
                $documentHistoryService
            ) {
                foreach ($files as $type) {
                    $fileInput = 'file_'.$type;
                    $nomorInput = 'nomor_'.$type;

                    if (! $request->hasFile($fileInput)) {
                        continue;
                    }

                    $uploadedFile = $request->file($fileInput);
                    $srcType = strtoupper($type);
                    $path = '/File_'.$srcType;

                    $filename = $this->storeFile($uploadedFile, $path, $storedFiles);

                    $document = $this->saveDocumentData([
                        'nomor' => $request->input($nomorInput),
                        'src_name' => $filename,
                        'src_type' => $srcType,
                        'payment_type' => 'LS_GAJI',
                        'reference_id' => $idSpp,
                        'id_unit_kerja' => $unitKerja,
                        'uploaded_by' => $userId,
                        'created_at' => now(),
                    ]);

                    $documentHistoryService->upload(
                        $document->id,
                        $filename,
                        $unitKerja
                    );
                }
            });

            Log::channel('payment_ls_gaji')->info('SPM LS Gaji store success', [
                'reference_id' => $idSpp,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data Tersimpan...',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_ls_gaji')->error('SPM LS Gaji store failed', [
                'reference_id' => $idSpp ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal menyimpan data',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function edit(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);
        Log::channel('payment_ls_gaji')->debug('SPM LS Gaji edit request', [
            'hash' => $request->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        try {
            $id = EncryptedId::decode($request->id);
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                return response()->json([
                    'status' => 403,
                    'message' => 'Posisi aktif tidak valid.',
                ], 403);
            }

            $query = LS_GAJI::rootQuery()
                ->when($id, fn ($q) => $q->where('document.id', $id))
                ->tap(fn ($q) => LS_GAJI::joinSpp($q, true))
                ->tap(fn ($q) => LS_GAJI::joinSp($q, true))
                ->tap(fn ($q) => LS_GAJI::joinSpm($q, true))
                ->tap(fn ($q) => LS_GAJI::joinSptjm($q, true))
                ->orderByDesc('created_at_spm');

            if ($user->jabatan->id != 1) {
                $query->where(
                    'sp.id_unit_kerja',
                    $user->unitKerja->id
                );
            }

            $result = $query->first();

            if (! $result) {
                Log::channel('payment_ls_gaji')->warning('SPM LS Gaji edit not found/forbidden', [
                    'doc_id' => $id,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 404,
                    'message' => 'Dokumen SPM tidak ditemukan',
                ], 404);
            }

            Log::channel('payment_ls_gaji')->debug('SPM LS Gaji edit success', [
                'doc_id' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::channel('payment_ls_gaji')->error('SPM LS Gaji edit failed', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Permintaan tidak valid',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function update(
        UpdateSpmRequest $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        Log::channel('payment_ls_gaji')->info('SPM LS Gaji update request', [
            'selected_spp' => $request->selected_spp,
            'hash' => $request->route('id'),
            'has_sp' => $request->hasFile('file_sp'),
            'has_spm' => $request->hasFile('file_spm'),
            'has_sptjm' => $request->hasFile('file_sptjm'),
            'has_sp_pengajuan' => $request->hasFile('file_sp_pengajuan'),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }
        $userId = $user->id;
        $sppId = $request->sppId();
        $data = $request->sppData()->first();

        if (! $data) {
            return response()->json([
                'status' => 404,
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        $resetPayload = [
            'reference_id' => $sppId,
            'rejected_by' => null,
            'notes' => null,
            'assigned_to' => null,
            'submit' => null,
            'users_to' => null,
            'updated_at' => now(),
        ];

        try {
            $storedFiles = [];
            DB::transaction(function () use (
                $request,
                $data,
                $sppId,
                $userId,
                $resetPayload,
                &$storedFiles,
                $documentHistoryService
            ) {

                if ($sppId != $data->id) {
                    Document::where('id', $sppId)->update([
                        'updated_at' => now(),
                    ]);
                }

                $documents = [
                    'sp' => $data->id_sp,
                    'spm' => $data->id_spm,
                    'sptjm' => $data->id_sptjm,
                    'sp_pengajuan' => $data->id_sp_pengajuan,
                ];

                foreach ($documents as $type => $docId) {
                    if (! $docId) {
                        continue;
                    }

                    $fileInput = 'file_'.$type;
                    $nomorInput = 'nomor_'.$type;
                    $srcType = strtoupper($type);

                    if (! $request->hasFile($fileInput)) {
                        $document = $this->updateDocumentData(
                            $docId,
                            array_merge($resetPayload, [
                                'nomor' => $request->input($nomorInput),
                            ])
                        );

                        if ($document) {
                            $documentHistoryService->edited(
                                $document->id,
                                $document->src_name
                            );
                        }

                        continue;
                    }

                    $filename = $this->storeFile(
                        $request->file($fileInput),
                        '/File_'.$srcType,
                        $storedFiles
                    );

                    $document = $this->updateDocumentData(
                        $docId,
                        array_merge($resetPayload, [
                            'nomor' => $request->input($nomorInput),
                            'src_name' => $filename,
                            'uploaded_by' => $userId,
                            'status' => null,
                        ])
                    );

                    if ($document) {
                        $documentHistoryService->edited(
                            $document->id,
                            $filename
                        );
                    }
                }
            });

            Log::channel('payment_ls_gaji')->info('SPM LS Gaji update success', [
                'spp_id' => $sppId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Data berhasil diperbarui',
            ]);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles ?? []);
            Log::channel('payment_ls_gaji')->error('SPM LS Gaji update failed', [
                'spp_id' => $sppId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal memperbarui data',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 400);
        }
    }

    // ######################################################################################################################################################
    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $start = microtime(true);
        Log::channel('payment_ls_gaji')->debug('SPM LS Gaji formJson request', [
            'edited' => $request->edited,
            'data' => $request->data,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }
        $unitKerja = isset($user->unitKerja) ? $user->unitKerja->id : null;
        try {
            $idSpp = $request->data ? EncryptedId::decode($request->data) : null;
        } catch (\Throwable) {
            return response()->json([
                'status' => 400,
                'message' => 'Parameter data tidak valid.',
            ], 400);
        }
        $isEdited = $request->edited === 'true';
        $assignedExpr = "REPLACE(COALESCE(document.assigned_to,''), ' ', '')";
        $applyUnusedDocumentScope = function ($query) {
            $query->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('document as used_docs')
                    ->whereColumn('used_docs.reference_id', 'document.id')
                    ->where('used_docs.payment_type', 'LS_GAJI')
                    ->whereNull('used_docs.deleted_at')
                    ->whereIn('used_docs.src_type', ['SP', 'SPM', 'SPTJM', 'SP_PENGAJUAN']);
            });
        };

        $dataQuery = LS_GAJI::rootQuery()
            ->tap(fn ($q) => LS_GAJI::joinSpp($q))
            ->where(function ($q) use ($unitKerja) {
                $q->where('document.id_unit_kerja', $unitKerja)
                    ->orWhere('unit_kerja_spp.skpd_id', $unitKerja);
            })
            ->whereRaw("FIND_IN_SET(?, {$assignedExpr})", ['7'])
            ->where('document.verify', 1)
            ->whereNull('document.rejected_by')
            ->when(! $isEdited, function ($q) use ($applyUnusedDocumentScope) {
                $applyUnusedDocumentScope($q);
            })
            ->when($isEdited, function ($q) use ($applyUnusedDocumentScope, $idSpp) {
                $q->where(function ($qq) use ($applyUnusedDocumentScope, $idSpp) {
                    $applyUnusedDocumentScope($qq);
                    $qq
                        ->orWhere('document.id', $idSpp);
                });
            });

        $response = DataTables::of($dataQuery)
            ->addIndexColumn()

            ->addColumn('status', function ($data) {
                $id = EncryptedId::encode($data->id);

                $html = '
                <span class="btn btn-sm btn-info show-document"
                    data-id="'.$id.'"
                    data-wenk="Klik untuk menampilan dokumen"
                    data-wenk-color="blue"
                    data-toggle="modal"
                    data-target="#FormTTE">
                    <i class="fa-solid fa-eye"></i> Tampilkan
                </span>
            ';

                if ($data->rejected_by_spp) {
                    $html .= '<span class="btn btn-sm btn-danger ms-1">Ditolak</span>';
                }

                return $html;
            })

            ->addColumn('action', function ($data) use ($request) {
                $id = EncryptedId::encode($data->id);
                $checked = ($request->edited === 'true' && EncryptedId::decode($request->data) == $data->id) ? 'checked' : '';

                $deniedButton = $checked ? '' : '
                <button
                    type="button"
                    class="btn btn-outline-danger denied"
                    value="'.$id.'"
                    data-wenk="Menolak data"
                    data-payment="LS_GAJI"
                    data-type="SPP"
                    data-wenk-color="red">
                    <i class="fa-solid fa-ban"></i>
                </button>';

                return '
                <div class="d-flex justify-content-center">
                    <div class="btn-group btn-group-sm">
                        <input type="radio"
                            class="btn-check"
                            name="selected_spp"
                            id="spp_'.$id.'"
                            value="'.$id.'"
                            '.$checked.'>

                        <label class="btn btn-outline-primary"
                            for="spp_'.$id.'"
                            data-wenk="Pilih data"
                            data-wenk-color="green">
                            <i class="fa-solid fa-check"></i>
                        </label>
                        '.$deniedButton.'
                    </div>
                </div>';
            })

            ->filterColumn('unit_kerja', function ($query, $keyword) {
                $query->where('unit_kerja_spp.nama', 'like', "%{$keyword}%");
            })

            ->rawColumns(['status', 'action'])
            ->make(true);

        Log::channel('payment_ls_gaji')->debug('SPM LS Gaji formJson success', [
            'edited' => $isEdited,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return $response;
    }

    // ######################################################################################################################################################
    public function submit(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $start = microtime(true);
        Log::channel('payment_ls_gaji')->info('SPM LS Gaji submit request', [
            'hash' => $request->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_ls_gaji')->warning('SPM LS Gaji submit invalid parameter', [
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
            Log::channel('payment_ls_gaji')->warning('SPM LS Gaji submit invalid active position', [
                'doc_id' => $docId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid.',
            ], 403);
        }
        $userLevel = (string) $user->jabatan->id;
        $userUnit = $user->unitKerja->id ?? null;

        try {
            DB::transaction(function () use ($docId, $userLevel, $userUnit, $documentHistoryService, $start) {
                $spp = Document::select('id', 'id_unit_kerja', 'rejected_by')
                    ->whereKey($docId)
                    ->where('src_type', 'SPP')
                    ->where('payment_type', 'LS_GAJI')
                    ->lockForUpdate()
                    ->first();

                if (! $spp) {
                    throw new \RuntimeException('Dokumen SPP tidak ditemukan');
                }

                $docs = Document::select('id', 'src_name', 'submit', 'assigned_to', 'rejected_by', 'src_type', 'id_unit_kerja')
                    ->where('reference_id', $docId)
                    ->whereIn('src_type', ['SP', 'SPM', 'SPTJM', 'SP_PENGAJUAN'])
                    ->lockForUpdate()
                    ->get();

                if ($docs->isEmpty()) {
                    throw new \RuntimeException('Dokumen tidak ditemukan');
                }

                $firstDoc = $docs->first();

                if (! $this->canAccessForSubmit($firstDoc, (int) $userLevel, $userUnit)) {
                    Log::channel('payment_ls_gaji')->warning('SPM LS Gaji submit forbidden document access', [
                        'doc_id' => $docId,
                        'doc_unit' => $firstDoc->id_unit_kerja ?? null,
                        'assigned_to' => $firstDoc->assigned_to ?? null,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);
                    throw new \RuntimeException('Anda tidak berwenang mengakses dokumen ini');
                }

                if ($spp->rejected_by !== null || $firstDoc->rejected_by !== null) {
                    throw new \RuntimeException('Dokumen SPP sudah ditolak');
                }

                $submitList = $firstDoc->submit
                    ? array_values(array_filter(array_map('trim', explode(',', trim((string) $firstDoc->submit, ','))), static fn ($value) => $value !== ''))
                    : [];

                if (in_array($userLevel, $submitList, true)) {
                    throw new \RuntimeException('Data telah disubmit sebelumnya. Silakan periksa status dokumen.');
                }

                $sppUnit = null;

                if ($userLevel === '7') {
                    $sppUnit = UnitKerja::query()
                        ->select('id', 'skpd_id')
                        ->whereKey($spp->id_unit_kerja)
                        ->first();

                    if (! $sppUnit) {
                        throw new \RuntimeException('Unit kerja SPP tidak ditemukan');
                    }

                    $assignedTo = ! empty($sppUnit->skpd_id) ? '6' : '5';
                } elseif (in_array($userLevel, ['5', '6'], true)) {
                    $assignedTo = '4';
                } else {
                    throw new \RuntimeException('User tidak memiliki hak submit');
                }

                $submitList[] = $userLevel;
                $newSubmit = implode(',', $submitList);

                Log::channel('payment_ls_gaji')->info('SPM LS Gaji submit route resolved', [
                    'doc_id' => $docId,
                    'assigned_to' => $assignedTo,
                    'spp_unit_id' => $spp->id_unit_kerja,
                    'is_child_unit' => ! empty($sppUnit->skpd_id ?? null),
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                Document::where('reference_id', $docId)
                    ->whereIn('src_type', ['SP', 'SPM', 'SPTJM', 'SP_PENGAJUAN'])
                    ->update([
                        'submit' => $newSubmit,
                        'assigned_to' => $assignedTo,
                        'updated_at' => now(),
                    ]);

                foreach ($docs as $doc) {
                    $documentHistoryService->submit(
                        $doc->id,
                        $doc->src_name,
                        $userUnit
                    );
                }
            });

            Log::channel('payment_ls_gaji')->info('SPM LS Gaji submit success', [
                'doc_id' => $docId,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Submit berhasil',
            ]);
        } catch (\RuntimeException $e) {
            Log::channel('payment_ls_gaji')->info('SPM LS Gaji submit blocked', [
                'doc_id' => $docId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            Log::channel('payment_ls_gaji')->error('SPM LS Gaji submit failed', [
                'doc_id' => $docId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
