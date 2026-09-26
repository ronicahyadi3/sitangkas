<?php

declare(strict_types=1);

namespace App\Http\Controllers\Document;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Services\Document\PdfViewerContractBuilder;
use App\Support\EncryptedId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PdfViewerContractController extends Controller
{
    public function __invoke(
        Request $request,
        string $document,
        string $resource,
        PdfViewerContractBuilder $contracts,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $documentId = EncryptedId::tryDecode(trim($document));
        abort_if($documentId === null || $documentId < 1 || ctype_digit($document), 404);

        $model = Document::withTrashed()
            ->with('pdfDeliveryArtifacts')
            ->findOrFail($documentId);

        return response()->json([
            'data' => $contracts->forDocument($model, $actor, $resource),
        ]);
    }
}
