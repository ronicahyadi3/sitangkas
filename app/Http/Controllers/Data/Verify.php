<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
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

class Verify extends Controller
{
    public function __construct(private readonly PositionIdentityResolver $positionIdentityResolver) {}

    public function verify(
        Request $request,
        DocumentHistoryService $documentHistoryService,
        ActivePositionService $activePosition
    ) {
        Log::channel('module_document_data')->info('Document Verify Request', [
            'hash' => $request->id,
        ]);

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('module_document_data')->warning('Document Verify Invalid ID', [
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
            Log::channel('module_document_data')->warning('Document Verify Not Found', [
                'doc_id' => $id,
            ]);

            return response()->json([
                'status' => 404,
                'message' => 'Dokumen tidak ditemukan',
            ], 404);
        }

        $actor = $activePosition->get();
        if (! $this->canAccessDocument($mainDoc, $actor)) {
            Log::channel('module_document_data')->warning('Document Verify Forbidden', [
                'doc_id' => $mainDoc->id,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang memverifikasi dokumen ini',
            ], 403);
        }

        $paymentType = (string) $mainDoc->payment_type;
        $srcType = (string) $mainDoc->src_type;
        $referenceId = $mainDoc->reference_id;
        $jabatanId = (int) $actor->jabatan->id;

        if ($paymentType === PaymentGU_SKPD::PAYMENT_TYPE && ! PaymentGU_SKPD::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: GU_SKPD child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen GU SKPD ini bukan entry point yang valid untuk proses verifikasi.',
            ], 403);
        }

