<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\AdminSuperActingContextController;
use App\Http\Controllers\Auth\LoginContextController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\TotpEnrollmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Profile\MfaRecoveryCodeController;
use App\Http\Controllers\Profile\SecurityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('public.landing');
})->name('landing');

Route::get('/about', function () {
    return view('public.about');
})->name('about');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:auth-login')
        ->name('login.store');
});

Route::middleware(['auth', 'single.device.session'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)
        ->middleware(['mfa.verified', 'active.position'])
        ->name('dashboard');

    Route::get('/profile/security', SecurityController::class)
        ->middleware(['mfa.verified', 'active.position'])
        ->name('profile.security');
    Route::post('/profile/security/mfa/recovery-codes', [MfaRecoveryCodeController::class, 'store'])
        ->middleware(['mfa.verified', 'active.position', 'throttle:auth-mfa'])
        ->name('profile.security.mfa.recovery_codes.regenerate');

    Route::get('/login/context', [LoginContextController::class, 'create'])->name('login.context');
    Route::post('/login/context', [LoginContextController::class, 'store'])
        ->middleware('throttle:auth-context')
        ->name('login.context.store');

    Route::get('/login/mfa', [MfaChallengeController::class, 'create'])->name('login.mfa');
    Route::post('/login/mfa', [MfaChallengeController::class, 'store'])
        ->middleware('throttle:auth-mfa')
        ->name('login.mfa.store');

    Route::get('/login/mfa/setup', [TotpEnrollmentController::class, 'create'])->name('login.mfa.setup');
    Route::post('/login/mfa/setup', [TotpEnrollmentController::class, 'store'])
        ->middleware('throttle:auth-mfa-setup')
        ->name('login.mfa.setup.store');

    Route::get('/login/post', [AdminSuperActingContextController::class, 'create'])
        ->middleware('mfa.verified')
        ->name('login.post');
    Route::post('/login/post', [AdminSuperActingContextController::class, 'store'])
        ->middleware(['mfa.verified', 'throttle:auth-context'])
        ->name('login.post.store');
    Route::get('/login/post/options/instansi', [AdminSuperActingContextController::class, 'instansiOptions'])
        ->middleware(['mfa.verified', 'throttle:auth-context-options'])
        ->name('login.post.options.instansi');
    Route::get('/login/post/options/unit-kerja', [AdminSuperActingContextController::class, 'unitKerjaOptions'])
        ->middleware(['mfa.verified', 'throttle:auth-context-options'])
        ->name('login.post.options.unit_kerja');
    Route::get('/login/post/options/special-users', [AdminSuperActingContextController::class, 'specialUserPositionOptions'])
        ->middleware(['mfa.verified', 'throttle:auth-context-options'])
        ->name('login.post.options.special_users');
});
