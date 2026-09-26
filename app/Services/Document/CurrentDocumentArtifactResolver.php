<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\Esign\DocumentArtifactType;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\Esign\DocumentArtifact;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class CurrentDocumentArtifactResolver
{
    public function resolve(Document $document): DocumentArtifact
    {
        $artifacts = $document->relationLoaded('pdfDeliveryArtifacts')
            ? $document->pdfDeliveryArtifacts
                ->filter(static fn (DocumentArtifact $artifact): bool => $artifact->is_current
                    && in_array($artifact->artifact_type, [
                        DocumentArtifactType::BeforeSign,
                        DocumentArtifactType::AfterSign,
                    ], true))
                ->sort(static fn (DocumentArtifact $left, DocumentArtifact $right): int => [
                    (int) $right->version,
                    (int) $right->getKey(),
                ] <=> [
                    (int) $left->version,
                    (int) $left->getKey(),
                ])
                ->take(2)
                ->values()
            : $document->artifacts()
                ->where('is_current', true)
                ->whereIn('artifact_type', [
                    DocumentArtifactType::BeforeSign->value,
                    DocumentArtifactType::AfterSign->value,
                ])
                ->orderByDesc('version')
                ->limit(2)
                ->get();

        if ($artifacts->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(
                DocumentArtifact::class,
                [$document->getKey()],
            );
        }

        if ($artifacts->count() !== 1) {
            throw new EsignInvariantViolationException('document_current_artifact_ambiguous');
        }

        /** @var DocumentArtifact $artifact */
        $artifact = $artifacts->first();

        if ((int) $artifact->document_id !== (int) $document->getKey()) {
            throw new EsignInvariantViolationException('document_current_artifact_mismatch');
        }

        if (! in_array($artifact->artifact_type, [
            DocumentArtifactType::BeforeSign,
            DocumentArtifactType::AfterSign,
        ], true)) {
            throw new EsignInvariantViolationException('document_current_artifact_not_deliverable');
        }

        return $artifact;
    }
}
