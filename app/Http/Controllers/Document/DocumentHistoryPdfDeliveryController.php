<?php

declare(strict_types=1);

namespace App\Http\Controllers\Document;

use App\Actions\Document\ServeDocumentHistoryPdf;
use App\Enums\Document\PdfDeliveryPurpose;
use App\Http\Controllers\Controller;
use App\Models\DocumentHistory;
use App\Models\User;
use App\Services\Document\PdfViewerContractBuilder;
use App\Support\EncryptedId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentHistoryPdfDeliveryController extends Controller
{
    public function viewer(
        Request $request,
        string $history,
        PdfViewerContractBuilder $contracts,
    ): JsonResponse {
        $model = DocumentHistory::query()
            ->with(['document' => static fn ($query) => $query->withTrashed()])
            ->findOrFail($this->decodeOpaqueHistoryId($history));

        return response()->json([
            'data' => $contracts->forHistory(
                $model,
                $this->authenticatedUser($request),
            ),
        ]);
    }

    public function content(
        Request $request,
        string $history,
        ServeDocumentHistoryPdf $servePdf,
    ): StreamedResponse {
        return $servePdf->handle(
            $this->authenticatedUser($request),
            $history,
            PdfDeliveryPurpose::View,
        );
    }

    public function download(
        Request $request,
        string $history,
        ServeDocumentHistoryPdf $servePdf,
    ): StreamedResponse {
        return $servePdf->handle(
            $this->authenticatedUser($request),
            $history,
            PdfDeliveryPurpose::Download,
        );
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function decodeOpaqueHistoryId(string $history): int
    {
        $token = trim($history);
        if ($token === '' || ctype_digit($token)) {
            abort(404);
        }

        $historyId = EncryptedId::tryDecode($token);
        if ($historyId === null || $historyId < 1) {
            abort(404);
        }

        return $historyId;
    }
}
