<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment\LS;

use App\Actions\Esign\ProvisionCanonicalDocument as ProvisionCanonicalDocumentAction;
use App\Data\Esign\EsignTransitionContext;
use App\Data\Esign\StagedDocumentArtifact;
use App\Enums\Esign\DocumentArtifactType;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Exceptions\Esign\LsSppSubmitGateException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LS\StoreSppRequest;
use App\Http\Requests\LS\UpdateSppRequest;
use App\Models\AnggaranKegiatan;
use App\Models\AnggaranKegiatanTemp;
use App\Models\Document;
use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\Jabatan;
use App\Models\Payment\LS;
use App\Models\UnitKerja;
use App\Models\UserPosition;
use App\Services\Document\DocumentHistoryService;
use App\Services\Document\DocumentOrganizationScope;
use App\Services\Esign\Authorization\LsSppSubmitGate;
use App\Services\Esign\LsSppWorkflowHandoffService;
use App\Services\Esign\Persistence\DocumentArtifactPersistenceService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Services\User\YearAccessService;
use App\Support\EncryptedId;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

class SPP extends Controller
{
    private const SETDA_SOURCE_UNIT_CODE = 'SKPD_SETDA';

    private const SETDA_TARGET_UNIT_CODE = 'SETDA_BAG_UMUM';

    public function __construct(
        private readonly PositionIdentityResolver $positionIdentityResolver,
        private readonly DocumentOrganizationScope $documentOrganizationScope,
        private readonly LsSppSubmitGate $lsSppSubmitGate,
        private readonly LsSppWorkflowHandoffService $lsSppWorkflowHandoff,
    ) {}

    protected function cleanupUnpersistedSourceArtifact(
        ?StagedDocumentArtifact $stagedArtifact,
        DocumentArtifactPersistenceService $artifactPersistence,
    ): void {
        if (! $stagedArtifact instanceof StagedDocumentArtifact) {
            return;
        }

        try {
            $artifactPersistence->discardUnpersistedSourceArtifact($stagedArtifact);
        } catch (Throwable $cleanupException) {
            Log::channel('payment_ls')->critical('SPP LS private artifact cleanup failed', [
                'artifact_public_id' => $stagedArtifact->publicId,
                'exception' => class_basename($cleanupException),
            ]);
            report($cleanupException);
        }
    }

    protected function cleanupUnpersistedAttachmentArtifact(
        ?StagedDocumentArtifact $stagedArtifact,
        DocumentArtifactPersistenceService $artifactPersistence,
    ): void {
        if (! $stagedArtifact instanceof StagedDocumentArtifact) {
            return;
        }

        try {
            $artifactPersistence->discardUnpersistedAttachmentArtifact($stagedArtifact);
        } catch (Throwable $cleanupException) {
            Log::channel('payment_ls')->critical('SPP LS private attachment cleanup failed', [
                'artifact_public_id' => $stagedArtifact->publicId,
                'exception' => class_basename($cleanupException),
            ]);
            report($cleanupException);
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
        $contentUrl = route('document.ls.spp.content', ['document' => $encript]);

        $verify = ! is_null($data->verify_spp);

        $dataById = array_column($allJabatan, 'nama', 'id');
        $rejectedBy = $data->rejected_by_spp === null
            ? null
            : ($dataById[$data->rejected_by_spp] ?? null);

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
                         data-toggle="modal"
                         data-target="#FormTTE">
                        <i class="fas fa-file-contract"></i> Sudah Tanda Tangan
                    </span>';
            }

            if ($inSubmit) {
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
        $rejectedBy = $data->rejected_by_spp === null
            ? null
            : ($dataById[$data->rejected_by_spp] ?? null);

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
                                ->orWhereIn(
                                    'document.id_unit_kerja',
                                    $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit),
                                );
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
        YearAccessService $yearAccess,
        DocumentHistoryService $documentHistoryService,
        DocumentArtifactPersistenceService $artifactPersistence,
        ProvisionCanonicalDocumentAction $provisionCanonicalDocument,
    ): JsonResponse {
        $start = microtime(true);
        $sppId = null;
        $validated = $request->validated();

        Log::channel('payment_ls')->info('SPP LS Store request', [
            'nomor_spp' => $validated['nomor_spp'],
            'nominal' => $validated['nominal'],
            'belanja' => $validated['belanja'],
            'rekening_count' => count($validated['rekening']),
            'has_spp' => $request->hasFile('file_spp'),
            'has_spj' => $request->hasFile('file_spj'),
            'has_billing' => $request->hasFile('file_billing'),
            'has_bmd' => $request->hasFile('file_bmd'),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $user = $activePosition->get();

        if (! $user instanceof UserPosition || ! $user->unitKerja) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif atau unit kerja tidak valid.',
            ], 403);
        }

        $userId = $user->id;
        $realActorPosition = $activePosition->real();
        $artifactCreatorUserId = $realActorPosition?->user_id
            ?? $request->user()?->getKey();
        $artifactCreatorPositionId = $realActorPosition?->getKey();
        $artifactCreatorIsActing = $realActorPosition instanceof UserPosition
            && (int) $realActorPosition->getKey() !== (int) $user->getKey();
        $unitKerja = (int) $user->unitKerja->id;
        $selectedYear = $yearAccess->selectedYear();
        $targetBudgetUnitId = $this->resolveSppBudgetUnitId($user);
        $stagedSppArtifact = null;
        $stagedSpjArtifact = null;
        $stagedBmdArtifact = null;
        $stagedBillingArtifact = null;
        $uploadedSpp = $request->file('file_spp');
        $uploadedSpj = $request->file('file_spj');
        $uploadedBmd = $request->file('file_bmd');
        $uploadedBilling = $request->file('file_billing');

