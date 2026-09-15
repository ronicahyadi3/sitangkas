<?php

namespace App\Http\Controllers\Payment\GU_UK;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\GU_UK as PaymentGU_UK;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SP2D extends Controller
{
    public function __construct(private readonly PositionIdentityResolver $positionIdentityResolver) {}

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
                2 => 'Ditolak BUD',
                3 => 'Ditolak Kuasa BUD',
                4 => 'Ditolak Verifikator',
                default => 'Ditolak',
            };

            return $badge('btn-danger', 'far fa-file-excel', $label);
        }

        if (! is_null($row->finished_at)) {
            return $badge('btn-success', 'fas fa-check-double', 'Selesai');
        }

        if (! empty($this->csvToArray($row->status))) {
            return $badge('btn-success', 'fas fa-file-signature', 'Sudah Tanda Tangan');
        }

        return $badge('btn-warning', 'fas fa-file-signature', 'Belum Tanda Tangan');
    }

    public function index()
    {
        return view('Payment.GU_UK.sp2d');
    }

    public function json(ActivePositionService $activePosition)
    {
        $user = $activePosition->get();
        if (! $user || ! $user->jabatan) {
            return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
        }

        $jabatanId = (int) $user->jabatan->id;
        $actorBudIds = in_array($jabatanId, [2, 3], true)
            ? $this->positionIdentityResolver->equivalentIds(
                $this->positionIdentityResolver->budActorPosition($user),
            )
            : [];
        $actorPositionId = (int) $user->id;
        $unitKerjaId = (int) ($user->unitKerja?->id ?? 0);
        $query = PaymentGU_UK::rootQueryAlias('sp2d')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'sp2d.id_unit_kerja')
            ->leftJoin('document as spm', function ($join) {
                $join->on('spm.reference_id', '=', 'sp2d.reference_id')
                    ->where('spm.src_type', 'SPM')
                    ->where('spm.payment_type', self::PAYMENT_TYPE)
                    ->whereNull('spm.deleted_at');
            })
            ->leftJoin('user_positions as user_pos_to', function ($join) {
                $join->on('user_pos_to.id', '=', 'sp2d.users_to');
            })
            ->leftJoin('users as users_to_data', function ($join) {
                $join->on('users_to_data.id', '=', 'user_pos_to.user_id')
                    ->whereNull('users_to_data.deleted_at');
            })
            ->where('sp2d.src_type', 'SP2D')
            ->where('sp2d.payment_type', self::PAYMENT_TYPE)
            ->when(in_array($jabatanId, [2, 3], true), function ($q) use ($actorBudIds) {
                $q->whereIn('sp2d.users_to', $actorBudIds);
            })
            ->select([
                'sp2d.id',
                'sp2d.reference_id',
                'sp2d.created_at',
                'sp2d.updated_at',
                'sp2d.nomor',
                'sp2d.src_name',
                'sp2d.status',
                'sp2d.rejected_by',
                'sp2d.notes',
                'sp2d.finished_at',
                'sp2d.uraian',
                'sp2d.nominal',
                'sp2d.rekening',
                'sp2d.id_unit_kerja',
                'sp2d.uploaded_by',
                'sp2d.assigned_to',
                'sp2d.users_to',
                'spm.nomor as nomor_spm',
                'uk.nama as unit_kerja',
                'users_to_data.nama as user_name',
            ])
            ->orderByDesc('sp2d.updated_at');

        $btn = static function (
            string $value,
            string $class,
            string $icon,
            string $title,
            string $extra = ''
        ): string {
            return sprintf(
                '<button value="%s" class="btn p-2 m-0 %s" title="%s" %s><i class="%s fa-lg"></i></button>',
                $value,
                $class,
                $title,
                $extra,
                $icon
            );
        };

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status', function ($row) use ($jabatanId) {
                $encRef = EncryptedId::encode($row->reference_id ?: $row->id);
                $statusArr = $this->csvToArray($row->status);
                $signedByCurrentUser = in_array((string) $jabatanId, $statusArr, true);
                $hasSignedStatus = ! empty($statusArr);
                $signedFileUrl = '/File_SP2D/signs/'.$row->src_name;
                $plainFileUrl = '/File_SP2D/'.$row->src_name;
                $fileUrl = $hasSignedStatus ? $signedFileUrl : $plainFileUrl;

                if ($jabatanId === 13) {
                    return $this->auditorStatusBadge($row);
                }

                if (! is_null($row->rejected_by)) {
                    $label = match ((int) $row->rejected_by) {
                        2 => 'Ditolak BUD',
                        3 => 'Ditolak Kuasa BUD',
                        4 => 'Ditolak Verifikator',
                        default => 'Ditolak',
                    };

                    return '<span type="button" class="btn btn-sm btn-danger show-document" data-id="'.$encRef.'" data-url="'.$fileUrl.'" data-files="'.$row->src_name.'" data-wenk="'.e((string) $row->notes).'" data-wenk-color="red" data-toggle="modal" data-target="#FormTTE"><i class="far fa-file-excel"></i> '.$label.'</span>';
                }

                if (! is_null($row->finished_at)) {
                    return '<span type="button" class="btn btn-sm btn-success show-document" data-id="'.$encRef.'" data-url="'.$fileUrl.'" data-files="'.$row->src_name.'" data-wenk="File Telah Selesai Pencairan" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-check-double"></i> Selesai</span>';
                }

                if (in_array($jabatanId, [2, 3], true)) {
                    if ($signedByCurrentUser) {
                        return '<span type="button" class="btn btn-sm btn-success show-document" data-status="1" data-id="'.$encRef.'" data-url="'.$signedFileUrl.'" data-files="'.$row->src_name.'" data-wenk="Sudah Tanda Tangan" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-contract"></i> Sudah Tanda Tangan</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-id="'.$encRef.'" data-url="'.$fileUrl.'" data-files="'.$row->src_name.'" data-wenk="Belum Tanda Tangan" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum Tanda Tangan</span>';
                }

                return '<span type="button" class="btn btn-sm btn-info show-document" data-id="'.$encRef.'" data-url="'.$fileUrl.'" data-files="'.$row->src_name.'" data-wenk="Tampilkan Dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>';
            })
            ->addColumn('action', function ($row) use ($jabatanId, $btn, $actorPositionId, $unitKerjaId) {
                $enc = EncryptedId::encode($row->id);
                $actions = [];

                if ($jabatanId === 13) {
                    $encRef = EncryptedId::encode($row->reference_id ?: $row->id);
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

                if (in_array($jabatanId, [2, 3], true)) {
                    if (is_null($row->rejected_by)) {
                        $actions[] = $btn(
                            $enc,
                            'denied',
                            'far fa-file-excel text-danger',
                            'Menolak Data',
                            'data-payment="GU_UK" data-type="SP2D"'
                        );
                    }
                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                }

                if ($jabatanId === 4 && trim((string) $row->status) === '') {
                    $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');

                    if ($this->canDeleteForVerifier($row, $actorPositionId, $unitKerjaId)) {
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_UK" data-type="SP2D"'
                        );
                    }
                }

                $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                return implode('', $actions);
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat SP2D.'], 403);
        }

        $validated = $request->validate([
            'nomor_sp2d' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'rekening' => ['required', 'string', 'max:255'],
            'user' => ['required'],
            'selected_spm' => ['required'],
            'file_sp2d' => ['required', 'file', 'mimes:pdf'],
        ]);

        try {
            $referenceId = EncryptedId::decode($validated['selected_spm']);
        } catch (\Throwable) {
            return response()->json(['status' => 400, 'message' => 'Referensi SPM tidak valid.'], 400);
        }

        try {
            $spm = $this->assertSpmAvailability($referenceId);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        }

        $storedFiles = [];

        try {
            DB::transaction(function () use ($validated, $request, $user, $referenceId, $spm, &$storedFiles, $documentHistoryService) {
                $filename = $this->storeFile($request->file('file_sp2d'), '/File_SP2D', $storedFiles);

                $document = Document::create([
                    'nomor' => $validated['nomor_sp2d'],
                    'src_name' => $filename,
                    'src_type' => 'SP2D',
                    'payment_type' => self::PAYMENT_TYPE,
                    'reference_id' => $referenceId,
                    'id_unit_kerja' => $spm->id_unit_kerja,
                    'uploaded_by' => $user->id,
                    'uraian' => $validated['uraian'],
                    'nominal' => $validated['nominal'],
                    'rekening' => $validated['rekening'],
                    'users_to' => $validated['user'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $documentHistoryService->upload($document->id, $filename, $document->id_unit_kerja);
            });

            return response()->json(['status' => 200, 'message' => 'Data Tersimpan...']);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
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
        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SP2D.'], 403);
        }

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $sp2d = Document::query()
            ->where('id', $id)
            ->where('src_type', 'SP2D')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->first();

        if (! $sp2d) {
            return response()->json(['status' => 404, 'message' => 'Data SP2D tidak ditemukan.'], 404);
        }

        if ($this->isLockedAfterSigned($sp2d)) {
            return response()->json([
                'status' => 409,
                'message' => 'SP2D yang sudah ditandatangani tidak dapat diubah.',
            ], 409);
        }

        $sp2d->selected_spm = EncryptedId::encode((int) $sp2d->reference_id);

        return response()->json(['status' => 200, 'data' => $sp2d]);
    }

    public function update(
        Request $request,
        string $id,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SP2D.'], 403);
        }

        try {
            $sp2dId = EncryptedId::decode($id);
        } catch (\Throwable) {
            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $validated = $request->validate([
            'nomor_sp2d' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'rekening' => ['required', 'string', 'max:255'],
            'user' => ['required'],
            'selected_spm' => ['required'],
            'file_sp2d' => ['nullable', 'file', 'mimes:pdf'],
        ]);

        try {
            $referenceId = EncryptedId::decode($validated['selected_spm']);
        } catch (\Throwable) {
            return response()->json(['status' => 400, 'message' => 'Referensi SPM tidak valid.'], 400);
        }

        $sp2d = Document::query()
            ->where('id', $sp2dId)
            ->where('src_type', 'SP2D')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->first();

        if (! $sp2d) {
            return response()->json(['status' => 404, 'message' => 'Data SP2D tidak ditemukan.'], 404);
        }

        if ($this->isLockedAfterSigned($sp2d)) {
            return response()->json([
                'status' => 409,
                'message' => 'SP2D yang sudah ditandatangani tidak dapat diubah.',
            ], 409);
        }

        try {
            $spm = $this->assertSpmAvailability($referenceId, $sp2dId);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 400, 'message' => $e->getMessage()], 400);
        }

        $storedFiles = [];

        try {
            DB::transaction(function () use ($validated, $request, $user, $referenceId, $spm, $sp2d, &$storedFiles, $documentHistoryService) {
                $payload = [
                    'reference_id' => $referenceId,
                    'id_unit_kerja' => $spm->id_unit_kerja,
                    'nomor' => $validated['nomor_sp2d'],
                    'uraian' => $validated['uraian'],
                    'nominal' => $validated['nominal'],
                    'rekening' => $validated['rekening'],
                    'users_to' => $validated['user'],
                    'rejected_by' => null,
                    'notes' => null,
                    'updated_at' => now(),
                ];

                $filenameForHistory = $sp2d->src_name;

                if ($request->hasFile('file_sp2d')) {
                    $filename = $this->storeFile($request->file('file_sp2d'), '/File_SP2D', $storedFiles);
                    $payload['src_name'] = $filename;
                    $payload['uploaded_by'] = $user->id;
                    $payload['status'] = null;
                    $filenameForHistory = $filename;
                }

                Document::where('id', $sp2d->id)->update($payload);

                $documentHistoryService->edited($sp2d->id, $filenameForHistory);
            });

            return response()->json(['status' => 200, 'message' => 'Data SP2D berhasil diperbarui']);
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            report($e);

            return response()->json([
                'status' => 400,
                'message' => 'Gagal memperbarui data SP2D',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 400);
        }
    }

    public function formJson(Request $request, ActivePositionService $activePosition)
    {
        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            return DataTables::of(collect())->make(true);
        }

        $isEdited = $request->edited === 'true';
        $referenceId = null;

        if ($request->data) {
            try {
                $sp2dId = EncryptedId::decode($request->data);
            } catch (\Throwable) {
                return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
            }

            $referenceId = Document::query()
                ->where('id', $sp2dId)
                ->where('src_type', 'SP2D')
                ->where('payment_type', self::PAYMENT_TYPE)
                ->whereNull('deleted_at')
                ->value('reference_id');
        }

        $query = PaymentGU_UK::rootQueryAlias('spm')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'spm.id_unit_kerja')
            ->where('spm.src_type', 'SPM')
            ->where('spm.payment_type', self::PAYMENT_TYPE)
            ->where('spm.verify', 1)
            ->whereNull('spm.rejected_by')
            ->where(function ($q) use ($isEdited, $referenceId) {
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('document as sp2d')
                        ->whereColumn('sp2d.reference_id', 'spm.reference_id')
                        ->where('sp2d.src_type', 'SP2D')
                        ->where('sp2d.payment_type', self::PAYMENT_TYPE)
                        ->whereNull('sp2d.deleted_at');
                });

                if ($isEdited && $referenceId) {
                    $q->orWhere('spm.reference_id', $referenceId);
                }
            })
            ->select([
                'spm.id',
                'spm.reference_id',
                'spm.nomor',
                'spm.src_name',
                'spm.created_at',
                'spm.rejected_by',
                'uk.nama as unit_kerja',
            ])
            ->orderByDesc('spm.created_at');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status', function ($row) {
                $encRef = EncryptedId::encode($row->reference_id);
                $html = '<span class="btn btn-sm btn-info show-document" data-url="/File_SPM/signs/'.$row->src_name.'" data-files="'.$row->src_name.'" data-id="'.$encRef.'" data-wenk="Klik untuk menampilkan dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>';

                if ($row->rejected_by) {
                    $html .= '<span class="btn btn-sm btn-danger ms-1">Ditolak</span>';
                }

                return $html;
            })
            ->addColumn('action', function ($row) use ($request, $referenceId) {
                $id = EncryptedId::encode($row->reference_id);
                $checked = ($request->edited === 'true' && (int) $referenceId === (int) $row->reference_id) ? 'checked' : '';
                $deniedButton = $checked ? '' : '<button type="button" class="btn btn-outline-danger denied" value="'.EncryptedId::encode($row->id).'" data-wenk="Menolak data" data-payment="GU_UK" data-type="SPM" data-wenk-color="red"><i class="fa-solid fa-ban"></i></button>';

                return '<div class="d-flex justify-content-center"><div class="btn-group btn-group-sm"><input type="radio" class="btn-check" name="selected_spm" id="spm_'.$id.'" value="'.$id.'" '.$checked.'><label class="btn btn-outline-primary" for="spm_'.$id.'" data-wenk="Pilih data" data-wenk-color="green"><i class="fa-solid fa-check"></i></label>'.$deniedButton.'</div></div>';
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    private function assertSpmAvailability(int $referenceId, ?int $currentSp2dId = null): Document
    {
        $spm = Document::query()
            ->where('reference_id', $referenceId)
            ->where('src_type', 'SPM')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('verify', 1)
            ->whereNull('rejected_by')
            ->whereNull('deleted_at')
            ->first();

        if (! $spm) {
            throw new \RuntimeException('Data SPM tidak valid atau belum diverifikasi.');
        }

        $used = Document::query()
            ->where('src_type', 'SP2D')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('reference_id', $referenceId)
            ->whereNull('deleted_at')
            ->when($currentSp2dId, fn ($q) => $q->where('id', '!=', $currentSp2dId))
            ->exists();

        if ($used) {
            throw new \RuntimeException('Data SPM sudah digunakan pada dokumen SP2D lain.');
        }

        return $spm;
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
        return $user && $user->jabatan && (int) $user->jabatan->id === 4;
    }

    private function canDeleteForVerifier(object $row, int $actorPositionId, int $unitKerjaId): bool
    {
        if ($this->positionIdentityResolver->contains($actorPositionId, (int) ($row->uploaded_by ?? 0))) {
            return true;
        }

        if ((int) ($row->id_unit_kerja ?? 0) === $unitKerjaId) {
            return true;
        }

        $assigned = $this->csvToArray($row->assigned_to ?? null);

        return in_array('4', $assigned, true);
    }

    private function isLockedAfterSigned(Document $document): bool
    {
        return trim((string) $document->status) !== '';
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
