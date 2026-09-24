<?php

namespace App\Http\Controllers\Esign;

use App\Actions\Esign\ResolveSigningSession;
use App\Data\Esign\PreparedSigningRenditionData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Esign\EphemeralPreparedRenditionStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SigningSessionRenditionPreviewController extends Controller
{
    public function __invoke(
        Request $request,
        string $signingSession,
        string $revision,
        ResolveSigningSession $resolveSigningSession,
        EphemeralPreparedRenditionStore $renditions,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $context = $resolveSigningSession->handle($user, $signingSession);
        $rendition = $renditions->get($signingSession, $revision, (int) $user->getKey());

        if (! $rendition instanceof PreparedSigningRenditionData) {
            throw (new ModelNotFoundException)->setModel(PreparedSigningRenditionData::class, [$revision]);
        }

        if ($rendition->sourceArtifactId !== $context->session->sourceArtifactId
            || ! hash_equals($rendition->sourceArtifactSha256, $context->session->sourceArtifactSha256)) {
            $renditions->forget($signingSession, (int) $user->getKey());
            throw (new ModelNotFoundException)->setModel(PreparedSigningRenditionData::class, [$revision]);
        }

        return $renditions->inlineResponse($rendition);
    }
}
