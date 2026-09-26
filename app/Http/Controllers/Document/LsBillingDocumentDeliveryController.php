<?php

declare(strict_types=1);

namespace App\Http\Controllers\Document;

use App\Enums\Esign\DocumentArtifactType;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Esign\DocumentArtifact;
use App\Services\Esign\DocumentArtifactIntegrityService;
use App\Support\EncryptedId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class LsBillingDocumentDeliveryController extends Controller
{
    public function content(
        string $document,
        DocumentArtifactIntegrityService $artifactIntegrity,
    ): StreamedResponse {
        $artifact = $this->resolveBillingArtifact($document);

        Gate::authorize('view', $artifact);

        return $artifactIntegrity->inlineResponse($artifact);
    }

    public function download(
        string $document,
        DocumentArtifactIntegrityService $artifactIntegrity,
    ): StreamedResponse {
        $artifact = $this->resolveBillingArtifact($document);

        Gate::authorize('download', $artifact);

        return $artifactIntegrity->downloadResponse($artifact);
    }

    private function resolveBillingArtifact(string $encryptedDocumentId): DocumentArtifact
    {
        try {
            $documentId = EncryptedId::decode($encryptedDocumentId);
        } catch (Throwable) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $spj = Document::query()
            ->whereKey($documentId)
            ->forPaymentType('LS')
            ->ofType(Document::TYPE_SPJ)
            ->whereNotNull('reference_id')
            ->whereNotNull('billing')
            ->whereHas('referenceDocument', static function (Builder $query): void {
                $query->forPaymentType('LS')->ofType(Document::TYPE_SPP);
            })
            ->firstOrFail();

        return $spj->artifacts()
            ->where('artifact_type', DocumentArtifactType::Attachment->value)
            ->where('source_reference_type', DocumentArtifact::SOURCE_REFERENCE_DOCUMENT_ATTACHMENT)
            ->where('source_reference_id', DocumentArtifact::ATTACHMENT_BILLING)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->firstOrFail();
    }
}
