<?php

declare(strict_types=1);

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Document\DocumentDetailContractBuilder;
use App\Services\Document\DocumentOrganizationScope;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class DetailTbp extends Controller
{
    public function __construct(
        private readonly PositionIdentityResolver $positionIdentityResolver,
        private readonly DocumentOrganizationScope $documentOrganizationScope,
        private readonly DocumentDetailContractBuilder $documentDetailContractBuilder,
    ) {}

    public function detail(Request $request, ActivePositionService $activePosition): JsonResponse
    {
        Log::channel('module_document_data')->info('Document Detail TBP Request', [
            'hash' => $request->id,
        ]);

        if (! $request->id) {
            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid',
            ], 400);
        }

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('module_document_data')->warning('Document Detail TBP Invalid ID', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 422,
                'message' => 'ID dokumen tidak valid',
            ], 422);
        }

        $position = $activePosition->get();
        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json([
                'status' => 401,
                'message' => 'Sesi pengguna tidak valid',
            ], 401);
        }

        if (! $position || ! $position->jabatan) {
            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid',
            ], 403);
        }

        $selectedYear = $activePosition->selectedYear();
        $document = Document::query()
            ->with('unitKerja')
            ->whereYear('created_at', $selectedYear)
            ->find($id);
        if (! $document) {
            return response()->json([
                'status' => 404,
                'message' => 'Dokumen tidak ditemukan',
            ], 404);
        }

        if (! $this->canAccessDocument($document, $position)) {
            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak berwenang mengakses data TBP ini',
            ], 403);
        }

        $lpjId = $this->resolveLpjId($document);
        if (! $lpjId) {
            return DataTables::of(collect())->make(true);
        }

        $tbpQuery = Document::query()
            ->with('pdfDeliveryArtifacts')
            ->where('document.src_type', 'TBP')
            ->where('document.payment_type', $document->payment_type)
            ->where('document.parent_id', $lpjId)
            ->whereYear('document.created_at', $selectedYear)
            ->whereNull('document.deleted_at')
            ->select('document.*')
            ->orderByDesc('document.created_at');

        return DataTables::of($tbpQuery)
            ->addIndexColumn()
            ->addColumn('document_contract', function (Document $row) use ($actor): array {
                return $this->documentDetailContractBuilder->build(
                    document: $row,
                    actor: $actor,
                )->toArray();
            })
            ->make(true);
    }

    private function resolveLpjId(Document $document): ?int
    {
        if ($document->payment_type === 'GU_SKPD') {
            return match ($document->src_type) {
                'LPJ' => (int) $document->id,
                'SPP', 'BMD' => $document->reference_id ? (int) $document->reference_id : null,
                'TBP' => $document->parent_id ? (int) $document->parent_id : null,
                default => null,
            };
        }

        if ($document->payment_type === 'GU_UK') {
            return match ($document->src_type) {
                'LPJ_BPP' => (int) $document->id,
                'TBP' => $document->parent_id ? (int) $document->parent_id : null,
                default => null,
            };
        }

        return null;
    }

    private function canAccessDocument(Document $document, UserPosition $position): bool
    {
        if (! $position->jabatan) {
            return false;
        }

        $jabatanId = (int) $position->jabatan->id;
        $jabatanCode = (string) $position->jabatan->kode;
        if ($jabatanCode === 'ADMIN_SUPER') {
            return true;
        }

        $unitKerjaId = $position->unitKerja?->id;
        if (! $unitKerjaId) {
            return false;
        }

        if ($document->payment_type === 'GU_UK') {
            if ($jabatanCode === 'PPTK' && $document->src_type === 'NPD') {
                return $this->positionIdentityResolver->contains(
                    $this->positionIdentityResolver->pptkActorPosition($position),
                    (int) $document->uploaded_by,
                );
            }

            return (int) $document->id_unit_kerja === (int) $unitKerjaId;
        }

        if ($this->documentOrganizationScope->containsUnit($position, $document->id_unit_kerja)) {
            return true;
        }

        $assigned = array_filter(explode(',', (string) $document->assigned_to));

        return in_array((string) $jabatanId, $assigned, true);
    }
}
