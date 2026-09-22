<?php

declare(strict_types=1);

namespace App\Http\Controllers\Document;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Document\CurrentDocumentArtifactResolver;
use App\Services\Esign\DocumentArtifactIntegrityService;
use App\Support\EncryptedId;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class LsSppDocumentDeliveryController extends Controller
{
    public function content(
        string $document,
        CurrentDocumentArtifactResolver $artifactResolver,
        DocumentArtifactIntegrityService $artifactIntegrity,
    ): StreamedResponse {
        $artifact = $artifactResolver->resolve($this->resolveLsSpp($document));

        Gate::authorize('view', $artifact);

        return $artifactIntegrity->inlineResponse($artifact);
    }

    public function download(
        string $document,
        CurrentDocumentArtifactResolver $artifactResolver,
        DocumentArtifactIntegrityService $artifactIntegrity,
    ): StreamedResponse {
        $artifact = $artifactResolver->resolve($this->resolveLsSpp($document));

        Gate::authorize('download', $artifact);

        return $artifactIntegrity->downloadResponse($artifact);
    }

    private function resolveLsSpp(string $encryptedDocumentId): Document
    {
        try {
            $documentId = EncryptedId::decode($encryptedDocumentId);
        } catch (Throwable) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return Document::query()
            ->whereKey($documentId)
            ->where('payment_type', 'LS')
            ->where('src_type', Document::TYPE_SPP)
            ->firstOrFail();
    }
}
