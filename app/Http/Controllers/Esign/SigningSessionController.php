<?php

namespace App\Http\Controllers\Esign;

use App\Actions\Esign\CreateSigningSession;
use App\Actions\Esign\ResolveSigningSession;
use App\Data\Esign\PreparedSigningRenditionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Esign\StoreSigningSessionRequest;
use App\Models\Esign\DocumentSigningStep;
use App\Models\User;
use App\Services\Esign\EphemeralPreparedRenditionStore;
use App\Services\Esign\EphemeralSigningSessionStore;
use App\Services\Esign\VisibleSigningEditorConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SigningSessionController extends Controller
{
    public function store(
        StoreSigningSessionRequest $request,
        CreateSigningSession $createSigningSession,
        VisibleSigningEditorConfiguration $editorConfiguration,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $step = DocumentSigningStep::query()
            ->where('public_id', $request->string('step_public_id')->toString())
            ->firstOrFail();
        $session = $createSigningSession->handle($user, $step);

        return response()->json([
            'data' => [
                ...$session->toClientArray(),
                'editor' => $editorConfiguration->forClient($session),
                'session_url' => route('esign.internal.signing-sessions.show', $session->sessionId),
                'preview_url' => route('esign.internal.signing-sessions.preview', $session->sessionId),
                'prepare_rendition_url' => route('esign.internal.signing-sessions.renditions.store', $session->sessionId),
                'sign_url' => route('esign.internal.signing-sessions.sign', $session->sessionId),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(
        Request $request,
        string $signingSession,
        ResolveSigningSession $resolveSigningSession,
        VisibleSigningEditorConfiguration $editorConfiguration,
        EphemeralPreparedRenditionStore $renditions,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $context = $resolveSigningSession->handle($user, $signingSession);
        $rendition = $renditions->getCurrent($signingSession, (int) $user->getKey());
        $preparedRendition = null;

        if ($rendition instanceof PreparedSigningRenditionData) {
            $preparedRendition = $rendition->toClientArray();
            $preparedRendition['signature_operations'] = array_map(
                fn (array $operation): array => [
                    ...$operation,
                    'qr_image_url' => route('esign.internal.signing-sessions.renditions.qr', [
                        'signingSession' => $signingSession,
                        'revision' => $rendition->revision,
                        'operationIndex' => $operation['operation_index'],
                    ]),
                ],
                $preparedRendition['signature_operations'],
            );
            $preparedRendition['preview_url'] = route('esign.internal.signing-sessions.renditions.preview', [
                'signingSession' => $signingSession,
                'revision' => $rendition->revision,
            ]);
        }

        return response()->json([
            'data' => [
                ...$context->session->toClientArray(),
                'editor' => $editorConfiguration->forClient($context->session),
                'session_url' => route('esign.internal.signing-sessions.show', $signingSession),
                'preview_url' => route('esign.internal.signing-sessions.preview', $signingSession),
                'prepare_rendition_url' => route('esign.internal.signing-sessions.renditions.store', $signingSession),
                'prepared_rendition' => $preparedRendition,
                'sign_url' => route('esign.internal.signing-sessions.sign', $signingSession),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        string $signingSession,
        EphemeralSigningSessionStore $sessions,
        EphemeralPreparedRenditionStore $renditions,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $renditions->forget($signingSession, (int) $user->getKey());

        if ($sessions->get($signingSession, (int) $user->getKey()) !== null) {
            $sessions->forget($signingSession);
        }

        return response()->noContent();
    }
}
