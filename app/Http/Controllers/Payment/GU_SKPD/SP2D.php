<?php

namespace App\Http\Controllers\Payment\GU_SKPD;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\GU_SKPD as PaymentGU_SKPD;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SP2D extends Controller
{
    public function __construct(private readonly PositionIdentityResolver $positionIdentityResolver) {}

    private const PAYMENT_TYPE = 'GU_SKPD';

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
        Log::channel('payment_gu_skpd')->debug('SP2D GU_SKPD index accessed');

        return view('Payment.GU_SKPD.sp2d');
    }

    public function json(ActivePositionService $activePosition)
    {
        $startedAt = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $user || ! $user->jabatan) {
                Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD json blocked: invalid active position', [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json(['status' => 403, 'message' => 'Posisi aktif tidak valid.'], 403);
            }

            $jabatanId = (int) $user->jabatan->id;
            $actorBudIds = in_array($jabatanId, [2, 3], true)
                ? $this->positionIdentityResolver->equivalentIds(
                    $this->positionIdentityResolver->budActorPosition($user),
                )
                : [];

            Log::channel('payment_gu_skpd')->debug('SP2D GU_SKPD json request', [
                'is_bud_scope' => in_array($jabatanId, [2, 3], true),
            ]);

            $query = PaymentGU_SKPD::rootQueryAlias('sp2d')
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

            Log::channel('payment_gu_skpd')->debug('SP2D GU_SKPD json success', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status', function ($row) use ($jabatanId) {
                    $encRef = EncryptedId::encode($row->reference_id ?: $row->id);
                    $statusArr = $this->csvToArray($row->status);
                    $signed = in_array((string) $jabatanId, $statusArr, true);
                    $signedFileUrl = '/File_SP2D/signs/'.$row->src_name;
                    $plainFileUrl = '/File_SP2D/'.$row->src_name;
                    $fileUrl = $signed ? $signedFileUrl : $plainFileUrl;

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

                        return '<span type="button" class="btn btn-sm btn-danger show-document" data-id="'.$encRef.'" data-url="'.$plainFileUrl.'" data-files="'.$row->src_name.'" data-wenk="'.e((string) $row->notes).'" data-wenk-color="red" data-toggle="modal" data-target="#FormTTE"><i class="far fa-file-excel"></i> '.$label.'</span>';
                    }

                    if (! is_null($row->finished_at)) {
                        return '<span type="button" class="btn btn-sm btn-success show-document" data-id="'.$encRef.'" data-url="'.$fileUrl.'" data-files="'.$row->src_name.'" data-wenk="File Telah Selesai Pencairan" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-check-double"></i> Selesai</span>';
                    }

                    if (in_array($jabatanId, [2, 3], true)) {
                        if ($signed) {
                            return '<span type="button" class="btn btn-sm btn-success show-document" data-status="1" data-id="'.$encRef.'" data-url="'.$signedFileUrl.'" data-files="'.$row->src_name.'" data-wenk="Sudah Tanda Tangan" data-wenk-color="green" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-contract"></i> Sudah Tanda Tangan</span>';
                        }

                        return '<span type="button" class="btn btn-sm btn-warning show-document" data-status="0" data-id="'.$encRef.'" data-url="'.$plainFileUrl.'" data-files="'.$row->src_name.'" data-wenk="Belum Tanda Tangan" data-wenk-color="orange" data-toggle="modal" data-target="#FormTTE"><i class="fas fa-file-signature"></i> Belum Tanda Tangan</span>';
                    }

                    return '<span type="button" class="btn btn-sm btn-info show-document" data-id="'.$encRef.'" data-url="'.$fileUrl.'" data-files="'.$row->src_name.'" data-wenk="Tampilkan Dokumen" data-wenk-color="blue" data-toggle="modal" data-target="#FormTTE"><i class="fa-solid fa-eye"></i> Tampilkan</span>';
                })
                ->addColumn('action', function ($row) use ($jabatanId, $btn) {
                    $enc = EncryptedId::encode($row->id);
                    $encRef = EncryptedId::encode($row->reference_id ?: $row->id);
                    $actions = [];

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

                    if (in_array($jabatanId, [2, 3], true)) {
                        if (is_null($row->rejected_by)) {
                            $actions[] = $btn(
                                $enc,
                                'denied',
                                'far fa-file-excel text-danger',
                                'Menolak Data',
                                'data-payment="GU_SKPD" data-type="SP2D"'
                            );
                        }
                        $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                        return implode('', $actions);
                    }

                    if ($jabatanId === 4 && (! is_null($row->rejected_by) || is_null($row->status))) {
                        $actions[] = $btn($enc, 'edit_data', 'fas fa-user-cog text-primary', 'Edit');
                        $actions[] = $btn(
                            $enc,
                            'delete',
                            'fas fa-trash text-danger',
                            'Hapus',
                            'data-payment="GU_SKPD" data-type="SP2D"'
                        );
                    }

                    $actions[] = $btn($enc, 'history_data', 'ni ni-collection text-info', 'History');

                    return implode('', $actions);
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        } catch (\Throwable $e) {
            Log::channel('payment_gu_skpd')->error('SP2D GU_SKPD json failed', [
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        }
    }

    public function store(
        Request $request,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $startedAt = microtime(true);
        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD store blocked: forbidden role', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang membuat SP2D.'], 403);
        }

        $storedFiles = [];
        $sp2dId = null;

        try {
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
                Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD store blocked: invalid spm reference', [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json(['status' => 400, 'message' => 'Referensi SPM tidak valid.'], 400);
            }

            Log::channel('payment_gu_skpd')->info('SP2D GU_SKPD store request', [
                'spm_reference_id' => $referenceId,
            ]);

            DB::transaction(function () use ($validated, $request, $user, $referenceId, &$storedFiles, $documentHistoryService, &$sp2dId) {
                $spm = $this->assertSpmAvailability($referenceId, null, true);
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

                $sp2dId = $document->id;
                $documentHistoryService->upload($document->id, $filename, $document->id_unit_kerja);
            });

            Log::channel('payment_gu_skpd')->info('SP2D GU_SKPD store success', [
                'sp2d_id' => $sp2dId,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data Tersimpan...']);
        } catch (ValidationException $e) {
            Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD store blocked: validation failed', [
                'errors' => $e->errors(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->error('SP2D GU_SKPD store failed', [
                'sp2d_id' => $sp2dId,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
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
        $startedAt = microtime(true);
        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD edit blocked: forbidden role', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SP2D.'], 403);
        }

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable) {
            Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD edit blocked: invalid parameter', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        Log::channel('payment_gu_skpd')->debug('SP2D GU_SKPD edit request', [
            'sp2d_id' => $id,
        ]);

        $sp2d = Document::query()
            ->where('id', $id)
            ->where('src_type', 'SP2D')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->whereNull('deleted_at')
            ->first();

        if (! $sp2d) {
            Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD edit blocked: not found', [
                'sp2d_id' => $id,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json(['status' => 404, 'message' => 'Data SP2D tidak ditemukan.'], 404);
        }

        if (! $this->isEditableForCrud($sp2d)) {
            Log::channel('payment_gu_skpd')->info('SP2D GU_SKPD edit blocked: non-editable', [
                'sp2d_id' => $sp2d->id,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Data SP2D tidak dapat diubah pada status saat ini.',
            ], 409);
        }

        $sp2d->selected_spm = EncryptedId::encode((int) $sp2d->reference_id);

        Log::channel('payment_gu_skpd')->debug('SP2D GU_SKPD edit success', [
            'sp2d_id' => $sp2d->id,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return response()->json(['status' => 200, 'data' => $sp2d]);
    }

    public function update(
        Request $request,
        string $id,
        ActivePositionService $activePosition,
        DocumentHistoryService $documentHistoryService
    ) {
        $startedAt = microtime(true);
        $user = $activePosition->get();
        if (! $this->canCrud($user)) {
            Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD update blocked: forbidden role', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json(['status' => 403, 'message' => 'Anda tidak berwenang mengubah SP2D.'], 403);
        }

        try {
            $sp2dId = EncryptedId::decode($id);
        } catch (\Throwable) {
            Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD update blocked: invalid parameter', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json(['status' => 400, 'message' => 'Parameter tidak valid.'], 400);
        }

        $storedFiles = [];

        try {
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
                Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD update blocked: invalid spm reference', [
                    'sp2d_id' => $sp2dId,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json(['status' => 400, 'message' => 'Referensi SPM tidak valid.'], 400);
            }

            $sp2d = Document::query()
                ->where('id', $sp2dId)
                ->where('src_type', 'SP2D')
                ->where('payment_type', self::PAYMENT_TYPE)
                ->whereNull('deleted_at')
                ->first();

            if (! $sp2d) {
                Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD update blocked: not found', [
                    'sp2d_id' => $sp2dId,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json(['status' => 404, 'message' => 'Data SP2D tidak ditemukan.'], 404);
            }

            if (! $this->isEditableForCrud($sp2d)) {
                Log::channel('payment_gu_skpd')->info('SP2D GU_SKPD update blocked: non-editable', [
                    'sp2d_id' => $sp2d->id,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return response()->json([
                    'status' => 409,
                    'message' => 'Data SP2D tidak dapat diubah pada status saat ini.',
                ], 409);
            }

            Log::channel('payment_gu_skpd')->info('SP2D GU_SKPD update request', [
                'sp2d_id' => $sp2d->id,
                'spm_reference_id' => $referenceId,
            ]);

            DB::transaction(function () use ($validated, $request, $user, $referenceId, $sp2dId, $sp2d, &$storedFiles, $documentHistoryService) {
                $spm = $this->assertSpmAvailability($referenceId, $sp2dId, true);
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

            Log::channel('payment_gu_skpd')->info('SP2D GU_SKPD update success', [
                'sp2d_id' => $sp2dId,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return response()->json(['status' => 200, 'message' => 'Data SP2D berhasil diperbarui']);
        } catch (ValidationException $e) {
            Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD update blocked: validation failed', [
                'sp2d_id' => $sp2dId,
                'errors' => $e->errors(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->cleanupStoredFiles($storedFiles);
            Log::channel('payment_gu_skpd')->error('SP2D GU_SKPD update failed', [
                'sp2d_id' => $sp2dId,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
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
        $startedAt = microtime(true);

        try {
            $user = $activePosition->get();
            if (! $this->canCrud($user)) {
                Log::channel('payment_gu_skpd')->debug('SP2D GU_SKPD formJson blocked: forbidden role', [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return DataTables::of(collect())->make(true);
            }

            $isEdited = $request->edited === 'true';
            $referenceId = null;
            $sp2dId = null;

            if ($request->data) {
                try {
                    $sp2dId = EncryptedId::decode($request->data);
                } catch (\Throwable) {
                    Log::channel('payment_gu_skpd')->warning('SP2D GU_SKPD formJson blocked: invalid data parameter', [
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    ]);

                    return response()->json(['status' => 400, 'message' => 'Parameter data tidak valid.'], 400);
                }

                $referenceId = Document::query()
                    ->where('id', $sp2dId)
                    ->where('src_type', 'SP2D')
                    ->where('payment_type', self::PAYMENT_TYPE)
                    ->whereNull('deleted_at')
                    ->value('reference_id');
            }

            Log::channel('payment_gu_skpd')->debug('SP2D GU_SKPD formJson request', [
                'is_edited' => $isEdited,
                'sp2d_id' => $sp2dId,
                'spm_reference_id' => $referenceId,
            ]);

            $query = PaymentGU_SKPD::rootQueryAlias('spm')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'spm.id_unit_kerja')
                ->where('spm.src_type', 'SPM')
                ->where('spm.payment_type', self::PAYMENT_TYPE)
                ->where('spm.verify', 1)
                ->whereNull('spm.rejected_by')
                ->whereNotNull('spm.submit')
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

            Log::channel('payment_gu_skpd')->debug('SP2D GU_SKPD formJson success', [
                'is_edited' => $isEdited,
                'sp2d_id' => $sp2dId,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

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
                    $deniedButton = $checked ? '' : '<button type="button" class="btn btn-outline-danger denied" value="'.EncryptedId::encode($row->id).'" data-wenk="Menolak data" data-payment="GU_SKPD" data-type="SPM" data-wenk-color="red"><i class="fa-solid fa-ban"></i></button>';

                    return '<div class="d-flex justify-content-center"><div class="btn-group btn-group-sm"><input type="radio" class="btn-check" name="selected_spm" id="spm_'.$id.'" value="'.$id.'" '.$checked.'><label class="btn btn-outline-primary" for="spm_'.$id.'" data-wenk="Pilih data" data-wenk-color="green"><i class="fa-solid fa-check"></i></label>'.$deniedButton.'</div></div>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        } catch (\Throwable $e) {
            Log::channel('payment_gu_skpd')->error('SP2D GU_SKPD formJson failed', [
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        }
    }

    private function assertSpmAvailability(
        int $referenceId,
        ?int $currentSp2dId = null,
        bool $lockForUpdate = false
    ): Document {
        $query = Document::query()
            ->where('reference_id', $referenceId)
            ->where('src_type', 'SPM')
            ->where('payment_type', self::PAYMENT_TYPE)
            ->where('verify', 1)
            ->whereNull('rejected_by')
            ->whereNull('deleted_at');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $spm = $query->first();

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

    private function isEditableForCrud(Document $document): bool
    {
        if (! is_null($document->finished_at)) {
            return false;
        }

        return ! is_null($document->rejected_by) || is_null($document->status);
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