        try {
            $sppStream = fopen($uploadedSpp->getPathname(), 'rb');

            if (! is_resource($sppStream)) {
                throw new RuntimeException('spp_upload_stream_unreadable');
            }

            try {
                $stagedSppArtifact = $artifactPersistence->stagePdfStream($sppStream);
            } finally {
                fclose($sppStream);
            }

            $filename_spp = $stagedSppArtifact->publicId.'.pdf';
            $spjStream = fopen($uploadedSpj->getPathname(), 'rb');

            if (! is_resource($spjStream)) {
                throw new RuntimeException('spj_upload_stream_unreadable');
            }

            try {
                $stagedSpjArtifact = $artifactPersistence->stagePdfStream($spjStream);
            } finally {
                fclose($spjStream);
            }

            $filename_spj = $stagedSpjArtifact->publicId.'.pdf';
            $filename_billing = null;

            if ($uploadedBilling !== null) {
                $billingStream = fopen($uploadedBilling->getPathname(), 'rb');

                if (! is_resource($billingStream)) {
                    throw new RuntimeException('billing_upload_stream_unreadable');
                }

                try {
                    $stagedBillingArtifact = $artifactPersistence->stagePdfStream($billingStream);
                } finally {
                    fclose($billingStream);
                }

                $filename_billing = $stagedBillingArtifact->publicId.'.pdf';
            }
            $filename_bmd = null;

            if ($uploadedBmd !== null) {
                $bmdStream = fopen($uploadedBmd->getPathname(), 'rb');

                if (! is_resource($bmdStream)) {
                    throw new RuntimeException('bmd_upload_stream_unreadable');
                }

                try {
                    $stagedBmdArtifact = $artifactPersistence->stagePdfStream($bmdStream);
                } finally {
                    fclose($bmdStream);
                }

                $filename_bmd = $stagedBmdArtifact->publicId.'.pdf';
            }
        } catch (Throwable $e) {
            $this->cleanupUnpersistedSourceArtifact($stagedSppArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedSpjArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedBmdArtifact, $artifactPersistence);
            $this->cleanupUnpersistedAttachmentArtifact($stagedBillingArtifact, $artifactPersistence);
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

        try {
            DB::transaction(function () use (
                $validated,
                $filename_spp,
                $filename_spj,
                $filename_billing,
                $filename_bmd,
                $userId,
                $unitKerja,
                $selectedYear,
                $targetBudgetUnitId,
                $documentHistoryService,
                $artifactPersistence,
                $provisionCanonicalDocument,
                $stagedSppArtifact,
                $stagedSpjArtifact,
                $stagedBmdArtifact,
                $stagedBillingArtifact,
                $uploadedSpp,
                $uploadedSpj,
                $uploadedBmd,
                $uploadedBilling,
                $artifactCreatorUserId,
                $artifactCreatorPositionId,
                $artifactCreatorIsActing,
                $start,
                &$sppId
            ): void {
                Log::channel('payment_ls')->debug('SPP LS Store transaction start', [
                    'nomor_spp' => $validated['nomor_spp'],
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                $lockedBudget = $this->lockAndValidateSppBudget(
                    rekeningItems: $validated['rekening'],
                    subKegiatanId: $validated['sub_kegiatan_id'],
                    submittedNominal: $validated['nominal'],
                    selectedYear: $selectedYear,
                    activeUnitId: $unitKerja,
                    targetBudgetUnitId: $targetBudgetUnitId,
                );
                $rekeningInput = $lockedBudget['rekening'];
                $budgetRows = $lockedBudget['budgets'];

                $spp = $this->saveDocumentData([
                    'nomor' => $validated['nomor_spp'],
                    'src_name' => $filename_spp,
                    'src_type' => Document::TYPE_SPP,
                    'payment_type' => 'LS',
                    'id_unit_kerja' => $unitKerja,
                    'uploaded_by' => $userId,
                    'nominal' => $validated['nominal'],
                    'uraian' => $validated['uraian'],
                    'expenditure_type' => $validated['belanja'],
                ]);
                $sppId = $spp->id;

                $artifactPersistence->finalizeSourceArtifact(
                    stagedArtifact: $stagedSppArtifact,
                    document: $spp,
                    originalName: $uploadedSpp->getClientOriginalName(),
                    createdByUserId: $artifactCreatorUserId === null
                        ? null
                        : (int) $artifactCreatorUserId,
                    sourceSystem: 'application',
                    sourceReferenceType: 'document',
                    sourceReferenceId: (string) $spp->id,
                    metadata: [
                        'document_type' => Document::TYPE_SPP,
                        'payment_type' => 'LS',
                        'storage_strategy' => 'canonical_private_upload',
                    ],
                );

                $provisionCanonicalDocument->handle(
                    documentId: (int) $spp->getKey(),
                    actorUserId: $artifactCreatorUserId === null
                        ? null
                        : (int) $artifactCreatorUserId,
                    actorUserPositionId: $artifactCreatorPositionId === null
                        ? null
                        : (int) $artifactCreatorPositionId,
                    actorIsActing: $artifactCreatorIsActing,
                );

                $documentHistoryService->upload($spp->id, $filename_spp, $unitKerja);
                $spj = $this->saveDocumentData([
                    'reference_id' => $spp->id,
                    'src_name' => $filename_spj,
                    'src_type' => Document::TYPE_SPJ,
                    'payment_type' => 'LS',
                    'id_unit_kerja' => $unitKerja,
                    'uploaded_by' => $userId,
                    'billing' => $filename_billing,
                ]);

                $spjSourceArtifact = $artifactPersistence->finalizeSourceArtifact(
                    stagedArtifact: $stagedSpjArtifact,
                    document: $spj,
                    originalName: $uploadedSpj->getClientOriginalName(),
                    createdByUserId: $artifactCreatorUserId === null
                        ? null
                        : (int) $artifactCreatorUserId,
                    sourceSystem: 'application',
                    sourceReferenceType: 'document',
                    sourceReferenceId: (string) $spj->getKey(),
                    metadata: [
                        'document_type' => Document::TYPE_SPJ,
                        'payment_type' => 'LS',
                        'storage_strategy' => 'canonical_private_upload',
                    ],
                );

                if ($stagedBillingArtifact instanceof StagedDocumentArtifact) {
                    $artifactPersistence->finalizeDocumentAttachment(
                        stagedArtifact: $stagedBillingArtifact,
                        document: $spj,
                        parentArtifact: $spjSourceArtifact,
                        attachmentType: DocumentArtifact::ATTACHMENT_BILLING,
                        originalName: $uploadedBilling?->getClientOriginalName(),
                        createdByUserId: $artifactCreatorUserId === null
                            ? null
                            : (int) $artifactCreatorUserId,
                        metadata: [
                            'document_type' => Document::TYPE_SPJ,
                            'payment_type' => 'LS',
                            'storage_strategy' => 'canonical_private_upload',
                        ],
                    );
                }

                $documentHistoryService->upload($spj->id, $filename_spj, $unitKerja);
                if ($filename_bmd) {
                    $bmd = $this->saveDocumentData([
                        'reference_id' => $spp->id,
                        'src_name' => $filename_bmd,
                        'src_type' => Document::TYPE_BMD,
                        'payment_type' => 'LS',
                        'id_unit_kerja' => $unitKerja,
                        'uploaded_by' => $userId,
                    ]);

                    $artifactPersistence->finalizeSourceArtifact(
                        stagedArtifact: $stagedBmdArtifact,
                        document: $bmd,
                        originalName: $uploadedBmd?->getClientOriginalName(),
                        createdByUserId: $artifactCreatorUserId === null
                            ? null
                            : (int) $artifactCreatorUserId,
                        sourceSystem: 'application',
                        sourceReferenceType: 'document',
                        sourceReferenceId: (string) $bmd->getKey(),
                        metadata: [
                            'document_type' => Document::TYPE_BMD,
                            'payment_type' => 'LS',
                            'storage_strategy' => 'canonical_private_upload',
                        ],
                    );

                    $documentHistoryService->upload($bmd->id, $filename_bmd, $unitKerja);
                }
                $now = now();
                $insertData = [];
                foreach ($rekeningInput as $idRekening => $rekening) {
                    /** @var AnggaranKegiatanTemp $row */
                    $row = $budgetRows->get($idRekening);
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
                        'nominal' => $rekening['nominal'],
                        'pagu' => $row->pagu,
                        'id_spp' => $spp->id,
                        'id_unit_kerja' => $unitKerja,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
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
        } catch (ValidationException $e) {
            $this->cleanupUnpersistedSourceArtifact($stagedSppArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedSpjArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedBmdArtifact, $artifactPersistence);
            $this->cleanupUnpersistedAttachmentArtifact($stagedBillingArtifact, $artifactPersistence);

            throw $e;
        } catch (Throwable $e) {
            $this->cleanupUnpersistedSourceArtifact($stagedSppArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedSpjArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedBmdArtifact, $artifactPersistence);
            $this->cleanupUnpersistedAttachmentArtifact($stagedBillingArtifact, $artifactPersistence);
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
                throw new RuntimeException('Active position not resolved');
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
        string $id,
        ActivePositionService $activePosition,
        YearAccessService $yearAccess,
        DocumentHistoryService $documentHistoryService,
        DocumentArtifactPersistenceService $artifactPersistence,
        ProvisionCanonicalDocumentAction $provisionCanonicalDocument,
    ): JsonResponse {
        $start = microtime(true);
        $validated = $request->validated();

        Log::channel('payment_ls')->info('SPP LS Update request', [
            'hash' => $id,
            'has_nomor_spp' => $request->has('nomor_spp'),
            'has_nominal' => $request->has('nominal'),
            'has_belanja' => $request->has('belanja'),
            'rekening_count' => count($validated['rekening']),
            'has_file_spp' => $request->hasFile('file_spp'),
            'has_spj' => $request->hasFile('file_spj'),
            'has_billing' => $request->hasFile('file_billing'),
            'has_bmd' => $request->hasFile('file_bmd'),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        $user = $activePosition->get();

        if (! $user instanceof UserPosition || ! $user->unitKerja) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif atau unit kerja tidak valid.',
            ], 403);
        }

        $unitKerjaId = (int) $user->unitKerja->id;
        $selectedYear = $yearAccess->selectedYear();
        $realActorPosition = $activePosition->real();
        $artifactCreatorUserId = $realActorPosition?->user_id
            ?? $request->user()?->getKey();
        $artifactCreatorPositionId = $realActorPosition?->getKey();
        $artifactCreatorIsActing = $realActorPosition instanceof UserPosition
            && (int) $realActorPosition->getKey() !== (int) $user->getKey();

        try {
            $decryptedId = EncryptedId::decode($id);
        } catch (Throwable $e) {
            Log::channel('payment_ls')->warning('SPP LS Update invalid ID', [
                'hash' => $id,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'ID tidak valid',
            ], 400);
        }

        $stagedSppArtifact = null;
        $stagedSpjArtifact = null;
        $stagedBmdArtifact = null;
        $stagedBillingArtifact = null;
        $uploadedSpp = $request->file('file_spp');
        $uploadedSpj = $request->file('file_spj');
        $uploadedBilling = $request->file('file_billing');
        $requiresBmdDocument = count(array_intersect(
            array_map('intval', explode(',', (string) $validated['belanja'])),
            [1, 2],
        )) > 0;
        $uploadedBmd = $requiresBmdDocument ? $request->file('file_bmd') : null;
        $sppReplacementOriginalName = $uploadedSpp?->getClientOriginalName();
        $spjReplacementOriginalName = $uploadedSpj?->getClientOriginalName();
        $bmdReplacementOriginalName = $uploadedBmd?->getClientOriginalName();
        $billingReplacementOriginalName = $uploadedBilling?->getClientOriginalName();

        try {
            if ($uploadedSpj !== null || $uploadedBilling !== null) {
                $this->provisionLegacyRelatedDocumentBeforeReplacement(
                    sppId: $decryptedId,
                    documentType: Document::TYPE_SPJ,
                    unitKerjaId: $unitKerjaId,
                    selectedYear: $selectedYear,
                    provisionCanonicalDocument: $provisionCanonicalDocument,
                    actorUserId: $artifactCreatorUserId === null
                        ? null
                        : (int) $artifactCreatorUserId,
                    actorUserPositionId: $artifactCreatorPositionId === null
                        ? null
                        : (int) $artifactCreatorPositionId,
                    actorIsActing: $artifactCreatorIsActing,
                );
            }

            if ($uploadedBilling !== null) {
                $this->provisionLegacyBillingBeforeReplacement(
                    sppId: $decryptedId,
                    unitKerjaId: $unitKerjaId,
                    selectedYear: $selectedYear,
                    artifactPersistence: $artifactPersistence,
                    actorUserId: $artifactCreatorUserId === null
                        ? null
                        : (int) $artifactCreatorUserId,
                );
            }

            if ($uploadedBmd !== null) {
                $this->provisionLegacyRelatedDocumentBeforeReplacement(
                    sppId: $decryptedId,
                    documentType: Document::TYPE_BMD,
                    unitKerjaId: $unitKerjaId,
                    selectedYear: $selectedYear,
                    provisionCanonicalDocument: $provisionCanonicalDocument,
                    actorUserId: $artifactCreatorUserId === null
                        ? null
                        : (int) $artifactCreatorUserId,
                    actorUserPositionId: $artifactCreatorPositionId === null
                        ? null
                        : (int) $artifactCreatorPositionId,
                    actorIsActing: $artifactCreatorIsActing,
                );
            }

            if ($uploadedSpp !== null) {
                $sppStream = fopen($uploadedSpp->getPathname(), 'rb');

                if (! is_resource($sppStream)) {
                    throw new RuntimeException('spp_replacement_stream_unreadable');
                }

                try {
                    $stagedSppArtifact = $artifactPersistence->stagePdfStream($sppStream);
                } finally {
                    fclose($sppStream);
                }
            }

            if ($uploadedBilling !== null) {
                $billingStream = fopen($uploadedBilling->getPathname(), 'rb');

                if (! is_resource($billingStream)) {
                    throw new RuntimeException('billing_replacement_stream_unreadable');
                }

                try {
                    $stagedBillingArtifact = $artifactPersistence->stagePdfStream($billingStream);
                } finally {
                    fclose($billingStream);
                }
            }

            if ($uploadedSpj !== null) {
                $spjStream = fopen($uploadedSpj->getPathname(), 'rb');

                if (! is_resource($spjStream)) {
                    throw new RuntimeException('spj_replacement_stream_unreadable');
                }

                try {
                    $stagedSpjArtifact = $artifactPersistence->stagePdfStream($spjStream);
                } finally {
                    fclose($spjStream);
                }
            }

            if ($uploadedBmd !== null) {
                $bmdStream = fopen($uploadedBmd->getPathname(), 'rb');

                if (! is_resource($bmdStream)) {
                    throw new RuntimeException('bmd_replacement_stream_unreadable');
                }

                try {
                    $stagedBmdArtifact = $artifactPersistence->stagePdfStream($bmdStream);
                } finally {
                    fclose($bmdStream);
                }
            }

            DB::transaction(function () use (
                $validated,
                $decryptedId,
                $unitKerjaId,
                $selectedYear,
                $user,
                $documentHistoryService,
                $artifactPersistence,
                $provisionCanonicalDocument,
                $artifactCreatorUserId,
                $artifactCreatorPositionId,
                $artifactCreatorIsActing,
                $sppReplacementOriginalName,
                $spjReplacementOriginalName,
                $bmdReplacementOriginalName,
                $billingReplacementOriginalName,
                $start,
                $stagedSppArtifact,
                $stagedSpjArtifact,
                $stagedBmdArtifact,
                $stagedBillingArtifact,
            ): void {
                Log::channel('payment_ls')->debug('SPP LS Update transaction start', [
                    'id_spp' => $decryptedId,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                /** @var Document|null $spp */
                $spp = Document::query()
                    ->forPaymentType('LS')
                    ->ofType(Document::TYPE_SPP)
                    ->whereKey($decryptedId)
                    ->where('id_unit_kerja', $unitKerjaId)
                    ->whereYear('created_at', $selectedYear)
                    ->lockForUpdate()
                    ->first();

                if (! $spp instanceof Document) {
                    throw ValidationException::withMessages([
                        'id' => 'Dokumen SPP tidak ditemukan untuk unit dan tahun anggaran aktif.',
                    ]);
                }

                $replaceableDraftWorkflow = $this->assertLockedSppCanBeUpdated($spp);

                $targetBudgetUnitId = $this->resolveSppBudgetUnitId($user);
                $lockedBudget = $this->lockAndValidateSppBudget(
                    rekeningItems: $validated['rekening'],
                    subKegiatanId: $validated['sub_kegiatan_id'],
                    submittedNominal: $validated['nominal'],
                    selectedYear: $selectedYear,
                    activeUnitId: $unitKerjaId,
                    targetBudgetUnitId: $targetBudgetUnitId,
                    excludedSppId: $decryptedId,
                );
                $rekeningInput = $lockedBudget['rekening'];
                $budgetRows = $lockedBudget['budgets'];

                $existing = AnggaranKegiatan::query()
                    ->forYear($selectedYear)
                    ->forSpp($decryptedId)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(static fn (AnggaranKegiatan $row): string => (string) $row->id_rekening);

                $dataSpp = [
                    'nomor' => $validated['nomor_spp'],
                    'nominal' => $validated['nominal'],
                    'uraian' => $validated['uraian'],
                    'expenditure_type' => $validated['belanja'],
                    'id_unit_kerja' => $unitKerjaId,
                    'uploaded_by' => $user->id,
                    'rejected_by' => null,
                    'notes' => null,
                    'assigned_to' => null,
                    'submit' => null,
                    'users_to' => null,
                ];

                $parentArtifact = null;

                if ($stagedSppArtifact instanceof StagedDocumentArtifact) {
                    $parentArtifact = $this->lockedCurrentSourceArtifact(
                        document: $spp,
                        field: 'file_spp',
                        label: 'SPP',
                    );
                    $dataSpp['src_name'] = $stagedSppArtifact->publicId.'.pdf';
                    $dataSpp['status'] = null;
                }

                $spp->fill($dataSpp);
                $spp->save();

                if ($stagedSppArtifact instanceof StagedDocumentArtifact) {
                    $artifactPersistence->finalizeSourceArtifact(
                        stagedArtifact: $stagedSppArtifact,
                        document: $spp,
                        parentArtifact: $parentArtifact,
                        originalName: $sppReplacementOriginalName,
                        createdByUserId: $artifactCreatorUserId === null
                            ? null
                            : (int) $artifactCreatorUserId,
                        sourceSystem: 'application',
                        sourceReferenceType: 'document',
                        sourceReferenceId: (string) $spp->getKey(),
                        metadata: [
                            'document_type' => Document::TYPE_SPP,
                            'payment_type' => 'LS',
                            'storage_strategy' => 'canonical_private_replacement',
                            'operation' => 'update',
                            'replaced_artifact_public_id' => $parentArtifact?->public_id,
                        ],
                        replaceableDraftWorkflow: $replaceableDraftWorkflow,
                        actorUserPositionId: $artifactCreatorPositionId === null
                            ? null
                            : (int) $artifactCreatorPositionId,
                        actorIsActing: $artifactCreatorIsActing,
                    );

                    $provisionCanonicalDocument->handle(
                        documentId: (int) $spp->getKey(),
                        actorUserId: $artifactCreatorUserId === null
                            ? null
                            : (int) $artifactCreatorUserId,
                        actorUserPositionId: $artifactCreatorPositionId === null
                            ? null
                            : (int) $artifactCreatorPositionId,
                        actorIsActing: $artifactCreatorIsActing,
                    );
                }

                $documentHistoryService->edited($spp->id, $spp->src_name);

                /** @var Document|null $spj */
                $spj = Document::query()
                    ->forPaymentType('LS')
                    ->ofType(Document::TYPE_SPJ)
                    ->where('reference_id', $decryptedId)
                    ->lockForUpdate()
                    ->first();
                $spjParentArtifact = null;

                if ($stagedSpjArtifact instanceof StagedDocumentArtifact && $spj instanceof Document) {
                    $spjParentArtifact = $this->lockedCurrentSourceArtifact(
                        document: $spj,
                        field: 'file_spj',
                        label: 'SPJ',
                    );
                }
                $billing = $stagedBillingArtifact instanceof StagedDocumentArtifact
                    ? $stagedBillingArtifact->publicId.'.pdf'
                    : $spj?->billing;

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

                if ($stagedSpjArtifact instanceof StagedDocumentArtifact) {
                    $dataSpj['src_name'] = $stagedSpjArtifact->publicId.'.pdf';
                    $dataSpj['status'] = null;
                }

                $spj ??= new Document([
                    'reference_id' => $decryptedId,
                    'src_type' => Document::TYPE_SPJ,
                    'payment_type' => 'LS',
                ]);
                $spj->fill($dataSpj);
                $spj->save();

                $spjReplacementArtifact = null;

                if ($stagedSpjArtifact instanceof StagedDocumentArtifact) {
                    $spjReplacementArtifact = $artifactPersistence->finalizeSourceArtifact(
                        stagedArtifact: $stagedSpjArtifact,
                        document: $spj,
                        parentArtifact: $spjParentArtifact,
                        originalName: $spjReplacementOriginalName,
                        createdByUserId: $artifactCreatorUserId === null
                            ? null
                            : (int) $artifactCreatorUserId,
                        sourceSystem: 'application',
                        sourceReferenceType: 'document',
                        sourceReferenceId: (string) $spj->getKey(),
                        metadata: [
                            'document_type' => Document::TYPE_SPJ,
                            'payment_type' => 'LS',
                            'storage_strategy' => $spjParentArtifact instanceof DocumentArtifact
                                ? 'canonical_private_replacement'
                                : 'canonical_private_upload',
                            'operation' => 'update',
                            'replaced_artifact_public_id' => $spjParentArtifact?->public_id,
                        ],
                    );
                }

                if ($stagedBillingArtifact instanceof StagedDocumentArtifact) {
                    $billingParentArtifact = $this->lockedLatestBillingAttachment($spj)
                        ?? $spjReplacementArtifact
                        ?? $this->lockedCurrentSourceArtifact(
                            document: $spj,
                            field: 'file_billing',
                            label: 'SPJ',
                        );

                    $artifactPersistence->finalizeDocumentAttachment(
                        stagedArtifact: $stagedBillingArtifact,
                        document: $spj,
                        parentArtifact: $billingParentArtifact,
                        attachmentType: DocumentArtifact::ATTACHMENT_BILLING,
                        originalName: $billingReplacementOriginalName,
                        createdByUserId: $artifactCreatorUserId === null
                            ? null
                            : (int) $artifactCreatorUserId,
                        metadata: [
                            'document_type' => Document::TYPE_SPJ,
                            'payment_type' => 'LS',
                            'storage_strategy' => $billingParentArtifact->artifact_type === DocumentArtifactType::Attachment
                                ? 'canonical_private_replacement'
                                : 'canonical_private_upload',
                            'operation' => 'update',
                            'replaced_artifact_public_id' => $billingParentArtifact->artifact_type === DocumentArtifactType::Attachment
                                ? $billingParentArtifact->public_id
                                : null,
                        ],
                    );
                }

                $documentHistoryService->edited($spj->id, $spj->src_name);

                $belanja = array_map('intval', explode(',', (string) $validated['belanja']));

                if (count(array_intersect($belanja, [1, 2])) > 0) {
                    /** @var Document|null $bmd */
                    $bmd = Document::query()
                        ->forPaymentType('LS')
                        ->ofType(Document::TYPE_BMD)
                        ->where('reference_id', $decryptedId)
                        ->lockForUpdate()
                        ->first();
                    $bmdParentArtifact = null;

                    if ($stagedBmdArtifact instanceof StagedDocumentArtifact && $bmd instanceof Document) {
                        $bmdParentArtifact = $this->lockedCurrentSourceArtifact(
                            document: $bmd,
                            field: 'file_bmd',
                            label: 'BMD',
                        );
                    }
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

                    if ($stagedBmdArtifact instanceof StagedDocumentArtifact) {
                        $dataBmd['src_name'] = $stagedBmdArtifact->publicId.'.pdf';
                    }

                    $bmd ??= new Document([
                        'reference_id' => $decryptedId,
                        'src_type' => Document::TYPE_BMD,
                        'payment_type' => 'LS',
                    ]);
                    $bmd->fill($dataBmd);
                    $bmd->save();

                    if ($stagedBmdArtifact instanceof StagedDocumentArtifact) {
                        $artifactPersistence->finalizeSourceArtifact(
                            stagedArtifact: $stagedBmdArtifact,
                            document: $bmd,
                            parentArtifact: $bmdParentArtifact,
                            originalName: $bmdReplacementOriginalName,
                            createdByUserId: $artifactCreatorUserId === null
                                ? null
                                : (int) $artifactCreatorUserId,
                            sourceSystem: 'application',
                            sourceReferenceType: 'document',
                            sourceReferenceId: (string) $bmd->getKey(),
                            metadata: [
                                'document_type' => Document::TYPE_BMD,
                                'payment_type' => 'LS',
                                'storage_strategy' => $bmdParentArtifact instanceof DocumentArtifact
                                    ? 'canonical_private_replacement'
                                    : 'canonical_private_upload',
                                'operation' => 'update',
                                'replaced_artifact_public_id' => $bmdParentArtifact?->public_id,
                            ],
                        );
                    }

                    $documentHistoryService->edited($bmd->id, $bmd->src_name);
                }

                $deleteIds = $existing->keys()->diff($rekeningInput->keys());

                if ($deleteIds->isNotEmpty()) {
                    AnggaranKegiatan::query()
                        ->forYear($selectedYear)
                        ->forSpp($decryptedId)
                        ->whereIn('id_rekening', $deleteIds)
                        ->delete();
                }

                $updateData = [];
                $insertData = [];

                foreach ($rekeningInput as $idRekening => $item) {
                    $nominal = $item['nominal'];

                    if ($existing->has($idRekening)) {
                        $updateData[] = [
                            'id_rekening' => $idRekening,
                            'nominal' => $nominal,
                            'updated_at' => now(),
                        ];
                    } else {
                        /** @var AnggaranKegiatanTemp $temp */
                        $temp = $budgetRows[$idRekening];

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
                    AnggaranKegiatan::query()
                        ->forYear($selectedYear)
                        ->forSpp($decryptedId)
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
        } catch (ValidationException $e) {
            $this->cleanupUnpersistedSourceArtifact($stagedSppArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedSpjArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedBmdArtifact, $artifactPersistence);
            $this->cleanupUnpersistedAttachmentArtifact($stagedBillingArtifact, $artifactPersistence);

            throw $e;
        } catch (Throwable $e) {
            $this->cleanupUnpersistedSourceArtifact($stagedSppArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedSpjArtifact, $artifactPersistence);
            $this->cleanupUnpersistedSourceArtifact($stagedBmdArtifact, $artifactPersistence);
            $this->cleanupUnpersistedAttachmentArtifact($stagedBillingArtifact, $artifactPersistence);

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

    /**
     * @param  array<int, array<string, mixed>>  $rekeningItems
     * @return array{
     *     rekening: Collection<string, array<string, mixed>>,
     *     budgets: Collection<string, AnggaranKegiatanTemp>
     * }
     */
    private function lockAndValidateSppBudget(
        array $rekeningItems,
        string $subKegiatanId,
        mixed $submittedNominal,
        int $selectedYear,
        int $activeUnitId,
        int $targetBudgetUnitId,
        ?int $excludedSppId = null,
    ): array {
        $rekeningCollection = collect($rekeningItems);
        $rekeningInput = $rekeningCollection->keyBy(
            static fn (array $item): string => trim((string) $item['id']),
        );

        if ($rekeningCollection->count() !== $rekeningInput->count()) {
            throw ValidationException::withMessages([
                'rekening' => 'Rekening yang sama tidak boleh dipilih lebih dari satu kali.',
            ]);
        }

        $rekeningIds = $rekeningInput->keys()->all();
        $budgetRows = AnggaranKegiatanTemp::query()
            ->forYear($selectedYear)
            ->forUnit($targetBudgetUnitId)
            ->where('kode_sub_kegiatan', $subKegiatanId)
            ->whereIn('id_rekening', $rekeningIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(static fn (AnggaranKegiatanTemp $row): string => (string) $row->id_rekening);

        if ($rekeningInput->keys()->diff($budgetRows->keys())->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rekening' => 'Rekening tidak tersedia untuk sub kegiatan, unit kerja, dan tahun anggaran aktif.',
            ]);
        }

        $submittedTotal = self::decimalToCents($submittedNominal);
        $rekeningTotal = $rekeningInput->sum(
            static fn (array $item): int => self::decimalToCents($item['nominal']),
        );

        if ($submittedTotal !== $rekeningTotal) {
            throw ValidationException::withMessages([
                'nominal' => 'Total nominal harus sama dengan jumlah seluruh nominal rekening.',
            ]);
        }

        $budgetUnitIds = array_values(array_unique([
            $activeUnitId,
            $targetBudgetUnitId,
        ]));
        $realizedQuery = AnggaranKegiatan::query()
            ->forYear($selectedYear)
            ->whereIn('id_unit_kerja', $budgetUnitIds)
            ->whereIn('id_rekening', $rekeningIds)
            ->orderBy('id')
            ->lockForUpdate();

        if ($excludedSppId !== null) {
            $realizedQuery->where('id_spp', '<>', $excludedSppId);
        }

        $realizedAmounts = $realizedQuery
            ->get(['id_rekening', 'nominal'])
            ->groupBy(static fn (AnggaranKegiatan $row): string => (string) $row->id_rekening)
            ->map(static fn ($rows): int => $rows->sum(
                static fn (AnggaranKegiatan $row): int => self::decimalToCents($row->nominal),
            ));

        foreach ($rekeningInput as $accountId => $item) {
            /** @var AnggaranKegiatanTemp $budget */
            $budget = $budgetRows->get($accountId);
            $remainingBudget = self::decimalToCents($budget->pagu)
                - (int) $realizedAmounts->get($accountId, 0);

            if (self::decimalToCents($item['nominal']) > $remainingBudget) {
                throw ValidationException::withMessages([
                    'rekening' => "Nominal rekening {$budget->kode_rekening} melebihi sisa pagu yang tersedia.",
                ]);
            }
        }

        return [
            'rekening' => $rekeningInput,
            'budgets' => $budgetRows,
        ];
    }

    private function assertLockedSppCanBeUpdated(Document $spp): ?DocumentSigningWorkflow
    {
        if ($spp->signed_at !== null || $spp->finished_at !== null) {
            throw ValidationException::withMessages([
                'id' => 'Dokumen yang sudah ditandatangani atau selesai tidak dapat diubah.',
            ]);
        }

        if ($spp->rejected_by === null && $spp->submit_list !== []) {
            throw ValidationException::withMessages([
                'id' => 'Dokumen sedang berada dalam proses persetujuan dan tidak dapat diubah.',
            ]);
        }

        $inProgressWorkflows = DocumentSigningWorkflow::query()
            ->where('document_id', $spp->getKey())
            ->whereIn('status', [
                DocumentSigningWorkflowStatus::Draft->value,
                DocumentSigningWorkflowStatus::Active->value,
                DocumentSigningWorkflowStatus::NeedsReview->value,
            ])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($inProgressWorkflows->isNotEmpty()) {
            $draftWorkflows = $inProgressWorkflows->where(
                'status',
                DocumentSigningWorkflowStatus::Draft,
            );
            $blockingWorkflows = $inProgressWorkflows->reject(
                static fn (DocumentSigningWorkflow $workflow): bool => $workflow->status === DocumentSigningWorkflowStatus::Draft,
            );

            if ($draftWorkflows->count() !== 1 || $blockingWorkflows->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'id' => 'Workflow tanda tangan dokumen masih aktif. Selesaikan proses revisi terlebih dahulu.',
                ]);
            }

            /** @var DocumentSigningWorkflow $draftWorkflow */
            $draftWorkflow = $draftWorkflows->first();

            if ($draftWorkflow->started_at !== null || $draftWorkflow->current_sequence !== null) {
                throw ValidationException::withMessages([
                    'id' => 'Workflow tanda tangan sudah dimulai dan dokumen tidak dapat diubah.',
                ]);
            }

            return $draftWorkflow;
        }

        return null;
    }

    private function lockedCurrentSourceArtifact(
        Document $document,
        string $field,
        string $label,
    ): DocumentArtifact {
        $currentArtifacts = DocumentArtifact::query()
            ->where('document_id', $document->getKey())
            ->where('is_current', true)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($currentArtifacts->count() !== 1) {
            throw ValidationException::withMessages([
                $field => "Artifact canonical aktif {$label} tidak tersedia atau ambigu. Jalankan provisioning/backfill sebelum mengganti file.",
            ]);
        }

        /** @var DocumentArtifact $currentArtifact */
        $currentArtifact = $currentArtifacts->first();

        if ($currentArtifact->artifact_type !== DocumentArtifactType::BeforeSign) {
            throw ValidationException::withMessages([
                $field => "Artifact aktif bukan sumber {$label} yang dapat direvisi.",
            ]);
        }

        return $currentArtifact;
    }

    private function lockedLatestBillingAttachment(Document $spj): ?DocumentArtifact
    {
        /** @var DocumentArtifact|null $attachment */
        $attachment = DocumentArtifact::query()
            ->where('document_id', $spj->getKey())
            ->where('artifact_type', DocumentArtifactType::Attachment->value)
            ->where('source_reference_type', DocumentArtifact::SOURCE_REFERENCE_DOCUMENT_ATTACHMENT)
            ->where('source_reference_id', DocumentArtifact::ATTACHMENT_BILLING)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        return $attachment;
    }

    private function provisionLegacyBillingBeforeReplacement(
        int $sppId,
        int $unitKerjaId,
        int $selectedYear,
        DocumentArtifactPersistenceService $artifactPersistence,
        ?int $actorUserId,
    ): void {
        $stagedArtifact = null;

        try {
            DB::transaction(function () use (
                $sppId,
                $unitKerjaId,
                $selectedYear,
                $artifactPersistence,
                $actorUserId,
                &$stagedArtifact,
            ): void {
                /** @var Document|null $spj */
                $spj = Document::query()
                    ->forPaymentType('LS')
                    ->ofType(Document::TYPE_SPJ)
                    ->where('reference_id', $sppId)
                    ->where('id_unit_kerja', $unitKerjaId)
                    ->whereYear('created_at', $selectedYear)
                    ->lockForUpdate()
                    ->first();

                if (! $spj instanceof Document || blank($spj->billing)) {
                    return;
                }

                if ($this->lockedLatestBillingAttachment($spj) instanceof DocumentArtifact) {
                    return;
                }

                $parentArtifact = $this->lockedCurrentSourceArtifact(
                    document: $spj,
                    field: 'file_billing',
                    label: 'SPJ',
                );
                $legacyBillingName = trim((string) $spj->billing);

                if (basename(str_replace('\\', '/', $legacyBillingName)) !== $legacyBillingName
                    || preg_match('/\.pdf\z/i', $legacyBillingName) !== 1) {
                    throw ValidationException::withMessages([
                        'file_billing' => 'Identitas file Billing lama tidak valid untuk diprovisikan.',
                    ]);
                }

                $legacyBillingPath = public_path('File_Billing'.DIRECTORY_SEPARATOR.$legacyBillingName);

                if (! is_file($legacyBillingPath) || ! is_readable($legacyBillingPath)) {
                    throw ValidationException::withMessages([
                        'file_billing' => 'File Billing lama tidak ditemukan. Pulihkan file legacy sebelum melakukan replacement.',
                    ]);
                }

                $billingStream = fopen($legacyBillingPath, 'rb');

                if (! is_resource($billingStream)) {
                    throw ValidationException::withMessages([
                        'file_billing' => 'File Billing lama tidak dapat dibaca untuk diprovisikan.',
                    ]);
                }

                try {
                    $stagedArtifact = $artifactPersistence->stagePdfStream(
                        $billingStream,
                        $spj->created_at,
                    );
                } finally {
                    fclose($billingStream);
                }

                $artifactPersistence->finalizeDocumentAttachment(
                    stagedArtifact: $stagedArtifact,
                    document: $spj,
                    parentArtifact: $parentArtifact,
                    attachmentType: DocumentArtifact::ATTACHMENT_BILLING,
                    originalName: null,
                    createdByUserId: $actorUserId,
                    metadata: [
                        'document_type' => Document::TYPE_SPJ,
                        'payment_type' => 'LS',
                        'storage_strategy' => 'canonical_private_legacy_provisioning',
                        'legacy_stored_name' => $legacyBillingName,
                        'original_name_available' => false,
                    ],
                );
            });
        } catch (Throwable $exception) {
            $this->cleanupUnpersistedAttachmentArtifact($stagedArtifact, $artifactPersistence);

            throw $exception;
        }
    }

    private function provisionLegacyRelatedDocumentBeforeReplacement(
        int $sppId,
        string $documentType,
        int $unitKerjaId,
        int $selectedYear,
        ProvisionCanonicalDocumentAction $provisionCanonicalDocument,
        ?int $actorUserId,
        ?int $actorUserPositionId,
        bool $actorIsActing,
    ): void {
        /** @var Document|null $relatedDocument */
        $relatedDocument = Document::query()
            ->forPaymentType('LS')
            ->ofType($documentType)
            ->where('reference_id', $sppId)
            ->where('id_unit_kerja', $unitKerjaId)
            ->whereYear('created_at', $selectedYear)
            ->first();

        if (! $relatedDocument instanceof Document || $relatedDocument->artifacts()->where('is_current', true)->exists()) {
            return;
        }

        $provisionCanonicalDocument->handle(
            documentId: (int) $relatedDocument->getKey(),
            actorUserId: $actorUserId,
            actorUserPositionId: $actorUserPositionId,
            actorIsActing: $actorIsActing,
        );
    }

    private function resolveSppBudgetUnitId(UserPosition $position): int
    {
        $activeUnitId = (int) $position->unitKerja?->id;

        if ($position->unitKerja?->kode !== self::SETDA_SOURCE_UNIT_CODE) {
            return $activeUnitId;
        }

        return (int) (UnitKerja::query()
            ->where('kode', self::SETDA_TARGET_UNIT_CODE)
            ->value('id') ?? $activeUnitId);
    }

    private static function decimalToCents(mixed $value): int
    {
        return (int) round((float) $value * 100);
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
        $handoffContext = $this->esignHandoffContext($request, $activePosition, $user);

        Log::channel('payment_ls')->info('SPP LS Submit request', [
            'hash' => $request->id,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        try {
            $docId = EncryptedId::decode($request->id);
        } catch (Throwable) {
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
            DB::transaction(function () use (
                $docId,
                $user,
                $userLevel,
                $userUnit,
                $handoffContext,
                $documentHistoryService,
                $start,
            ) {
                $mainDoc = Document::select(
                    'id',
                    'src_name',
                    'submit',
                    'assigned_to',
                    'rejected_by',
                    'src_type',
                    'payment_type',
                    'reference_id',
                    'parent_id',
                    'id_unit_kerja',
                    'status',
                    'signed_at',
                )
                    ->whereKey($docId)
                    ->forPaymentType('LS')
                    ->ofType(Document::TYPE_SPP)
                    ->whereNull('reference_id')
                    ->whereNull('parent_id')
                    ->lockForUpdate()
                    ->first();

                if (! $mainDoc instanceof Document) {
                    Log::channel('payment_ls')->warning('SPP LS Submit document not found', [
                        'doc_id' => $docId,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);
                    throw new RuntimeException('Dokumen tidak ditemukan');
                }

                if (! $this->canAccessForSubmit($mainDoc, $userLevel, $userUnit)) {
                    Log::channel('payment_ls')->warning('SPP LS Submit forbidden document access', [
                        'doc_id' => $docId,
                        'doc_unit' => $mainDoc->id_unit_kerja ?? null,
                        'assigned_to' => $mainDoc->assigned_to ?? null,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);
                    throw new RuntimeException('Anda tidak berwenang mengakses dokumen ini');
                }

                if ($mainDoc->rejected_by !== null) {
                    Log::channel('payment_ls')->info('SPP LS Submit already rejected', [
                        'doc_id' => $docId,
                        'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                    ]);
                    throw new RuntimeException('Dokumen sudah ditolak');
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

                    throw new RuntimeException($message);
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

                    throw new RuntimeException('Data telah disubmit sebelumnya. Silakan periksa status dokumen.');
                }

                $canonicalGateApplied = $this->lsSppSubmitGate->assertSubsequentHandoff(
                    $mainDoc,
                    $user,
                    $submitList,
                );
                $canonicalHeadPosition = $canonicalGateApplied && $userLevel === 8
                    ? $this->lsSppWorkflowHandoff->assignHeadAndActivate(
                        $mainDoc,
                        $user,
                        $handoffContext,
                    )
                    : null;

                $has5or6 = in_array(5, $submitList, true) || in_array(6, $submitList, true);

                $assignedTo = match ($userLevel) {
                    8 => $canonicalHeadPosition instanceof UserPosition
                        ? (string) $canonicalHeadPosition->jabatan_id
                        : '5,6',
                    5 => '9',
                    6 => '10',
                    9, 10 => $has5or6
                        ? '7'
                        : throw new RuntimeException('Belum melewati verifikator 5 atau 6'),
                    default => throw new RuntimeException('User tidak memiliki hak submit'),
                };

                Log::channel('payment_ls')->debug('SPP LS Submit next assigned', [
                    'doc_id' => $docId,
                    'assigned_to' => $assignedTo,
                    'canonical_gate_applied' => $canonicalGateApplied,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                $submitList[] = $userLevel;
                $newSubmit = implode(',', $submitList);

                Document::whereKey($mainDoc->getKey())
                    ->update([
                        'submit' => $newSubmit,
                        'assigned_to' => $assignedTo,
                        'updated_at' => now(),
                    ]);

                $documentHistoryService->submit(
                    (int) $mainDoc->getKey(),
                    $mainDoc->src_name,
                    $userUnit
                );

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
        } catch (LsSppSubmitGateException $e) {
            Log::channel('payment_ls')->info('SPP LS Submit blocked by canonical gate', [
                'doc_id' => $docId ?? null,
                'reason_code' => $e->reasonCode,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => $e->getMessage(),
                'error' => ['code' => $e->reasonCode],
            ], 409);
        } catch (RuntimeException $e) {
            Log::channel('payment_ls')->info('SPP LS Submit blocked', [
                'doc_id' => $docId ?? null,
                'reason' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400);
        } catch (Throwable $e) {
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
        } catch (Throwable) {

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
        $handoffContext = $this->esignHandoffContext($request, $activePosition, $user);

        try {
            return DB::transaction(function () use (
                $docId,
                $userTo,
                $userJabatan,
                $user,
                $userUnit,
                $handoffContext,
                $documentHistoryService,
                $start,
            ) {

                $firstDoc = Document::select(
                    'id',
                    'src_name',
                    'submit',
                    'assigned_to',
                    'rejected_by',
                    'src_type',
                    'payment_type',
                    'reference_id',
                    'parent_id',
                    'id_unit_kerja',
                    'status',
                    'signed_at',
                )
                    ->whereKey($docId)
                    ->forPaymentType('LS')
                    ->ofType(Document::TYPE_SPP)
                    ->whereNull('reference_id')
                    ->whereNull('parent_id')
                    ->lockForUpdate()
                    ->first();

                if (! $firstDoc instanceof Document) {
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

                $canonicalGateApplied = $this->lsSppSubmitGate->assertInitialHandoffToPptk(
                    $firstDoc,
                    $user,
                );
                $canonicalTargetPosition = $canonicalGateApplied
                    ? $this->lsSppWorkflowHandoff->assignPptkAndActivate(
                        $firstDoc,
                        $user,
                        $userTo,
                        $handoffContext,
                    )
                    : null;
                $targetPositionId = $canonicalTargetPosition?->getKey() ?? $userTo;

                Document::whereKey($firstDoc->getKey())
                    ->update([
                        'submit' => $userJabatan,
                        'assigned_to' => 8,
                        'users_to' => $targetPositionId,
                    ]);

                $documentHistoryService->submit(
                    (int) $firstDoc->getKey(),
                    $firstDoc->src_name,
                    $user->unitKerja->id
                );

                Log::channel('payment_ls')->info('SPP LS Submit PPTK success', [
                    'doc_id' => $docId,
                    'assigned_to' => 8,
                    'users_to' => $targetPositionId,
                    'canonical_gate_applied' => $canonicalGateApplied,
                    'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                ]);

                return response()->json([
                    'status' => 200,
                    'message' => 'Dokumen berhasil disubmit ke PPTK.',
                ], 200);
            });
        } catch (LsSppSubmitGateException $e) {
            Log::channel('payment_ls')->info('SPP LS Submit PPTK blocked by canonical gate', [
                'doc_id' => $docId ?? null,
                'reason_code' => $e->reasonCode,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ]);

            return response()->json([
                'status' => 409,
                'message' => $e->getMessage(),
                'error' => ['code' => $e->reasonCode],
            ], 409);
        } catch (Throwable $e) {

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

    private function esignHandoffContext(
        Request $request,
        ActivePositionService $activePosition,
        UserPosition $effectivePosition,
    ): EsignTransitionContext {
        $realPosition = $activePosition->real();
        $auditPosition = $realPosition instanceof UserPosition
            ? $realPosition
            : $effectivePosition;
        $actorIsActing = (int) $auditPosition->getKey() !== (int) $effectivePosition->getKey();

        return new EsignTransitionContext(
            actorUserId: $request->user()?->getKey() ?? $auditPosition->user_id,
            actorUserPositionId: (int) $auditPosition->getKey(),
            actorIsActing: $actorIsActing,
            reasonCode: 'ls_spp_handoff',
            message: 'Assignment signer canonical diperbarui saat handoff LS SPP.',
            metadata: [
                'effective_actor_position_id' => (int) $effectivePosition->getKey(),
                'effective_actor_role_id' => (int) $effectivePosition->jabatan_id,
            ],
        );
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

        if ($this->documentOrganizationScope->containsUnit($unitKerjaId, $document->id_unit_kerja)) {
            return true;
        }

        $assigned = array_filter(explode(',', (string) $document->assigned_to));

        return in_array((string) $jabatanId, $assigned, true);
    }
}
