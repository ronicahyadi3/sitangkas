<?php

namespace App\Http\Controllers\Esign;

use App\Actions\Esign\PrepareSigningRendition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Esign\PrepareSigningRenditionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class SigningSessionRenditionController extends Controller
{
    public function __invoke(
        PrepareSigningRenditionRequest $request,
        string $signingSession,
        PrepareSigningRendition $prepareSigningRendition,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);

        $rendition = $prepareSigningRendition->handle(
            user: $user,
            sessionId: $signingSession,
            input: $request->signingPlan(),
        );
        $data = $rendition->toClientArray();
        $data['signature_operations'] = array_map(
            fn (array $operation): array => [
                ...$operation,
                'qr_image_url' => route('esign.internal.signing-sessions.renditions.qr', [
                    'signingSession' => $signingSession,
                    'revision' => $rendition->revision,
                    'operationIndex' => $operation['operation_index'],
                ]),
            ],
            $data['signature_operations'],
        );

        return response()->json([
            'data' => [
                ...$data,
                'preview_url' => route('esign.internal.signing-sessions.renditions.preview', [
                    'signingSession' => $signingSession,
                    'revision' => $rendition->revision,
                ]),
            ],
        ], Response::HTTP_CREATED);
    }
}
