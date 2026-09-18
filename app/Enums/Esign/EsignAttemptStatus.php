<?php

namespace App\Enums\Esign;

enum EsignAttemptStatus: string
{
    case Prepared = 'prepared';
    case Signing = 'signing';
    case Validating = 'validating';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
