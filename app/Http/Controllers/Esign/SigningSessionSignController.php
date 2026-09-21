<?php

namespace App\Http\Controllers\Esign;

use App\Actions\Esign\SignDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Esign\SignDocumentRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class SigningSessionSignController extends Controller
{
    public function __invoke(
        SignDocumentRequest $request,
        string $signingSession,
        SignDocument $signDocument,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $attempt = $signDocument->handle(
            user: $user,
            sessionId: $signingSession,
            idempotencyKey: (string) $request->validated('idempotency_key'),
            previewSha256: (string) $request->validated('preview_sha256'),
            passphrase: $request->passphrase(),
        );

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->public_id,
                'status' => $attempt->status->value,
                'status_url' => route('esign.internal.attempts.show', $attempt),
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
