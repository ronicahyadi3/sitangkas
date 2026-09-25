<?php

declare(strict_types=1);

namespace App\Http\Controllers\Document;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Document\CurrentDocumentArtifactResolver;
use App\Services\Esign\DocumentArtifactIntegrityService;
use App\Support\EncryptedId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class LsSpjDocumentDeliveryController extends Controller
{
    public function content(
        string $document,
        CurrentDocumentArtifactResolver $artifactResolver,
        DocumentArtifactIntegrityService $artifactIntegrity,
    ): StreamedResponse {
        $artifact = $artifactResolver->resolve($this->resolveLsSpj($document));

        Gate::authorize('view', $artifact);

        return $artifactIntegrity->inlineResponse($artifact);
    }

    public function download(
        string $document,
        CurrentDocumentArtifactResolver $artifactResolver,
        DocumentArtifactIntegrityService $artifactIntegrity,
    ): StreamedResponse {
        $artifact = $artifactResolver->resolve($this->resolveLsSpj($document));

        Gate::authorize('download', $artifact);

        return $artifactIntegrity->downloadResponse($artifact);
    }

    private function resolveLsSpj(string $encryptedDocumentId): Document
    {
        try {
            $documentId = EncryptedId::decode($encryptedDocumentId);
        } catch (Throwable) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return Document::query()
            ->whereKey($documentId)
            ->forPaymentType('LS')
            ->ofType(Document::TYPE_SPJ)
            ->whereNotNull('reference_id')
            ->whereHas('referenceDocument', static function (Builder $query): void {
                $query->forPaymentType('LS')->ofType(Document::TYPE_SPP);
            })
            ->firstOrFail();
    }
}
