<?php

declare(strict_types=1);

namespace App\Actions\Document;

use App\Contracts\Document\PdfDeliverySource;
use App\Enums\Document\PdfDeliveryPurpose;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\User;
use App\Services\Document\PdfDeliveryAuthorizationService;
use App\Services\Document\PdfDeliveryResponseFactory;
use App\Support\EncryptedId;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ServeResolvedPdf
{
    public function __construct(
        private readonly PdfDeliverySource $sourceResolver,
        private readonly PdfDeliveryAuthorizationService $authorization,
        private readonly PdfDeliveryResponseFactory $responses,
    ) {}

    public function handle(
        User $user,
        string $encryptedDocumentId,
        string $resourceKey,
        PdfDeliveryPurpose $purpose,
    ): StreamedResponse {
        if (! in_array($resourceKey, [
            PdfDeliverySource::RESOURCE_DOCUMENT,
            PdfDeliverySource::RESOURCE_BILLING,
            PdfDeliverySource::RESOURCE_SPJ_FUNCTIONAL,
        ], true)) {
            abort(404);
        }

        $documentId = $this->decodeOpaqueDocumentId($encryptedDocumentId);
        $document = Document::withTrashed()
            ->with('pdfDeliveryArtifacts')
            ->findOrFail($documentId);
        $source = $this->sourceResolver->resolve($document, $resourceKey);

        if ($source === null) {
            abort(404);
        }

        if ($source->documentId !== (int) $document->getKey()
            || $source->resourceKey !== $resourceKey) {
            throw new EsignInvariantViolationException('pdf_delivery_source_identity_mismatch');
        }

        $this->authorization->authorize($user, $source, $purpose)->authorize();

        Log::channel('module_document_data')->info('Document PDF delivery authorized', [
            'document_id' => (int) $document->getKey(),
            'purpose' => $purpose->value,
            'resource_key' => $resourceKey,
            'source_state' => $source->sourceState->value,
            'user_id' => (int) $user->getKey(),
        ]);

        return $purpose === PdfDeliveryPurpose::Download
            ? $this->responses->download($source)
            : $this->responses->inline($source);
    }

    private function decodeOpaqueDocumentId(string $encryptedDocumentId): int
    {
        $token = trim($encryptedDocumentId);

        if ($token === '' || ctype_digit($token)) {
            abort(404);
        }

        $documentId = EncryptedId::tryDecode($token);
        if ($documentId === null || $documentId < 1) {
            abort(404);
        }

        return $documentId;
    }
}