        if ($paymentType === PaymentTU::PAYMENT_TYPE && ! PaymentTU::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: TU child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen TU ini bukan entry point yang valid untuk proses verifikasi.',
            ], 403);
        }

        if ($paymentType === PaymentUP::PAYMENT_TYPE && ! PaymentUP::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: UP child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen UP ini bukan entry point yang valid untuk proses verifikasi.',
            ], 403);
        }

        if ($paymentType === PaymentKKPD::PAYMENT_TYPE && ! PaymentKKPD::isRootDocumentType($srcType)) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: KKPD child document is not a valid entry point', [
                'doc_id' => $mainDoc->id,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Dokumen KKPD ini bukan entry point yang valid untuk proses verifikasi.',
            ], 403);
        }

        if ($paymentType === 'GU_SKPD' && $srcType === 'NPD') {
            Log::channel('module_document_data')->warning('Document Verify Blocked: NPD has no verify flow', [
                'doc_id' => $mainDoc->id,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'NPD tidak memiliki proses verifikasi',
            ], 409);
        }

        if ($paymentType === 'GU_SKPD' && $srcType === 'LPJ' && $jabatanId !== 7) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: GU_SKPD SPP verify only for PPK', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPP GU SKPD hanya dapat dilakukan oleh PPK.',
            ], 403);
        }

        if ($paymentType === 'GU_SKPD' && $srcType === 'SPM' && $jabatanId !== 4) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: GU_SKPD SPM verify only for verifikator', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPM GU SKPD hanya dapat dilakukan oleh verifikator.',
            ], 403);
        }

        if ($paymentType === 'GU_UK' && $srcType === 'LPJ' && $jabatanId !== 7) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: GU_UK LPJ verify only for PPK', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi LPJ GU UK hanya dapat dilakukan oleh PPK.',
            ], 403);
        }

        if ($paymentType === 'GU_UK' && $srcType === 'SPM' && $jabatanId !== 4) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: GU_UK SPM verify only for verifikator', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPM GU UK hanya dapat dilakukan oleh verifikator.',
            ], 403);
        }

        if ($paymentType === PaymentTU::PAYMENT_TYPE && $srcType === 'PENGAJUAN' && $jabatanId !== 4) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: TU PENGAJUAN verify only for verifikator', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi pengajuan TU hanya dapat dilakukan oleh verifikator.',
            ], 403);
        }

        if ($paymentType === PaymentTU::PAYMENT_TYPE && $srcType === 'SPP' && $jabatanId !== 7) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: TU SPP verify only for PPK', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPP TU hanya dapat dilakukan oleh verifikator.',
            ], 403);
        }

        if ($paymentType === PaymentUP::PAYMENT_TYPE && $srcType === 'SPP' && $jabatanId !== 7) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: UP SPP verify only for PPK', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPP UP hanya dapat dilakukan oleh PPK.',
            ], 403);
        }

        if ($paymentType === PaymentUP::PAYMENT_TYPE && $srcType === 'SPM' && $jabatanId !== 4) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: UP SPM verify only for verifikator', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPM UP hanya dapat dilakukan oleh verifikator.',
            ], 403);
        }

        if ($paymentType === PaymentUP::PAYMENT_TYPE && $srcType === 'SP2D') {
            Log::channel('module_document_data')->warning('Document Verify Blocked: UP SP2D has no verify flow', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SP2D UP tidak memiliki proses verifikasi.',
            ], 409);
        }

        if ($paymentType === PaymentTU::PAYMENT_TYPE && $srcType === 'SPM' && $jabatanId !== 4) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: TU SPM verify only for verifikator', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPM TU hanya dapat dilakukan oleh verifikator.',
            ], 403);
        }

        if ($paymentType === PaymentKKPD::PAYMENT_TYPE && $srcType === 'SPP' && $jabatanId !== 7) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: KKPD SPP verify only for PPK', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPP KKPD hanya dapat dilakukan oleh PPK.',
            ], 403);
        }

        if ($paymentType === PaymentKKPD::PAYMENT_TYPE && $srcType === 'SPM' && $jabatanId !== 4) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: KKPD SPM verify only for verifikator', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Verifikasi SPM KKPD hanya dapat dilakukan oleh verifikator.',
            ], 403);
        }

        if ($paymentType === PaymentKKPD::PAYMENT_TYPE && $srcType === 'SP2D') {
            Log::channel('module_document_data')->warning('Document Verify Blocked: KKPD SP2D has no verify flow', [
                'doc_id' => $mainDoc->id,
                'jabatan_id' => $jabatanId,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'SP2D KKPD tidak memiliki proses verifikasi.',
            ], 409);
        }

        if ($this->isLockedNpd($mainDoc)) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: NPD already processed', [
                'doc_id' => $mainDoc->id,
                'submit' => $mainDoc->submit,
                'status' => $mainDoc->status,
                'verify' => $mainDoc->verify,
            ]);

            return response()->json([
                'status' => 409,
                'message' => 'NPD sudah diproses dan tidak dapat diverifikasi ulang',
            ], 409);
        }

        $stateBlockMessage = $this->validateVerifyState($mainDoc, $jabatanId);
        if (! is_null($stateBlockMessage)) {
            Log::channel('module_document_data')->warning('Document Verify Blocked: invalid workflow state', [
                'doc_id' => $mainDoc->id,
                'payment_type' => $paymentType,
                'src_type' => $srcType,
                'jabatan_id' => $jabatanId,
                'submit' => $mainDoc->submit,
                'status' => $mainDoc->status,
                'verify' => $mainDoc->verify,
                'rejected_by' => $mainDoc->rejected_by,
                'reason' => $stateBlockMessage,
            ]);

            return response()->json([
                'status' => 409,
                'message' => $stateBlockMessage,
            ], 409);
        }

        $verifyRules = [
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
            ! isset($verifyRules[$paymentType]) ||
            ! isset($verifyRules[$paymentType][$srcType])
        ) {
            Log::channel('module_document_data')->warning('Document Verify Rule Missing', [
                'doc_id' => $mainDoc->id,
                'payment_type' => $paymentType,
                'src_type' => $srcType,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Rule verifikasi dokumen tidak ditemukan',
            ], 403);
        }

        $rule = $verifyRules[$paymentType][$srcType];

        try {
            Log::channel('module_document_data')->info('Document Verify Transaction Start', [
                'doc_id' => $mainDoc->id,
            ]);

            DB::transaction(function () use ($rule, $mainDoc, $documentHistoryService) {
                Document::where('id', $rule['id'])
                    ->update(['verify' => 1]);

                $documentHistoryService->verify(
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

                    $query->update(['verify' => 1]);

                    foreach ($relatedDocs as $doc) {
                        $documentHistoryService->verify(
                            $doc->id,
                            $doc->src_name,
                            $doc->id_unit_kerja
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::channel('module_document_data')->error('Document Verify Transaction Failed', [
                'doc_id' => $mainDoc->id ?? $id,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'status' => 500,
                'message' => 'Terjadi kesalahan saat memverifikasi dokumen',
            ], 500);
        }

        Log::channel('module_document_data')->info('Document Verify Success', [
            'doc_id' => $mainDoc->id,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Dokumen berhasil diverifikasi',
        ]);
    }

    private function canAccessDocument(Document $document, $position): bool
    {
        if (! $position || ! $position->jabatan) {
            return false;
        }

        $jabatanId = (int) $position->jabatan->id;
        if ($jabatanId === 4 && $document->src_type === 'SPM') {
            $assigned = $this->csvToArray($document->assigned_to);
            $submit = $this->csvToArray($document->submit);

            return in_array('4', $assigned, true)
                || in_array('5', $submit, true)
                || in_array('6', $submit, true);
        }

        if (
            $jabatanId === 1 &&
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

        $unitKerjaId = $position->unitKerja?->id;
        if (! $unitKerjaId) {
            return false;
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE) {
            if (in_array($jabatanId, [4], true) && in_array($document->src_type, ['PENGAJUAN', 'SPM'], true)) {
                $assigned = $this->csvToArray($document->assigned_to);

                return in_array((string) $jabatanId, $assigned, true);
            }

            if ($document->src_type === 'SPP' && $jabatanId === 7) {
                $assigned = $this->csvToArray($document->assigned_to);
                if (! in_array('7', $assigned, true)) {
                    return false;
                }

                return (int) $document->id_unit_kerja === (int) $unitKerjaId
                    || (int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId;
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

            if ($document->src_type === 'SPP' && $jabatanId === 8) {
                return ! is_null($document->users_to)
                    && $this->positionIdentityResolver->contains(
                        $this->positionIdentityResolver->pptkActorPosition($position),
                        (int) $document->users_to,
                    );
            }

            $assigned = $this->csvToArray($document->assigned_to);

            return in_array((string) $jabatanId, $assigned, true);
        }

        if ((int) $document->id_unit_kerja === (int) $unitKerjaId) {
            return true;
        }

        if ((int) ($document->unitKerja?->skpd_id ?? 0) === (int) $unitKerjaId) {
            return true;
        }

        if ($document->payment_type === PaymentGU_SKPD::PAYMENT_TYPE && $document->src_type === 'SPM') {
            return false;
        }

        $assigned = $this->csvToArray($document->assigned_to);

        return in_array((string) $jabatanId, $assigned, true);
    }

    private function isLockedNpd(Document $document): bool
    {
        if ($document->payment_type !== 'GU_SKPD' || $document->src_type !== 'NPD') {
            return false;
        }

        return ! is_null($document->submit)
            || ! is_null($document->status)
            || ! is_null($document->verify);
    }

    private function validateVerifyState(Document $document, int $jabatanId): ?string
    {
        if (! is_null($document->rejected_by)) {
            return 'Dokumen sudah ditolak.';
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE && $document->src_type === 'PENGAJUAN') {
            $submit = $this->csvToArray($document->submit);

            if ($jabatanId !== 4) {
                return null;
            }

            if (! in_array('5', $submit, true) && ! in_array('6', $submit, true)) {
                return 'Pengajuan belum disubmit ke verifikator.';
            }

            if (! is_null($document->verify)) {
                return 'Pengajuan sudah diverifikasi oleh verifikator.';
            }

            return null;
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE && $document->src_type === 'SPP') {
            $submit = $this->csvToArray($document->submit);
            $submitCount = array_count_values($submit);

            if ($jabatanId !== 7) {
                return null;
            }

            if (! is_null($document->verify)) {
                return 'SPP sudah diverifikasi.';
            }

            $isFlowWithBpp = in_array('10', $submit, true);

            if ($isFlowWithBpp) {
                if (($submitCount['10'] ?? 0) < 2 || ($submitCount['8'] ?? 0) < 1 || ($submitCount['6'] ?? 0) < 1) {
                    return 'SPP belum disubmit ke verifikator sesuai alur BPP.';
                }

                return null;
            }

            if (($submitCount['9'] ?? 0) < 2 || ($submitCount['8'] ?? 0) < 1 || ($submitCount['5'] ?? 0) < 1) {
                return 'SPP belum disubmit ke verifikator sesuai alur BP.';
            }

            return null;
        }

        if ($document->payment_type === PaymentTU::PAYMENT_TYPE && $document->src_type === 'SPM') {
            $submit = $this->csvToArray($document->submit);

            if ($jabatanId !== 4) {
                return null;
            }

            if (! in_array('7', $submit, true)) {
                return 'SPM belum disubmit oleh PPK.';
            }

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

        if ($document->payment_type === PaymentUP::PAYMENT_TYPE && $document->src_type === 'SPP') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);
            $submitCount = array_count_values($submit);

            if ($jabatanId !== 7) {
                return null;
            }

            if (! is_null($document->verify)) {
                return 'SPP sudah diverifikasi.';
            }

            if (($submitCount['9'] ?? 0) < 2 || ($submitCount['5'] ?? 0) < 1) {
                return 'SPP belum disubmit ke PPK sesuai alur BP.';
            }

            if (! in_array('5', $status, true)) {
                return 'SPP belum TTE oleh PA.';
            }

            return null;
        }

        if ($document->payment_type === PaymentUP::PAYMENT_TYPE && $document->src_type === 'SPM') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);

            if ($jabatanId !== 4) {
                return null;
            }

            if (! in_array('7', $submit, true)) {
                return 'SPM belum disubmit oleh PPK.';
            }

            if (! in_array('5', $submit, true)) {
                return 'SPM belum disubmit ke verifikator.';
            }

            $sptjm = Document::query()
                ->where('payment_type', PaymentUP::PAYMENT_TYPE)
                ->where('src_type', 'SPTJM')
                ->where('reference_id', $document->reference_id)
                ->whereNull('deleted_at')
                ->first();

            $spPengajuan = Document::query()
                ->where('payment_type', PaymentUP::PAYMENT_TYPE)
                ->where('src_type', 'SP_PENGAJUAN')
                ->where('reference_id', $document->reference_id)
                ->whereNull('deleted_at')
                ->first();

            $sptjmStatus = $this->csvToArray($sptjm?->status);
            $spPengajuanStatus = $this->csvToArray($spPengajuan?->status);

            if (
                ! in_array('5', $status, true) ||
                ! $sptjm || ! in_array('5', $sptjmStatus, true) ||
                ! $spPengajuan || ! in_array('5', $spPengajuanStatus, true)
            ) {
                return 'Dokumen SPM, SPTJM, dan SP Pengajuan belum TTE lengkap oleh PA.';
            }

            if (! is_null($document->verify)) {
                return 'SPM sudah diverifikasi.';
            }

            $usedBySp2d = Document::query()
                ->where('payment_type', PaymentUP::PAYMENT_TYPE)
                ->where('src_type', 'SP2D')
                ->where('reference_id', $document->reference_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($usedBySp2d) {
                return 'SPM sudah digunakan pada SP2D aktif.';
            }

            return null;
        }

        if ($document->payment_type === PaymentKKPD::PAYMENT_TYPE && $document->src_type === 'SPP') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);
            $submitCount = array_count_values($submit);

            if ($jabatanId !== 7) {
                return null;
            }

            if (! is_null($document->verify)) {
                return 'SPP sudah diverifikasi.';
            }

            if (($submitCount['9'] ?? 0) < 2 || ($submitCount['5'] ?? 0) < 1) {
                return 'SPP belum disubmit ke PPK sesuai alur BP.';
            }

            if (! in_array('5', $status, true)) {
                return 'SPP belum TTE oleh PA.';
            }

            return null;
        }

        if ($document->payment_type === PaymentKKPD::PAYMENT_TYPE && $document->src_type === 'SPM') {
            $submit = $this->csvToArray($document->submit);
            $status = $this->csvToArray($document->status);

            if ($jabatanId !== 4) {
                return null;
            }

            if (! in_array('7', $submit, true)) {
                return 'SPM belum disubmit oleh PPK.';
            }

            if (! in_array('5', $submit, true)) {
                return 'SPM belum disubmit ke verifikator.';
            }

            $sptjm = Document::query()
                ->where('payment_type', PaymentKKPD::PAYMENT_TYPE)
                ->where('src_type', 'SPTJM')
                ->where('reference_id', $document->reference_id)
                ->whereNull('deleted_at')
                ->first();

            $spPengajuan = Document::query()
                ->where('payment_type', PaymentKKPD::PAYMENT_TYPE)
                ->where('src_type', 'SP_PENGAJUAN')
                ->where('reference_id', $document->reference_id)
                ->whereNull('deleted_at')
                ->first();

            $sptjmStatus = $this->csvToArray($sptjm?->status);
            $spPengajuanStatus = $this->csvToArray($spPengajuan?->status);

            if (
                ! in_array('5', $status, true) ||
                ! $sptjm || ! in_array('5', $sptjmStatus, true) ||
                ! $spPengajuan || ! in_array('5', $spPengajuanStatus, true)
            ) {
                return 'Dokumen SPM, SPTJM, dan SP Pengajuan belum TTE lengkap oleh PA.';
            }

            if (! is_null($document->verify)) {
                return 'SPM sudah diverifikasi.';
            }

            $usedBySp2d = Document::query()
                ->where('payment_type', PaymentKKPD::PAYMENT_TYPE)
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

    private function csvToArray(?string $csv): array
    {
        if (! $csv) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static fn ($v) => $v !== ''));
    }
}
