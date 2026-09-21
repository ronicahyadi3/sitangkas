<?php

namespace App\Services\Esign;

use App\Data\Esign\SignerIdentityData;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Esign\DocumentSigningStep;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use Illuminate\Http\Request;

final class SignerIdentityResolver
{
    public function __construct(
        private EsignAuthorizationService $authorization,
        private CurrentUserContext $currentUserContext,
        private Request $request,
    ) {}

    public function resolve(User $user, DocumentSigningStep $step): SignerIdentityData
    {
        $this->authorization->placeSignature($user, $step)->authorize();

        $realPosition = $this->currentUserContext->realActivePosition($this->request);

        if (! $realPosition instanceof UserPosition
            || (int) $realPosition->user_id !== (int) $user->getKey()
            || (int) $step->assigned_user_position_id !== (int) $realPosition->getKey()) {
            throw new EsignInvariantViolationException('signer_real_position_mismatch');
        }

        $nik = preg_replace('/\D+/', '', (string) $user->nik);

        if (! is_string($nik) || preg_match('/\A\d{16}\z/', $nik) !== 1) {
            throw new EsignInvariantViolationException('signer_nik_invalid');
        }

        return new SignerIdentityData(
            userId: (int) $user->getKey(),
            userPositionId: (int) $realPosition->getKey(),
            name: (string) $user->nama,
            maskedNik: str_repeat('*', 12).substr($nik, -4),
            nik: $nik,
        );
    }
}
