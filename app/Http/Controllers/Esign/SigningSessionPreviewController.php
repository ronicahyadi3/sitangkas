<?php

namespace App\Http\Controllers\Esign;

use App\Actions\Esign\ResolveSigningSession;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Esign\DocumentArtifactIntegrityService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SigningSessionPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        string $signingSession,
        ResolveSigningSession $resolveSigningSession,
        DocumentArtifactIntegrityService $artifactIntegrity,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $context = $resolveSigningSession->handle($user, $signingSession);

        return $artifactIntegrity->inlineResponse($context->artifact);
    }
}
