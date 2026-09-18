<?php

namespace App\Enums\Esign;

enum EsignProviderOutcome: string
{
    case Success = 'success';
    case BusinessFailure = 'business_failure';
    case TechnicalFailure = 'technical_failure';
    case InvalidResponse = 'invalid_response';
    case Unknown = 'unknown';
}
