<?php

declare(strict_types=1);

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Payment\GU_SKPD as PaymentGU_SKPD;
use App\Models\Payment\GU_UK as PaymentGU_UK;
use App\Models\Payment\KKPD as PaymentKKPD;
use App\Models\Payment\TU as PaymentTU;
use App\Models\Payment\UP as PaymentUP;
use App\Models\UserPosition;
use App\Services\Document\DocumentHistoryService;
use App\Services\Document\DocumentOrganizationScope;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Services\User\YearAccessService;
use App\Support\EncryptedId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Denied extends Controller
{
    public function __construct(
        private readonly PositionIdentityResolver $positionIdentityResolver,
        private readonly DocumentOrganizationScope $documentOrganizationScope,
    ) {}

    public function denied(
        Request $request,
        DocumentHistoryService $documentHistoryService,
        ActivePositionService $activePosition,
        YearAccessService $yearAccess,
    ): JsonResponse {
        Log::channel('module_document_data')->info('Document Denied Request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('module_document_data')->warning('Document Denied Invalid ID', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid',
            ], 400);
        }

        $notes = trim((string) $request->notes);
        if ($notes === '' || mb_strlen($notes) > 255) {
            Log::channel('module_document_data')->warning('Document Denied Missing Notes', [
                'hash' => $request->id,
            ]);

            return response()->json([
                'status' => 422,
                'message' => $notes === ''
                    ? 'Catatan penolakan wajib diisi'
                    : 'Catatan penolakan maksimal 255 karakter',
            ], 422);
        }
        $userData = $activePosition->get();
        if (! $userData instanceof UserPosition || ! $userData->jabatan) {
            Log::channel('module_document_data')->warning('Document Denied Invalid Actor Position');

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid',
            ], 403);
        }
        $jabatanId = (int) $userData->jabatan->id;

        $mainDoc = Document::with('unitKerja')->find($id);

        if (! $mainDoc) {
            Log::channel('module_document_data')->warning('Document Denied Not Found', [
                'doc_id' => $id,
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Dokumen tidak ditemukan',
            ], 404);
        }

        if (
            ! $yearAccess->canWrite($userData)
            || $yearAccess->selectedYear() > $yearAccess->currentYear()
            || (int) $mainDoc->created_at?->year !== $yearAccess->selectedYear()
        ) {
            return response()->json([
                'status' => 403,
                'message' => 'Dokumen tidak dapat diubah pada tahun anggaran ini',
            ], 403);
        }

        if (! $this->canAccessDocument($mainDoc, $userData)) {
            Log::channel('module_document_data')->warning('Document Denied Forbidden', [
                'doc_id' => $mainDoc->id,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang menolak dokumen ini',
            ], 403);
        }

        $paymentType = (string) $mainDoc->payment_type;
        $srcType = (string) $mainDoc->src_type;
        $referenceId = $mainDoc->reference_id;

        if ($paymentType === PaymentGU_SKPD::PAYMENT_TYPE && ! PaymentGU_SKPD::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Denied Blocked: GU_SKPD child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen GU SKPD ini bukan entry point yang valid untuk proses tolak.',
            ], 403);
        }

        if ($paymentType === PaymentGU_UK::PAYMENT_TYPE && ! PaymentGU_UK::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Denied Blocked: GU_UK child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen GU Unit Kerja ini bukan entry point yang valid untuk proses tolak.',
            ], 403);
        }

        if ($paymentType === PaymentTU::PAYMENT_TYPE && ! PaymentTU::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Denied Blocked: TU child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen TU ini bukan entry point yang valid untuk proses tolak.',
            ], 403);
        }

        if ($paymentType === PaymentUP::PAYMENT_TYPE && ! PaymentUP::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Denied Blocked: UP child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen UP ini bukan entry point yang valid untuk proses tolak.',
            ], 403);
        }

        if ($paymentType === PaymentKKPD::PAYMENT_TYPE && ! PaymentKKPD::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Denied Blocked: KKPD child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen KKPD ini bukan entry point yang valid untuk proses tolak.',
            ], 403);
        }

        if ($this->isLockedNpd($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Denied Blocked: NPD already used by TBP', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'NPD sudah dipakai pada TBP aktif dan tidak dapat ditolak',
            ], 409);
        }

        $stateBlockMessage = $this->validateDeniedState($mainDoc, (int) $jabatanId);
        if (! is_null($stateBlockMessage)) {
            Log::channel('module_document_data')->warning('Document Denied Blocked: invalid workflow state', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
                'payment_type' => $paymentType,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
                'reason' => $stateBlockMessage,
            ]);

            return response()->json([
                'status' => 409,
                'message' => $stateBlockMessage,
            ], 409);
        }

        $deniedRules = [
            'LS' => [
                'SPP' => ['id' => $id, 'reference_id' => $id, 'src_types' => ['SPJ', 'BMD']],
                'SPM' => ['id' => $id, 'reference_id' => $referenceId, 'src_types' => ['SP', 'SPTJM', 'SP_PENGAJUAN']],
                'SP2D' => ['id' => $id],
            ],
            'LS_GAJI' => [
                'SPP' => ['id' => $id, 'reference_id' => $id, 'src_types' => ['SPJ', 'BMD']],
                'SPM' => ['id' => $id, 'reference_id' => $referenceId, 'src_types' => ['SP', 'SPTJM', 'SP_PENGAJUAN']],
                'SP2D' => ['id' => $id],
            ],
            'GU_SKPD' => PaymentGU_SKPD::actionRules($id, $referenceId),
            'GU_UK' => PaymentGU_UK::actionRules($id, $referenceId),
            'TU' => PaymentTU::actionRules($id, $referenceId),
            'UP' => PaymentUP::actionRules($id, $referenceId),
            'KKPD' => PaymentKKPD::actionRules($id, $referenceId),
        ];

        if (
            ! isset($deniedRules[$paymentType]) ||
            ! isset($deniedRules[$paymentType][$srcType])
        ) {
            Log::channel('module_document_data')->warning('Document Denied Rule Missing', [
                'doc_id' => $mainDoc->id,
                'payment_type' => $paymentType,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Rule penolakan tidak ditemukan',
            ], 403);
        }

        $rule = $deniedRules[$paymentType][$srcType];

        try {
            Log::channel('module_document_data')->info('Document Denied Transaction Start', [
                'doc_id' => $mainDoc->id,
            ]);

            DB::transaction(function () use ($rule, $jabatanId, $notes, $mainDoc, $documentHistoryService) {
                Document::where('id', $rule['id'])
                    ->update([
                        'rejected_by' => $jabatanId,
                        'notes' => $notes,
                        'verify' => null,
                    ]);

                $documentHistoryService->reject(
                    $mainDoc->id,
                    $mainDoc->src_name,
                    $mainDoc->id_unit_kerja
                );

                if (isset($rule['reference_id'])) {
                    $query = Document::where('reference_id', $rule['reference_id'])
                        ->where('payment_type', $mainDoc->payment_type);

                    if (isset($rule['src_types'])) {
                        $query->whereIn('src_type', $rule['src_types']);
                    }

                    $relatedDocs = $query->get();

                    $query->update([
                        'rejected_by' => $jabatanId,
                        'notes' => $notes,
                        'verify' => null,
                    ]);

                    foreach ($relatedDocs as $doc) {
                        $documentHistoryService->reject(
                            $doc->id,
                            $doc->src_name,
                            $doc->id_unit_kerja
                        );
                    }
                }

            });
        } catch (\Throwable $e) {
            Log::channel('module_document_data')->error('Document Denied Transaction Failed', [
                'doc_id' => $mainDoc->id ?? $id,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan saat menolak dokumen',
            ], 500);
        }

        Log::channel('module_document_data')->info('Document Denied Success', [
            'doc_id' => $mainDoc->id,
        ]);
        $yearAccess->recordHistoricalWriteUsage($userData);

        return response()->json([
            'status' => 200,
            'message' => 'Dokumen berhasil ditolak',
        ]);
    }

    private function canAccessDocument(Document $document, UserPosition $position): bool
    {
        if (! $position->jabatan) {
            return false;
        }

        $jabatanId = (int) $position->jabatan->id;
        if (
            $jabatanId === 1 &&
            ! (
                $document->payment_type === PaymentUP::PAYMENT_TYPE
            ) &&
            ! (
                $document->payment_type === PaymentGU_UK::PAYMENT_TYPE
                && in_array($document->src_type, ['LPJ_BPP', 'LPJ'], true)
            ) &&
            ! (
                $document->payment_type === PaymentKKPD::PAYMENT_TYPE
                && $document->src_type === 'SPP'
            ) &&
            ! (
                $document->payment_type === PaymentKKPD::PAYMENT_TYPE
                && $document->src_type === 'SPM'
            ) &&
            ! (
                $document->payment_type === PaymentKKPD::PAYMENT_TYPE
                && $document->src_type === 'SP2D'
            )
        ) {
            return true;
        }

        $allowedRoles = $this->allowedRolesForDenied(
            (string) $document->payment_type,
            (string) $document->src_type
        );
        if (! is_null($allowedRoles) && ! in_array($jabatanId, $allowedRoles, true)) {
            return false;
        }

        $unitKerjaId = $position->unitKerja?->id;
        if (! $unitKerjaId) {
            return false;
        }

        if ($document->payment_type === PaymentGU_UK::PAYMENT_TYPE) {
            if ($document->src_type === 'NPD' && $jabatanId === 8) {
                return $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->pptkActorPosition($position),
                    (int) $document->uploaded_by,
                );
            }

            if ($document->src_type === 'LPJ_BPP' && $jabatanId === 9) {
                $scopeUnitId = $this->resolveScopeUnitId((int) $unitKerjaId);
                if (! $scopeUnitId) {
                    return false;
                }

                if ((int) $document->id_unit_kerja === (int) $scopeUnitId) {
                    return true;
                }

                return $this->documentOrganizationScope->containsUnit($scopeUnitId, $document->id_unit_kerja);
            }

            if ($document->src_type === 'SPM') {
                if (! in_array($jabatanId, [7, 5, 4], true)) {
                    return false;
                }

                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if ($document->src_type === 'SP2D') {
                if (! in_array($jabatanId, [4, 2, 3], true)) {
                    return false;
                }

                if (in_array($jabatanId, [2, 3], true)) {
                    if (! is_null($document->users_to) && $this->positionIdentityResolver->contains(
                        $this->positionIdentityResolver->budActorPosition($position),
                        (int) $document->users_to,
                    )) {
                        return true;
                    }

                    $assigned = array_filter(explode(',', (string) $document->assigned_to));

                    return in_array((string) $jabatanId, $assigned, true);
                }

                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                return true;
            }

            $assigned = array_filter(explode(',', (string) $document->assigned_to));

            return in_array((string) $jabatanId, $assigned, true);
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'TBP') {
            if (! in_array($jabatanId, [5], true)) {
                return false;
            }

            if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                return true;
            }

            return $this->documentOrganizationScope->containsUnit($position, $document->id_unit_kerja);
        }

        if (in_array($document->payment_type, ['LS', 'LS_GAJI'], true) && $document->src_type === 'SP2D') {
            if (in_array($jabatanId, [2, 3], true)) {
                if (! is_null($document->users_to) && $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->budActorPosition($position),
                    (int) $document->users_to,
                )) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'LPJ') {
            if (! in_array($jabatanId, [5, 7], true)) {
                return false;
            }

            if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                return true;
            }

            return $this->documentOrganizationScope->containsUnit($position, $document->id_unit_kerja);
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'SPM') {
            if (! in_array($jabatanId, [4, 5], true)) {
                return false;
            }

            if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                return true;
            }

            return $this->documentOrganizationScope->containsUnit($position, $document->id_unit_kerja);
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'SP2D') {
            if (! in_array($jabatanId, [2, 3], true)) {
                return false;
            }

            return ! is_null($document->users_to)
                && $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->budActorPosition($position),
                    (int) $document->users_to,
                );
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE) {
            if (
                ($document->src_type === 'PENGAJUAN' && in_array($jabatanId, [2, 4], true)) ||
                ($document->src_type === 'SPM' && $jabatanId === 4)
            ) {
                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if ((int) $document->id_unit_kerja !== (int) $unitKerjaId) {
                return false;
            }

            if ($document->src_type === 'SP2D' && in_array($jabatanId, [2, 3], true)) {
                if (! is_null($document->users_to) && $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->budActorPosition($position),
                    (int) $document->users_to,
                )) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if ($document->src_type === 'PENGAJUAN' && $jabatanId === 8) {
                return $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->pptkActorPosition($position),
                    (int) $document->uploaded_by,
                );
            }

            if ($document->src_type === 'SPP' && $jabatanId === 8) {
                if (is_null($document->users_to) || ! $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->pptkActorPosition($position),
                    (int) $document->users_to,
                )) {
                    return false;
                }
            }

            $assigned = array_filter(explode(',', (string) $document->assigned_to));

            return in_array((string) $jabatanId, $assigned, true);
        }

        if ($document->payment_type === PaymentUP::PAYMENT_TYPE) {
            if ($document->src_type === 'SP2D' && in_array($jabatanId, [2, 3], true)) {
                if (! is_null($document->users_to) && $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->budActorPosition($position),
                    (int) $document->users_to,
                )) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if (in_array($document->src_type, ['SPP', 'SPM'], true)) {
                if ($document->src_type === 'SPP' && ! in_array($jabatanId, [5, 7, 9], true)) {
                    return false;
                }

                if ($document->src_type === 'SPM' && ! in_array($jabatanId, [4, 5], true)) {
                    return false;
                }

                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                if ($jabatanId === 5 && $this->documentOrganizationScope->containsUnit($position, $document->id_unit_kerja)) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            return false;
        }

        if ($document->payment_type === PaymentKKPD::PAYMENT_TYPE) {
            if ($document->src_type === 'SP2D' && in_array($jabatanId, [2, 3], true)) {
                if (! is_null($document->users_to) && $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->budActorPosition($position),
                    (int) $document->users_to,
                )) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if (in_array($document->src_type, ['DPR', 'SPP', 'SPM'], true)) {
                if ($document->src_type === 'DPR' && ! in_array($jabatanId, [5, 6, 9], true)) {
                    return false;
                }

                if ($document->src_type === 'SPP' && ! in_array($jabatanId, [5, 7, 9], true)) {
                    return false;
                }

                if ($document->src_type === 'SPM' && ! in_array($jabatanId, [4, 5], true)) {
                    return false;
                }

                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                if ($jabatanId === 5 && $this->documentOrganizationScope->containsUnit($position, $document->id_unit_kerja)) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            return false;
        }

        if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
            return true;
        }

        if ($this->documentOrganizationScope->containsUnit($position, $document->id_unit_kerja)) {
            return true;
        }

        $assigned = array_filter(explode(',', (string) $document->assigned_to));

        return in_array((string) $jabatanId, $assigned, true);
    }

    private function allowedRolesForDenied(string $paymentType, string $srcType): ?array
    {
        $rules = [
            'LS' => [
                'SPP' => [9, 10, 8, 5, 6],
                'SPM' => [4, 5, 6],
                'SP2D' => [2, 3],
            ],
            'LS_GAJI' => [
                'SPP' => [9, 10, 8, 5, 6],
                'SPM' => [4, 5, 6],
                'SP2D' => [2, 3],
            ],
            'GU_SKPD' => [
                'NPD' => [5],
                'TBP' => [5],
                'LPJ' => [5],
                'SPM' => [4, 5],
                'SP2D' => [2, 3],
            ],
            'GU_UK' => [
                'NPD' => [6, 10],
                'TBP' => [6, 10],
                'LPJ_BPP' => [9],
                'LPJ' => [5, 9, 7],
                'SPM' => [5, 4],
                'SP2D' => [2, 3],
            ],
            'TU' => [
                'PENGAJUAN' => [2, 4, 5, 6],
                'SPP' => [9, 10, 8, 5, 6, 7],
                'SPM' => [4, 5, 6],
                'SP2D' => [2, 3],
            ],
            'UP' => [
                'SPP' => [5, 7, 9],
                'SPM' => [4, 5],
                'SP2D' => [2, 3],
            ],
            'KKPD' => [
                'DPR' => [5, 6, 9],
                'SPP' => [5, 7, 9],
                'SPM' => [4, 5],
                'SP2D' => [2, 3],
            ],
        ];

        return $rules[$paymentType][$srcType] ?? null;
    }

    private function isLockedNpd(Document $document): bool
    {
        if (! in_array($document->payment_type, ['GU_SKPD', 'GU_UK'], true) || $document->src_type !== 'NPD') {
            return false;
        }

        // Kunci jika NPD sudah dipakai oleh TBP yang belum dihapus.
        return Document::query()
            ->where('payment_type', $document->payment_type)
            ->where('src_type', 'TBP')
            ->where('reference_id', $document->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function validateDeniedState(Document $document, int $jabatanId): ?string
    {
        if (! is_null($document->rejected_by)) {
            return 'Dokumen sudah ditolak.';
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE && $document->src_type === 'PENGAJUAN') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);

            if (in_array($jabatanId, [5, 6], true)) {
                if (! in_array('8', $submit, true)) {
                    return 'Pengajuan belum disubmit oleh PPTK.';
                }

                if (in_array((string) $jabatanId, $submit, true)) {
                    return 'Pengajuan sudah selesai diproses oleh jabatan ini.';
                }

                return null;
            }

            if ($jabatanId === 4) {
                if (! in_array('5', $submit, true) && ! in_array('6', $submit, true)) {
                    return 'Pengajuan belum disubmit ke verifikator.';
                }

                if (! is_null($document->verify)) {
                    return 'Pengajuan sudah diverifikasi oleh verifikator.';
                }

                return null;
            }

            if ($jabatanId === 2) {
                if (is_null($document->verify) || ! in_array('4', $submit, true)) {
                    return 'Pengajuan belum disubmit ke BUD.';
                }

                if (in_array('2', $status, true)) {
                    return 'Pengajuan sudah ditandatangani oleh BUD.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE && $document->src_type === 'SPP') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);
            $submitCount = array_count_values($submit);
            $isFlowWithBpp = in_array('10', $submit, true);

            if ($jabatanId === 9) {
                if (($submitCount['9'] ?? 0) < 1) {
                    return 'SPP belum disubmit ke PPTK oleh BP.';
                }

                if (($submitCount['5'] ?? 0) < 1) {
                    return 'SPP belum disubmit kembali oleh PA.';
                }

                if (! in_array('9', $status, true)) {
                    return 'SPP belum TTE oleh BP.';
                }

                if (($submitCount['7'] ?? 0) > 0 || ($submitCount['9'] ?? 0) > 1 || $isFlowWithBpp) {
                    return 'SPP sudah selesai diproses oleh BP.';
                }

                return null;
            }

            if ($jabatanId === 10) {
                if (($submitCount['10'] ?? 0) < 1) {
                    return 'SPP belum disubmit ke PPTK oleh BPP.';
                }

                if (($submitCount['6'] ?? 0) < 1) {
                    return 'SPP belum disubmit kembali oleh KPA.';
                }

                if (! in_array('10', $status, true)) {
                    return 'SPP belum TTE oleh BPP.';
                }

                if (($submitCount['7'] ?? 0) > 0 || ($submitCount['10'] ?? 0) > 1 || ! $isFlowWithBpp) {
                    return 'SPP sudah selesai diproses oleh BPP.';
                }

                return null;
            }

            if ($jabatanId === 8) {
                if (($submitCount['9'] ?? 0) < 1 && ($submitCount['10'] ?? 0) < 1) {
                    return 'SPP belum disubmit dari BP/BPP ke PPTK.';
                }

                if (($submitCount['8'] ?? 0) > 0) {
                    return 'SPP sudah selesai diproses oleh PPTK.';
                }

                return null;
            }

            if ($jabatanId === 5) {
                if (($submitCount['9'] ?? 0) < 1) {
                    return 'SPP belum melalui BP.';
                }

                if (($submitCount['8'] ?? 0) < 1) {
                    return 'SPP belum disubmit oleh PPTK.';
                }

                if (($submitCount['5'] ?? 0) > 0) {
                    return 'SPP sudah selesai diproses oleh PA.';
                }

                return null;
            }

            if ($jabatanId === 6) {
                if (($submitCount['10'] ?? 0) < 1) {
                    return 'SPP belum melalui BPP.';
                }

                if (($submitCount['8'] ?? 0) < 1) {
                    return 'SPP belum disubmit oleh PPTK.';
                }

                if (($submitCount['6'] ?? 0) > 0) {
                    return 'SPP sudah selesai diproses oleh KPA.';
                }

                return null;
            }

            if ($jabatanId === 7) {
                if ($isFlowWithBpp) {
                    if (($submitCount['10'] ?? 0) < 2 || ($submitCount['6'] ?? 0) < 1 || ($submitCount['8'] ?? 0) < 1) {
                        return 'SPP belum disubmit ke verifikator sesuai alur BPP.';
                    }
                } else {
                    if (($submitCount['9'] ?? 0) < 2 || ($submitCount['5'] ?? 0) < 1 || ($submitCount['8'] ?? 0) < 1) {
                        return 'SPP belum disubmit ke verifikator sesuai alur BP.';
                    }
                }

                if (! is_null($document->verify)) {
                    return 'SPP sudah diverifikasi.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE && $document->src_type === 'SPM') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);
            $submitCount = array_count_values($submit);

            if ($jabatanId === 5) {
                if (! in_array('7', $submit, true)) {
                    return 'SPM belum disubmit oleh PPK.';
                }

                if (! in_array('5', $status, true)) {
                    return 'SPM belum TTE oleh PA.';
                }

                if (in_array('5', $submit, true)) {
                    return 'SPM sudah selesai diproses oleh PA.';
                }

                return null;
            }

            if ($jabatanId === 6) {
                if (! in_array('7', $submit, true)) {
                    return 'SPM belum disubmit oleh PPK.';
                }

                if (! in_array('6', $status, true)) {
                    return 'SPM belum TTE oleh KPA.';
                }

                if (in_array('6', $submit, true)) {
                    return 'SPM sudah selesai diproses oleh KPA.';
                }

                return null;
            }

            if ($jabatanId === 4) {
                if (! in_array('5', $submit, true) && ! in_array('6', $submit, true)) {
                    return 'SPM belum disubmit ke verifikator.';
                }

                if (! is_null($document->verify)) {
                    return 'SPM sudah diverifikasi.';
                }

                $usedBySp2d = Document::query()
                    ->where('payment_type', PaymentTU::PAYMENT_TYPE)
                    ->where('src_type', 'SP2D')
                    ->where('reference_id', $document->reference_id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($usedBySp2d) {
                    return 'SPM sudah digunakan pada SP2D aktif.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type === PaymentUP::PAYMENT_TYPE && $document->src_type === 'SPP') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);
            $submitCount = array_count_values($submit);

            if ($jabatanId === 9) {
                if (($submitCount['9'] ?? 0) < 1) {
                    return 'SPP belum disubmit oleh BP.';
                }

                if (($submitCount['5'] ?? 0) < 1) {
                    return 'SPP belum disubmit kembali oleh PA.';
                }

                if (! in_array('9', $status, true)) {
                    return 'SPP belum TTE oleh BP.';
                }

                if (($submitCount['9'] ?? 0) > 1) {
                    return 'SPP sudah selesai diproses oleh BP.';
                }

                if (! is_null($document->verify)) {
                    return 'SPP sudah diverifikasi.';
                }

                return null;
            }

            if ($jabatanId === 5) {
                if (($submitCount['9'] ?? 0) < 1) {
                    return 'SPP belum disubmit oleh BP.';
                }

                if (! in_array('9', $status, true)) {
                    return 'SPP belum TTE oleh BP.';
                }

                if (($submitCount['5'] ?? 0) > 0) {
                    return 'SPP sudah selesai diproses oleh PA.';
                }

                if (! is_null($document->verify)) {
                    return 'SPP sudah diverifikasi.';
                }

                return null;
            }

            if ($jabatanId === 7) {
                if (($submitCount['9'] ?? 0) < 2 || ($submitCount['5'] ?? 0) < 1) {
                    return 'SPP belum disubmit ke PPK sesuai alur BP.';
                }

                if (! in_array('5', $status, true)) {
                    return 'SPP belum TTE oleh PA.';
                }

                if (! is_null($document->verify)) {
                    return 'SPP sudah diverifikasi.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type === PaymentUP::PAYMENT_TYPE && $document->src_type === 'SPM') {
            $submit = $this->csvToArray($document->submit);

            if ($jabatanId === 5) {
                if (! in_array('7', $submit, true)) {
                    return 'SPM belum disubmit oleh PPK.';
                }

                if (in_array('5', $submit, true)) {
                    return 'SPM sudah selesai diproses oleh PA.';
                }

                if (! is_null($document->verify)) {
                    return 'SPM sudah diverifikasi.';
                }

                return null;
            }

            if ($jabatanId === 4) {
                if (! in_array('7', $submit, true)) {
                    return 'SPM belum disubmit oleh PPK.';
                }

                if (! in_array('5', $submit, true)) {
                    return 'SPM belum disubmit ke verifikator.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type === PaymentUP::PAYMENT_TYPE && $document->src_type === 'SP2D') {
            if (! is_null($document->finished_at)) {
                return 'SP2D sudah selesai dicairkan.';
            }

            return null;
        }

        if ($document->payment_type === PaymentKKPD::PAYMENT_TYPE && $document->src_type === 'SPP') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);
            $submitCount = array_count_values($submit);

            if ($jabatanId === 9) {
                if (($submitCount['9'] ?? 0) < 1 || ($submitCount['5'] ?? 0) < 1) {
                    return 'SPP belum disubmit kembali oleh PA.';
                }

                if (! in_array('9', $status, true)) {
                    return 'SPP belum TTE oleh BP.';
                }

                if (($submitCount['7'] ?? 0) > 0 || ($submitCount['9'] ?? 0) > 1) {
                    return 'SPP sudah selesai diproses oleh BP.';
                }

                if (! is_null($document->verify)) {
                    return 'SPP sudah diverifikasi.';
                }

                return null;
            }

            if ($jabatanId === 5) {
                if (($submitCount['9'] ?? 0) < 1) {
                    return 'SPP belum disubmit oleh BP.';
                }

                if (! in_array('9', $status, true)) {
                    return 'SPP belum TTE oleh BP.';
                }

                if (($submitCount['5'] ?? 0) > 0) {
                    return 'SPP sudah selesai diproses oleh PA.';
                }

                if (! is_null($document->verify)) {
                    return 'SPP sudah diverifikasi.';
                }

                return null;
            }

            if ($jabatanId === 7) {
                if (($submitCount['9'] ?? 0) < 2) {
                    return 'SPP belum disubmit ke PPK oleh BP.';
                }

                if (($submitCount['5'] ?? 0) < 1) {
                    return 'SPP belum disubmit kembali oleh PA.';
                }

                if (! in_array('5', $status, true)) {
                    return 'SPP belum TTE oleh PA.';
                }

                if (! is_null($document->verify)) {
                    return 'SPP sudah diverifikasi.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type === PaymentKKPD::PAYMENT_TYPE && $document->src_type === 'SPM') {
            $submit = $this->csvToArray($document->submit);

            if ($jabatanId === 5) {
                if (! in_array('7', $submit, true)) {
                    return 'SPM belum disubmit oleh PPK.';
                }

                if (in_array('5', $submit, true)) {
                    return 'SPM sudah selesai diproses oleh PA.';
                }

                if (! is_null($document->verify)) {
                    return 'SPM sudah diverifikasi.';
                }

                return null;
            }

            if ($jabatanId === 4) {
                if (! in_array('7', $submit, true)) {
                    return 'SPM belum disubmit oleh PPK.';
                }

                if (! in_array('5', $submit, true)) {
                    return 'SPM belum disubmit ke verifikator.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type === PaymentKKPD::PAYMENT_TYPE && $document->src_type === 'SP2D') {
            if (! is_null($document->finished_at)) {
                return 'SP2D sudah selesai dicairkan.';
            }

            return null;
        }

        if ($document->payment_type === PaymentGU_UK::PAYMENT_TYPE && $document->src_type === 'LPJ') {
            $submit = $this->csvToArray($document->submit);
            $submitCount = array_count_values($submit);

            if ($jabatanId === 5) {
                if (($submitCount['9'] ?? 0) < 1) {
                    return 'LPJ belum disubmit oleh BP.';
                }

                if (($submitCount['5'] ?? 0) >= 1) {
                    return 'LPJ sudah selesai diproses oleh PA.';
                }

                return null;
            }

            if ($jabatanId === 9) {
                if (($submitCount['9'] ?? 0) < 1 || ($submitCount['5'] ?? 0) < 1) {
                    return 'LPJ belum disubmit kembali oleh PA.';
                }

                if (($submitCount['7'] ?? 0) > 0 || ($submitCount['9'] ?? 0) > 1) {
                    return 'LPJ sudah selesai diproses oleh BP.';
                }

                return null;
            }

            if ($jabatanId === 7) {
                if (($submitCount['9'] ?? 0) < 2 || ($submitCount['5'] ?? 0) < 1) {
                    return 'LPJ belum disubmit ke PPK.';
                }

                if (! is_null($document->verify)) {
                    return 'LPJ sudah diverifikasi oleh PPK.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type === PaymentGU_UK::PAYMENT_TYPE && $document->src_type === 'SPM') {
            $submit = $this->csvToArray($document->submit);

            if ($jabatanId === 5) {
                if (! in_array('7', $submit, true)) {
                    return 'SPM belum disubmit oleh PPK.';
                }

                if (in_array('5', $submit, true)) {
                    return 'SPM sudah selesai diproses oleh PA.';
                }

                return null;
            }

            if ($jabatanId === 4) {
                if (! in_array('5', $submit, true)) {
                    return 'SPM belum disubmit ke verifikator.';
                }

                $usedBySp2d = Document::query()
                    ->where('payment_type', PaymentGU_UK::PAYMENT_TYPE)
                    ->where('src_type', 'SP2D')
                    ->where('reference_id', $document->reference_id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($usedBySp2d) {
                    return 'SPM sudah digunakan pada SP2D aktif.';
                }

                return null;
            }

            return null;
        }

        if ($document->payment_type !== PaymentGU_UK::PAYMENT_TYPE || $document->src_type !== 'TBP') {
            if ($document->payment_type !== PaymentGU_UK::PAYMENT_TYPE || $document->src_type !== 'LPJ_BPP') {
                return null;
            }

            $submit = $this->csvToArray($document->submit);

            if (! in_array('10', $submit, true)) {
                return 'LPJ BPP belum disubmit oleh BPP.';
            }

            if ($jabatanId !== 9) {
                return null;
            }

            return null;
        }

        $submit = $this->csvToArray($document->submit);

        if ($jabatanId === 6) {
            if (! in_array('10', $submit, true)) {
                return 'TBP belum disubmit oleh BPP.';
            }

            if (in_array('6', $submit, true)) {
                return 'TBP sudah selesai diproses oleh KPA.';
            }

            return null;
        }

        if ($jabatanId === 10) {
            if (! in_array('6', $submit, true)) {
                return 'TBP belum disubmit kembali oleh KPA.';
            }

            return null;
        }

        return null;
    }

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(explode(',', (string) $csv), fn ($v) => trim((string) $v) !== ''));
    }

    private function resolveScopeUnitId(int $unitKerjaId): ?int
    {
        return $this->documentOrganizationScope->scopeUnitIdForUnit($unitKerjaId);
    }
}
