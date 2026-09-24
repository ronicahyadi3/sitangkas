<?php

namespace App\Http\Controllers\Esign;

use App\Actions\Esign\ResumeEsignAttempt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Esign\ResumeEsignAttemptRequest;
use App\Models\Esign\EsignAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class EsignAttemptResumeController extends Controller
{
    public function __invoke(
        ResumeEsignAttemptRequest $request,
        EsignAttempt $esignAttempt,
        ResumeEsignAttempt $resume,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, Response::HTTP_UNAUTHORIZED);
        Gate::authorize('resume', $esignAttempt);

        $attempt = $resume->handle($user, $esignAttempt, $request->passphrase());

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->public_id,
                'status' => $attempt->status->value,
                'status_url' => route('esign.internal.attempts.show', $attempt),
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
