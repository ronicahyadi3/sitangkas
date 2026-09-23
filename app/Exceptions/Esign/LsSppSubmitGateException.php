<?php

namespace App\Exceptions\Esign;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class LsSppSubmitGateException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
