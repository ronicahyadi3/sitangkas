<?php

namespace App\Contracts\Esign;

use App\Data\Esign\SignRequestData;
use App\Data\Esign\SignResultData;
use App\Data\Esign\UserStatusData;
use App\Data\Esign\UserStatusRequestData;
use App\Data\Esign\VerificationResultData;
use App\Data\Esign\VerifyPdfData;

interface EsignGateway
{
    public function sign(SignRequestData $request): SignResultData;

    public function verify(VerifyPdfData $request): VerificationResultData;

    public function checkUserStatus(UserStatusRequestData $request): UserStatusData;
}
