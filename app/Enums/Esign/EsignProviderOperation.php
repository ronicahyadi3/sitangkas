<?php

namespace App\Enums\Esign;

enum EsignProviderOperation: string
{
    case CheckUserStatus = 'check_user_status';
    case Sign = 'sign';
    case Verify = 'verify';
}
