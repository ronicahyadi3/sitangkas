<?php

namespace App\Http\Controllers\Esign;

use App\Enums\Esign\EsignAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Esign\EsignAttempt;
use App\Models\Esign\EsignSignatureOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class EsignAttemptController extends Controller
{
    public function __invoke(EsignAttempt $esignAttempt): JsonResponse
    {
        Gate::authorize('view', $esignAttempt);
        $esignAttempt->loadMissing([
            'resultArtifact:id,public_id',
            'signatureOperations:id,esign_attempt_id,operation_index,status,retryable',
        ]);
        $isProcessing = in_array($esignAttempt->status, [
            EsignAttemptStatus::Prepared,
            EsignAttemptStatus::Signing,
            EsignAttemptStatus::Validating,
        ], true);
        $canResume = $esignAttempt->status->requiresUserAction()
            && $esignAttempt->retryable
            && Gate::allows('resume', $esignAttempt);

        return response()->json([
            'data' => [
                'attempt_id' => $esignAttempt->public_id,
                'attempt_number' => $esignAttempt->attempt_number,
                'status' => $esignAttempt->status->value,
                'retryable' => $esignAttempt->retryable,
                'progress' => [
                    'planned' => $esignAttempt->planned_signature_count,
                    'completed' => $esignAttempt->completed_signature_count,
                    'current_index' => $esignAttempt->current_signature_index,
                    'operations' => $esignAttempt->signatureOperations->map(
                        static fn (EsignSignatureOperation $operation): array => [
                            'index' => $operation->operation_index,
                            'status' => $operation->status->value,
                            'retryable' => $operation->retryable,
                        ],
                    )->values()->all(),
                ],
                'requires_passphrase' => $canResume,
                'resume_url' => $canResume
                    ? route('esign.internal.attempts.resume', $esignAttempt)
                    : null,
                'requires_reconciliation' => $esignAttempt->status === EsignAttemptStatus::Unknown,
                'error_code' => $esignAttempt->application_error_code,
                'result_artifact_id' => $esignAttempt->resultArtifact?->public_id,
                'started_at' => $esignAttempt->started_at?->toIso8601String(),
                'completed_at' => $esignAttempt->completed_at?->toIso8601String(),
                'next_poll_after_ms' => $isProcessing ? 2000 : null,
            ],
        ]);
    }
}
