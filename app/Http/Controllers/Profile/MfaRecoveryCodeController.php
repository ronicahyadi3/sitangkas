<?php

namespace App\Http\Controllers\Profile;

use App\Actions\Auth\RegenerateMfaRecoveryCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\RegenerateMfaRecoveryCodesRequest;
use Illuminate\Http\RedirectResponse;

class MfaRecoveryCodeController extends Controller
{
    public function store(
        RegenerateMfaRecoveryCodesRequest $request,
        RegenerateMfaRecoveryCodes $regenerateMfaRecoveryCodes
    ): RedirectResponse {
        $result = $regenerateMfaRecoveryCodes->handle(
            $request,
            $request->authenticatedUser(),
            $request->realActiveUserPosition()
        );

        return redirect()
            ->route('profile.security')
            ->with('status', 'Recovery codes MFA berhasil dibuat ulang. Simpan codes baru yang ditampilkan.')
            ->with('mfa_recovery_codes_regenerated', [
                'codes' => $result['recovery_codes'],
                'regenerated_at' => $result['regenerated_at'],
                'recovery_code_count' => $result['recovery_code_count'],
                'previous_recovery_code_count' => $result['previous_recovery_code_count'],
            ]);
    }
}
