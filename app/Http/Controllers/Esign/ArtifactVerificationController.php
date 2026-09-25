<?php

namespace App\Http\Controllers\Esign;

use App\Actions\Esign\VerifyDocumentArtifact;
use App\Exceptions\Esign\EsignArtifactStorageException;
use App\Exceptions\Esign\EsignOperationException;
use App\Http\Controllers\Controller;
use App\Models\Esign\DocumentArtifact;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class ArtifactVerificationController extends Controller
{
    public function __invoke(
        DocumentArtifact $documentArtifact,
        VerifyDocumentArtifact $verifyDocumentArtifact,
    ): JsonResponse {
        Gate::authorize('verify', $documentArtifact);

        try {
            $verification = $verifyDocumentArtifact->handle($documentArtifact);
        } catch (EsignArtifactStorageException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Artifact dokumen tidak dapat diverifikasi karena integritas file bermasalah.',
                'error' => [
                    'code' => 'esign.artifact_integrity_unavailable',
                    'retryable' => false,
                ],
            ], Response::HTTP_CONFLICT);
        } catch (EsignOperationException $exception) {
            return response()->json([
                'message' => 'Layanan validasi tanda tangan elektronik sedang tidak tersedia.',
                'error' => [
                    'code' => $exception->errorCode->value,
                    'retryable' => $exception->retryable,
                ],
            ], $exception->retryable
                ? Response::HTTP_SERVICE_UNAVAILABLE
                : Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => [
                ...$verification,
                'preview_url' => route(
                    'esign.internal.artifacts.verification.preview',
                    $documentArtifact,
                ),
            ],
        ]);
    }
}
