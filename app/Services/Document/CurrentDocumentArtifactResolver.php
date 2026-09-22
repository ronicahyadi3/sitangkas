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
        $artifacts = $document->artifacts()
            ->where('is_current', true)
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
