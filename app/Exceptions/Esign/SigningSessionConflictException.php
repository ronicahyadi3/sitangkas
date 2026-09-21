<?php

namespace App\Exceptions\Esign;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class SigningSessionConflictException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly string $conflictCode)
    {
        parent::__construct('Signing session sudah tidak berlaku. Silakan buka ulang proses TTE.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => ['code' => $this->conflictCode],
        ], 409);
    }
}
