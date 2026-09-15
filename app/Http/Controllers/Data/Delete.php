<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\AnggaranKegiatan;
use App\Models\Document;
use App\Models\Payment\GU_SKPD as PaymentGU_SKPD;
use App\Models\Payment\GU_UK as PaymentGU_UK;
use App\Models\Payment\KKPD as PaymentKKPD;
use App\Models\Payment\TU as PaymentTU;
use App\Models\Payment\UP as PaymentUP;
use App\Services\Document\DocumentHistoryService;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Delete extends Controller
{
    public function __construct(private readonly PositionIdentityResolver $positionIdentityResolver) {}

    public function delete(
        Request $request,
        DocumentHistoryService $documentHistoryService,
        ActivePositionService $activePosition
    ) {
        Log::channel('module_document_data')->info('Document Delete Request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('module_document_data')->warning('Document Delete Invalid ID', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid',
            ], 400);
        }

        $mainDoc = Document::with('unitKerja')->find($id);

        if (! $mainDoc) {
            Log::channel('module_document_data')->warning('Document Delete Not Found', [
                'doc_id' => $id,
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Dokumen tidak ditemukan',
            ], 404);
        }

        $actor = $activePosition->get();
        if (! $this->canAccessDocument($mainDoc, $actor)) {
            Log::channel('module_document_data')->warning('Document Delete Forbidden', [
                'doc_id' => $mainDoc->id,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang menghapus dokumen ini',
            ], 403);
        }

        $paymentType = (string) $mainDoc->payment_type;
        $srcType = (string) $mainDoc->src_type;
        $referenceId = $mainDoc->reference_id;

        if ($paymentType === PaymentGU_SKPD::PAYMENT_TYPE && ! PaymentGU_SKPD::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: GU_SKPD child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen GU SKPD ini bukan entry point yang valid untuk proses hapus.',
            ], 403);
        }

        if ($paymentType === PaymentGU_UK::PAYMENT_TYPE && ! PaymentGU_UK::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: GU_UK child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen GU Unit Kerja ini bukan entry point yang valid untuk proses hapus.',
            ], 403);
        }

        if ($paymentType === PaymentTU::PAYMENT_TYPE && ! PaymentTU::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: TU child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen TU ini bukan entry point yang valid untuk proses hapus.',
            ], 403);
        }

        if ($paymentType === PaymentUP::PAYMENT_TYPE && ! PaymentUP::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: UP child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen UP ini bukan entry point yang valid untuk proses hapus.',
            ], 403);
        }

        if ($paymentType === PaymentKKPD::PAYMENT_TYPE && ! PaymentKKPD::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: KKPD child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen KKPD ini bukan entry point yang valid untuk proses hapus.',
            ], 403);
        }

        if ($this->isLockedNpd($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: NPD already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'NPD sudah disubmit dan tidak dapat dihapus',
            ], 409);
        }

        if ($this->isLockedGuUkTbp($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: TBP already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'TBP yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        if ($this->isLockedGuUkLpjBpp($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: LPJ_BPP already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'LPJ BPP yang sudah disubmit tidak dapat dihapus',
            ], 409);
        }

        if ($this->isLockedGuUkLpj($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: LPJ already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'LPJ yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        if ($this->isLockedGuUkSp2d($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: SP2D already signed', [
                'doc_id' => $mainDoc->id,
                'status' => $mainDoc->status,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SP2D yang sudah ditandatangani tidak dapat dihapus',
            ], 409);
        }

        if ($this->isLockedTuSp2d($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: TU SP2D already signed', [
                'doc_id' => $mainDoc->id,
                'status' => $mainDoc->status,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SP2D TU yang sudah ditandatangani tidak dapat dihapus',
            ], 409);
        }

        if ($this->isLockedKkpdSp2d($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: KKPD SP2D already signed', [
                'doc_id' => $mainDoc->id,
                'status' => $mainDoc->status,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SP2D KKPD yang sudah ditandatangani tidak dapat dihapus',
            ], 409);
        }

        if ($this->isLockedTuPengajuan($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: TU PENGAJUAN already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'Pengajuan TU yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        if ($this->isLockedTuSpp($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: TU SPP already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SPP TU yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        if ($this->isLockedUpSpp($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: UP SPP already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SPP UP yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        if ($this->isLockedUpSpm($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: UP SPM already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SPM UP yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        if ($this->isLockedUpSp2d($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: UP SP2D already signed', [
                'doc_id' => $mainDoc->id,
                'status' => $mainDoc->status,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SP2D UP yang sudah ditandatangani tidak dapat dihapus',
            ], 409);
        }

        if ($this->isLockedKkpdSpp($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: KKPD SPP already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SPP KKPD yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        if ($this->isLockedTuSpm($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: TU SPM already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SPM TU yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        if ($this->isLockedKkpdSpm($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Delete Blocked: KKPD SPM already submitted', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'rejected_by' => $mainDoc->rejected_by,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SPM KKPD yang sudah disubmit tidak dapat dihapus kecuali setelah ditolak',
            ], 409);
        }

        $deleteRules = [
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
            ! isset($deleteRules[$paymentType]) ||
            ! isset($deleteRules[$paymentType][$srcType])
        ) {
            Log::channel('module_document_data')->warning('Document Delete Rule Missing', [
                'doc_id' => $mainDoc->id,
                'payment_type' => $paymentType,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Rule hapus dokumen tidak ditemukan',
            ], 403);
        }

        $rule = $deleteRules[$paymentType][$srcType];

        try {
            Log::channel('module_document_data')->info('Document Delete Transaction Start', [
                'doc_id' => $mainDoc->id,
                'payment_type' => $paymentType,
                'src_type' => $srcType,
            ]);

            DB::transaction(function () use ($rule, $mainDoc, $documentHistoryService) {
                if (
                    $mainDoc->payment_type === 'GU_SKPD' &&
                    $mainDoc->src_type === 'LPJ'
                ) {
                    $lpjId = $mainDoc->id;

                    if ($lpjId) {
                        Document::where('payment_type', 'GU_SKPD')
                            ->where('src_type', 'TBP')
                            ->where('parent_id', $lpjId)
                            ->update([
                                'parent_id' => null,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if (
                    $mainDoc->payment_type === 'GU_UK' &&
                    $mainDoc->src_type === 'LPJ_BPP'
                ) {
                    $lpjId = $mainDoc->id;

                    if ($lpjId) {
                        Document::where('payment_type', 'GU_UK')
                            ->where('src_type', 'TBP')
                            ->where('parent_id', $lpjId)
                            ->update([
                                'parent_id' => null,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if (
                    $mainDoc->payment_type === 'GU_UK' &&
                    $mainDoc->src_type === 'LPJ'
                ) {
                    Document::where('payment_type', 'GU_UK')
                        ->where('src_type', 'LPJ_BPP')
                        ->where('parent_id', $mainDoc->id)
                        ->update([
                            'parent_id' => null,
                            'updated_at' => now(),
                        ]);
                }

                if (
                    $mainDoc->payment_type === PaymentKKPD::PAYMENT_TYPE &&
                    $mainDoc->src_type === 'SPP'
                ) {
                    Document::where('payment_type', PaymentKKPD::PAYMENT_TYPE)
                        ->where('src_type', 'DPR')
                        ->where('reference_id', $mainDoc->id)
                        ->update([
                            'reference_id' => null,
                            'updated_at' => now(),
                        ]);
                }

                if (
                    $mainDoc->payment_type === PaymentKKPD::PAYMENT_TYPE &&
                    $mainDoc->src_type === 'SPM'
                ) {
                    Document::where('payment_type', PaymentKKPD::PAYMENT_TYPE)
                        ->where('src_type', 'SPP')
                        ->where('id', $mainDoc->reference_id)
                        ->update([
                            'updated_at' => now(),
                        ]);
                }

                Document::where('id', $rule['id'])->delete();
                AnggaranKegiatan::where('id_spp', $rule['id'])->delete();
                $documentHistoryService->delete(
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

                    $query->delete();

                    foreach ($relatedDocs as $doc) {
                        $documentHistoryService->delete(
                            $doc->id,
                            $doc->src_name,
                            $doc->id_unit_kerja
                        );
                    }
                }

            });
        } catch (\Throwable $e) {
            Log::channel('module_document_data')->error('Document Delete Transaction Failed', [
                'doc_id' => $mainDoc->id ?? $id,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan saat menghapus dokumen',
            ], 500);
        }

        Log::channel('module_document_data')->info('Document Delete Success', [
            'doc_id' => $mainDoc->id,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Hapus Dokumen Sukses',
        ]);
    }

    private function canAccessDocument(Document $document, $position): bool
    {
        if (! $position || ! $position->jabatan) {
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
                && $document->src_type === 'DPR'
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

        $allowedRoles = $this->allowedRolesForDelete(
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
                return (int) $document->id_unit_kerja === (int) $unitKerjaId
                    && $this->positionIdentityResolver->contains(
                        $this->positionIdentityResolver->pptkActorPosition($position),
                        (int) $document->uploaded_by,
                    );
            }

            if ($document->src_type === 'LPJ') {
                return $jabatanId === 9 && (int) $document->id_unit_kerja === (int) $unitKerjaId;
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

                if ($jabatanId === 4 && $this->positionIdentityResolver->contains($position, (int) $document->uploaded_by)) {
                    return true;
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

        if (in_array($document->payment_type, ['LS', 'LS_GAJI'], true) && $document->src_type === 'SP2D') {
            if ($jabatanId !== 4) {
                return false;
            }

            if ($this->positionIdentityResolver->contains($position, (int) $document->uploaded_by)) {
                return true;
            }

            if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                return true;
            }

            return (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'NPD' && $jabatanId === 8) {
            return (int) $document->id_unit_kerja === (int) $unitKerjaId
                && $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->pptkActorPosition($position),
                    (int) $document->uploaded_by,
                );
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'TBP') {
            if (! in_array($jabatanId, [9], true)) {
                return false;
            }

            if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                return true;
            }

            return (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'LPJ') {
            if (! in_array($jabatanId, [9], true)) {
                return false;
            }

            if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                return true;
            }

            return (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'SPM') {
            if (! in_array($jabatanId, [7], true)) {
                return false;
            }

            if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                return true;
            }

            return (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE) {
            if ($document->src_type === 'SP2D') {
                if ($jabatanId !== 4) {
                    return false;
                }

                if ($this->positionIdentityResolver->contains($position, (int) $document->uploaded_by)) {
                    return true;
                }

                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if ((int) $document->id_unit_kerja !== (int) $unitKerjaId) {
                return false;
            }

            if ($document->src_type === 'PENGAJUAN' && $jabatanId === 8) {
                return $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->pptkActorPosition($position),
                    (int) $document->uploaded_by,
                );
            }

            if ($document->src_type === 'SPP' && in_array($jabatanId, [9, 10], true)) {
                $assigned = array_filter(explode(',', (string) $document->assigned_to));
                if (in_array((string) $jabatanId, $assigned, true)) {
                    return true;
                }

                if (! is_null($document->rejected_by)) {
                    $submit = array_filter(explode(',', (string) $document->submit));

                    return in_array((string) $jabatanId, $submit, true);
                }

                return false;
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
            if ($document->src_type === 'SP2D') {
                if ($jabatanId !== 4) {
                    return false;
                }

                if ($this->positionIdentityResolver->contains($position, (int) $document->uploaded_by)) {
                    return true;
                }

                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if ($document->src_type === 'SPP' && $jabatanId === 9) {
                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                return (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
            }

            if ($document->src_type === 'SPM' && $jabatanId === 7) {
                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                return (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
            }

            return false;
        }

        if ($document->payment_type === PaymentKKPD::PAYMENT_TYPE) {
            if ($document->src_type === 'DPR' && $jabatanId === 8) {
                return (int) $document->id_unit_kerja === (int) $unitKerjaId
                    && $this->positionIdentityResolver->contains(
                        $this->positionIdentityResolver->pptkActorPosition($position),
                        (int) $document->uploaded_by,
                    );
            }

            if ($document->src_type === 'SPP' && $jabatanId === 9) {
                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                return (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
            }

            if ($document->src_type === 'SPM' && $jabatanId === 7) {
                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                return (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
            }

            if ($document->src_type === 'SP2D') {
                if (! in_array($jabatanId, [4, 2, 3], true)) {
                    return false;
                }

                if ($jabatanId === 4 && $this->positionIdentityResolver->contains($position, (int) $document->uploaded_by)) {
                    return true;
                }

                if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
                    return true;
                }

                $assigned = array_filter(explode(',', (string) $document->assigned_to));

                return in_array((string) $jabatanId, $assigned, true);
            }

            if ((int) $document->id_unit_kerja !== (int) $unitKerjaId) {
                return false;
            }

            $assigned = array_filter(explode(',', (string) $document->assigned_to));

            return in_array((string) $jabatanId, $assigned, true);
        }

        if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
            return true;
        }

        if ((int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId) {
            return true;
        }

        $assigned = array_filter(explode(',', (string) $document->assigned_to));

        return in_array((string) $jabatanId, $assigned, true);
    }

    private function allowedRolesForDelete(string $paymentType, string $srcType): ?array
    {
        $rules = [
            'LS' => [
                'SPP' => [9, 10],
                'SPM' => [7],
                'SP2D' => [4],
            ],
            'LS_GAJI' => [
                'SPP' => [9, 10],
                'SPM' => [7],
                'SP2D' => [4],
            ],
            'GU_SKPD' => [
                'NPD' => [8],
                'TBP' => [9],
                'LPJ' => [9],
                'SPM' => [7],
                'SP2D' => [4],
            ],
            'GU_UK' => [
                'NPD' => [8],
                'TBP' => [10],
                'LPJ_BPP' => [10],
                'LPJ' => [9],
                'SPM' => [7],
                'SP2D' => [4],
            ],
            'TU' => [
                'PENGAJUAN' => [8],
                'SPP' => [9, 10],
                'SPM' => [7],
                'SP2D' => [4],
            ],
            'UP' => [
                'SPP' => [9],
                'SPM' => [7],
                'SP2D' => [4],
            ],
            'KKPD' => [
                'DPR' => [8],
                'SPP' => [9],
                'SPM' => [7],
                'SP2D' => [4],
            ],
        ];

        return $rules[$paymentType][$srcType] ?? null;
    }

    private function isLockedNpd(Document $document): bool
    {
        if (! in_array($document->payment_type, ['GU_SKPD', 'GU_UK'], true) || $document->src_type !== 'NPD') {
            return false;
        }

        return Document::query()
            ->where('payment_type', $document->payment_type)
            ->where('src_type', 'TBP')
            ->where('reference_id', $document->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function isLockedGuUkTbp(Document $document): bool
    {
        if ($document->payment_type !== PaymentGU_UK::PAYMENT_TYPE || $document->src_type !== 'TBP') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        $submit = array_values(array_filter(explode(',', (string) $document->submit), fn ($v) => trim((string) $v) !== ''));

        return in_array('10', $submit, true);
    }

    private function isLockedGuUkLpjBpp(Document $document): bool
    {
        if ($document->payment_type !== PaymentGU_UK::PAYMENT_TYPE || $document->src_type !== 'LPJ_BPP') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        $submit = array_values(array_filter(explode(',', (string) $document->submit), fn ($v) => trim((string) $v) !== ''));

        return in_array('10', $submit, true);
    }

    private function isLockedGuUkLpj(Document $document): bool
    {
        if ($document->payment_type !== PaymentGU_UK::PAYMENT_TYPE || $document->src_type !== 'LPJ') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        return trim((string) $document->submit) !== '';
    }

    private function isLockedGuUkSp2d(Document $document): bool
    {
        if ($document->payment_type !== PaymentGU_UK::PAYMENT_TYPE || $document->src_type !== 'SP2D') {
            return false;
        }

        return trim((string) $document->status) !== '';
    }

    private function isLockedTuSp2d(Document $document): bool
    {
        if ($document->payment_type !== PaymentTU::PAYMENT_TYPE || $document->src_type !== 'SP2D') {
            return false;
        }

        return trim((string) $document->status) !== '';
    }

    private function isLockedKkpdSp2d(Document $document): bool
    {
        if ($document->payment_type !== PaymentKKPD::PAYMENT_TYPE || $document->src_type !== 'SP2D') {
            return false;
        }

        return trim((string) $document->status) !== '';
    }

    private function isLockedTuPengajuan(Document $document): bool
    {
        if ($document->payment_type !== PaymentTU::PAYMENT_TYPE || $document->src_type !== 'PENGAJUAN') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        $submit = array_values(array_filter(explode(',', (string) $document->submit), fn ($v) => trim((string) $v) !== ''));

        return in_array('8', $submit, true);
    }

    private function isLockedTuSpp(Document $document): bool
    {
        if ($document->payment_type !== PaymentTU::PAYMENT_TYPE || $document->src_type !== 'SPP') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        $submit = array_values(array_filter(explode(',', (string) $document->submit), fn ($v) => trim((string) $v) !== ''));

        return in_array('9', $submit, true) || in_array('10', $submit, true);
    }

    private function isLockedUpSpp(Document $document): bool
    {
        if ($document->payment_type !== PaymentUP::PAYMENT_TYPE || $document->src_type !== 'SPP') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        $submit = array_values(array_filter(explode(',', (string) $document->submit), fn ($v) => trim((string) $v) !== ''));

        return in_array('9', $submit, true);
    }

    private function isLockedUpSpm(Document $document): bool
    {
        if ($document->payment_type !== PaymentUP::PAYMENT_TYPE || $document->src_type !== 'SPM') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        return trim((string) $document->submit) !== '';
    }

    private function isLockedUpSp2d(Document $document): bool
    {
        if ($document->payment_type !== PaymentUP::PAYMENT_TYPE || $document->src_type !== 'SP2D') {
            return false;
        }

        return trim((string) $document->status) !== '';
    }

    private function isLockedKkpdSpp(Document $document): bool
    {
        if ($document->payment_type !== PaymentKKPD::PAYMENT_TYPE || $document->src_type !== 'SPP') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        return trim((string) $document->submit) !== '';
    }

    private function isLockedTuSpm(Document $document): bool
    {
        if ($document->payment_type !== PaymentTU::PAYMENT_TYPE || $document->src_type !== 'SPM') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        $submit = array_values(array_filter(explode(',', (string) $document->submit), fn ($v) => trim((string) $v) !== ''));

        return in_array('7', $submit, true);
    }

    private function isLockedKkpdSpm(Document $document): bool
    {
        if ($document->payment_type !== PaymentKKPD::PAYMENT_TYPE || $document->src_type !== 'SPM') {
            return false;
        }

        if (! is_null($document->rejected_by)) {
            return false;
        }

        return trim((string) $document->submit) !== '';
    }
}
