<?php

namespace App\Exceptions\Esign;

use RuntimeException;

final class EsignInvariantViolationException extends RuntimeException
{
    public function __construct(public readonly string $invariantCode)
    {
        parent::__construct("Invariant eSign dilanggar: {$invariantCode}.");
    }

    /** @return array{esign_invariant_code: string} */
    public function context(): array
    {
        return ['esign_invariant_code' => $this->invariantCode];
    }
}
