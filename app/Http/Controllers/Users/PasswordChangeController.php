<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PasswordChangeController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $passwordExpiresAt = $user->password_expires_at ?: ($user->password_changed_at
            ? $user->password_changed_at->copy()->addMonthsNoOverflow(3)
            : null);

        return view('users.change-password', compact('user', 'passwordExpiresAt'));
    }

    public function update(ChangePasswordRequest $request)
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->input('password'),
            'password_changed_at' => now(),
            'password_expires_at' => now()->addMonthsNoOverflow(3),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        Auth::logoutOtherDevices($request->input('password'));

        Log::channel('module_users')->info('User changed own password', [
            'user_id' => $user->id,
            'password_changed_at' => $user->password_changed_at?->toDateTimeString(),
            'password_expires_at' => $user->password_expires_at?->toDateTimeString(),
        ]);

        return redirect()
            ->intended(route('dashboard'))
            ->with('status', 'Password berhasil diperbarui.');
    }
}
