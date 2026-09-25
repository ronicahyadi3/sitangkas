<?php

namespace App\Http\Controllers\Esign;

use App\Http\Controllers\Controller;
use App\Models\Esign\DocumentArtifact;
use App\Services\Esign\DocumentArtifactIntegrityService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArtifactVerificationPreviewController extends Controller
{
    public function __invoke(
        DocumentArtifact $documentArtifact,
        DocumentArtifactIntegrityService $integrity,
    ): StreamedResponse {
        Gate::authorize('preview', $documentArtifact);

        return $integrity->inlineResponse($documentArtifact);
    }
}
