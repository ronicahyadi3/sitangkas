<?php

declare(strict_types=1);

namespace App\Actions\Document;

use App\Enums\Document\PdfDeliveryMode;
use App\Enums\Document\PdfDeliveryPurpose;
use App\Models\DocumentHistory;
use App\Models\User;
use App\Services\Document\DocumentHistoryPdfDeliverySource;
use App\Services\Document\PdfDeliveryAuthorizationService;
use App\Services\Document\PdfDeliveryResponseFactory;
use App\Support\EncryptedId;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ServeDocumentHistoryPdf
{
    public function __construct(
        private readonly DocumentHistoryPdfDeliverySource $sourceResolver,
        private readonly PdfDeliveryAuthorizationService $authorization,
        private readonly PdfDeliveryResponseFactory $responses,
    ) {}

    public function handle(
        User $user,
        string $encryptedHistoryId,
        PdfDeliveryPurpose $purpose,
    ): StreamedResponse {
        $history = $this->history($encryptedHistoryId);
        $source = $this->sourceResolver->resolve($history);

        if ($source === null) {
            abort(404);
        }

        $this->authorization->authorize($user, $source, $purpose)->authorize();

        Log::channel('module_document_data')->info('Document history PDF delivery authorized', [
            'document_id' => (int) $source->documentId,
            'document_process_id' => (int) $history->getKey(),
            'delivery_mode' => PdfDeliveryMode::Original->value,
            'purpose' => $purpose->value,
            'source_state' => $source->sourceState->value,
            'user_id' => (int) $user->getKey(),
        ]);

        return $purpose === PdfDeliveryPurpose::Download
            ? $this->responses->download($source)
            : $this->responses->inline($source);
    }

    private function history(string $encryptedHistoryId): DocumentHistory
    {
        $token = trim($encryptedHistoryId);
        if ($token === '' || ctype_digit($token)) {
            abort(404);
        }

        $historyId = EncryptedId::tryDecode($token);
        if ($historyId === null || $historyId < 1) {
            abort(404);
        }

        return DocumentHistory::query()
            ->with(['document' => static fn ($query) => $query->withTrashed()])
            ->findOrFail($historyId);
    }
}
