<?php

namespace App\Http\Controllers\Esign;

use App\Actions\Esign\CreateSigningSession;
use App\Actions\Esign\ResolveSigningSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Esign\StoreSigningSessionRequest;
use App\Models\Esign\DocumentSigningStep;
use App\Models\User;
use App\Services\Esign\EphemeralSigningSessionStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SigningSessionController extends Controller
{
    public function store(
        StoreSigningSessionRequest $request,
        CreateSigningSession $createSigningSession,
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
                'preview_url' => route('esign.internal.signing-sessions.preview', $session->sessionId),
                'sign_url' => route('esign.internal.signing-sessions.sign', $session->sessionId),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(
        Request $request,
        string $signingSession,
        ResolveSigningSession $resolveSigningSession,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $context = $resolveSigningSession->handle($user, $signingSession);

        return response()->json([
            'data' => [
                ...$context->session->toClientArray(),
                'preview_url' => route('esign.internal.signing-sessions.preview', $signingSession),
                'sign_url' => route('esign.internal.signing-sessions.sign', $signingSession),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        string $signingSession,
        EphemeralSigningSessionStore $sessions,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        if ($sessions->get($signingSession, (int) $user->getKey()) !== null) {
            $sessions->forget($signingSession);
        }

        return response()->noContent();
    }
}
