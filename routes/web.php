<?php

use App\Http\Controllers\Admin\RealtimeOnlineUsersController;
use App\Http\Controllers\Auth\AdminSuperActingContextController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MissingActivePositionController;
use App\Http\Controllers\Auth\PositionContextController;
use App\Http\Controllers\Auth\TotpEnrollmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Data\Delete as DocumentDeleteController;
use App\Http\Controllers\Data\Denied as DocumentDeniedController;
use App\Http\Controllers\Data\Detail as DocumentDetailController;
use App\Http\Controllers\Data\DetailTbp as DocumentDetailTbpController;
use App\Http\Controllers\Data\History as DocumentHistoryController;
use App\Http\Controllers\Data\Rekening as DocumentRekeningController;
use App\Http\Controllers\Data\Verify as DocumentVerifyController;
use App\Http\Controllers\Esign\EsignAttemptController;
use App\Http\Controllers\Esign\SigningSessionController;
use App\Http\Controllers\Esign\SigningSessionPreviewController;
use App\Http\Controllers\Esign\SigningSessionSignController;
use App\Http\Controllers\Payment\LS\SP2D as LsSp2dController;
use App\Http\Controllers\Payment\LS\SPM as LsSpmController;
use App\Http\Controllers\Payment\LS\SPP as LsSppController;
use App\Http\Controllers\Profile\MfaRecoveryCodeController;
use App\Http\Controllers\Profile\SecurityController;
use App\Http\Controllers\Realtime\OnlinePresenceController;
use App\Http\Controllers\Users\AjaxOptionsController;
use App\Http\Controllers\Users\PasswordChangeController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\UserManagementAuditController;
use App\Http\Controllers\Users\UserPositionController;
use App\Http\Controllers\Users\UserSecurityController;
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

Route::middleware(['auth', 'account.accessible', 'single.device.session'])->group(function (): void {
    Route::get('/ping', function () {
        return response()->json(null, 204);
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/login/no-active-position', MissingActivePositionController::class)
        ->name('login.no_active_position');

    Route::get('/dashboard', DashboardController::class)
        ->middleware(['has.position', 'mfa.verified', 'active.position', 'password.fresh'])
        ->name('dashboard');

    Route::get('/positions', [PositionContextController::class, 'create'])
        ->middleware('has.position')
        ->name('positions.index');
    Route::post('/positions', [PositionContextController::class, 'store'])
        ->middleware(['has.position', 'throttle:auth-context'])
        ->name('positions.store');

    Route::get('/profile/security', SecurityController::class)
        ->middleware(['has.position', 'mfa.verified', 'active.position', 'password.fresh'])
        ->name('profile.security');
    Route::post('/profile/security/mfa/recovery-codes', [MfaRecoveryCodeController::class, 'store'])
        ->middleware(['has.position', 'mfa.verified', 'active.position', 'password.fresh', 'throttle:auth-mfa'])
        ->name('profile.security.mfa.recovery_codes.regenerate');

    Route::post('/realtime/presence/heartbeat', [OnlinePresenceController::class, 'heartbeat'])
        ->middleware(['has.position', 'mfa.verified', 'active.position', 'password.fresh', 'throttle:120,1'])
        ->name('realtime.presence.heartbeat');
    Route::post('/realtime/presence/leave', [OnlinePresenceController::class, 'leave'])
        ->middleware(['has.position', 'mfa.verified', 'active.position', 'password.fresh', 'throttle:120,1'])
        ->name('realtime.presence.leave');

    Route::get('/admin/realtime/online-users', RealtimeOnlineUsersController::class)
        ->middleware(['has.position', 'mfa.verified', 'active.position', 'password.fresh'])
        ->name('admin.realtime.online-users.index');
    Route::get('/admin/realtime/online-users/state', [RealtimeOnlineUsersController::class, 'state'])
        ->middleware(['has.position', 'mfa.verified', 'active.position', 'password.fresh', 'throttle:60,1'])
        ->name('admin.realtime.online-users.state');

    Route::get('/login/context', [PositionContextController::class, 'legacy'])
        ->name('login.context.legacy');

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

    Route::middleware(['has.position', 'mfa.verified', 'active.position', 'password.fresh'])->group(function (): void {
        Route::get('/users/post/change-password', [PasswordChangeController::class, 'edit'])
            ->name('password.change');
        Route::post('/users/post/change-password', [PasswordChangeController::class, 'update'])
            ->middleware('throttle:auth-context')
            ->name('password.change.save');

        Route::get('/ajax/options/instansi', [AjaxOptionsController::class, 'instansi'])
            ->middleware('throttle:auth-context-options')
            ->name('ajax.options.instansi');
        Route::get('/ajax/options/unit-kerja', [AjaxOptionsController::class, 'unitKerja'])
            ->middleware('throttle:auth-context-options')
            ->name('ajax.options.unitkerja');

        Route::prefix('esign/internal')->name('esign.internal.')->group(function (): void {
            Route::post('/signing-sessions', [SigningSessionController::class, 'store'])
                ->middleware('throttle:esign-prepare')
                ->name('signing-sessions.store');
            Route::get('/signing-sessions/{signingSession}', [SigningSessionController::class, 'show'])
                ->whereUuid('signingSession')
                ->middleware('throttle:esign-status')
                ->name('signing-sessions.show');
            Route::get('/signing-sessions/{signingSession}/preview', SigningSessionPreviewController::class)
                ->whereUuid('signingSession')
                ->middleware('throttle:esign-preview')
                ->name('signing-sessions.preview');
            Route::post('/signing-sessions/{signingSession}/sign', SigningSessionSignController::class)
                ->whereUuid('signingSession')
                ->middleware('throttle:esign-sign')
                ->name('signing-sessions.sign');
            Route::delete('/signing-sessions/{signingSession}', [SigningSessionController::class, 'destroy'])
                ->whereUuid('signingSession')
                ->middleware('throttle:esign-prepare')
                ->name('signing-sessions.destroy');
            Route::get('/attempts/{esignAttempt}', EsignAttemptController::class)
                ->middleware('throttle:esign-status')
                ->name('attempts.show');
        });

        Route::prefix('document')->name('document.')->group(function (): void {
            Route::get('/detail', [DocumentDetailController::class, 'detail'])->name('detail');
            Route::get('/detail-tbp', [DocumentDetailTbpController::class, 'detail'])->name('detail_tbp');
            Route::post('/history', [DocumentHistoryController::class, 'history'])->name('history');
            Route::post('/delete', [DocumentDeleteController::class, 'delete'])->name('delete');
            Route::post('/denied', [DocumentDeniedController::class, 'denied'])->name('denied');
            Route::post('/verify', [DocumentVerifyController::class, 'verify'])->name('verify');
            Route::post('/sub-kegiatan', [DocumentRekeningController::class, 'subKegiatan'])->name('sub_kegiatan');
            Route::post('/rekening', [DocumentRekeningController::class, 'rekening'])->name('rekening');
            Route::get('/rekening/detail', [DocumentRekeningController::class, 'rekeningDetail'])->name('rekening.detail');
        });

        Route::get('/payment/options/pptk', [UserController::class, 'pptk'])
            ->middleware('throttle:auth-context-options')
            ->name('payment.options.pptk');

        Route::prefix('ls')->name('ls.')->group(function (): void {
            Route::get('/spp', [LsSppController::class, 'index'])->name('spp.index');
            Route::get('/spp/json', [LsSppController::class, 'json'])->name('spp.json');
            Route::post('/spp', [LsSppController::class, 'store'])->name('spp.store');
            Route::get('/spp/{id}/edit', [LsSppController::class, 'edit'])->name('spp.edit');
            Route::post('/spp/update/{id}', [LsSppController::class, 'update'])->name('spp.update');
            Route::post('/spp/submit', [LsSppController::class, 'submit'])->name('spp.submit');
            Route::post('/spp/submit/pptk', [LsSppController::class, 'submit_pptk'])->name('spp.submit.pptk');

            Route::get('/spm', [LsSpmController::class, 'index'])->name('spm.index');
            Route::get('/spm/json', [LsSpmController::class, 'json'])->name('spm.json');
            Route::get('/spm/spp/json', [LsSpmController::class, 'formJson'])->name('spm.spp.json');
            Route::post('/spm', [LsSpmController::class, 'store'])->name('spm.store');
            Route::get('/spm/{id}/edit', [LsSpmController::class, 'edit'])->name('spm.edit');
            Route::post('/spm/update/{id}', [LsSpmController::class, 'update'])->name('spm.update');
            Route::post('/spm/submit', [LsSpmController::class, 'submit'])->name('spm.submit');

            Route::get('/sp2d', [LsSp2dController::class, 'index'])->name('sp2d.index');
            Route::get('/sp2d/json', [LsSp2dController::class, 'json'])->name('sp2d.json');
            Route::get('/sp2d/spm/json', [LsSp2dController::class, 'formJson'])->name('sp2d.spp.json');
            Route::post('/sp2d', [LsSp2dController::class, 'store'])->name('sp2d.store');
            Route::get('/sp2d/{id}/edit', [LsSp2dController::class, 'edit'])->name('sp2d.edit');
            Route::post('/sp2d/update/{id}', [LsSp2dController::class, 'update'])->name('sp2d.update');
            Route::post('/sp2d/submit', [LsSp2dController::class, 'submit'])->name('sp2d.submit');
        });

        Route::prefix('users')->name('users.')->middleware('user.management')->scopeBindings()->group(function (): void {
            Route::get('/datatable', [UserController::class, 'datatable'])
                ->middleware('throttle:auth-context-options')
                ->name('datatable');
            Route::get('/pptk', [UserController::class, 'pptk'])
                ->middleware('throttle:auth-context-options')
                ->name('pptk');
            Route::get('/bud', [UserController::class, 'bud'])
                ->middleware('throttle:auth-context-options')
                ->name('bud');
            Route::get('/audit-trail', [UserManagementAuditController::class, 'index'])
                ->name('audit-trail');

            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::put('/{user}', [UserController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
            Route::get('/{user}/security', [UserSecurityController::class, 'show'])
                ->middleware('throttle:auth-context-options')
                ->name('security.show');
            Route::post('/{user}/security/force-password-change', [UserSecurityController::class, 'forcePasswordChange'])
                ->middleware('throttle:auth-context')
                ->name('security.force-password-change');
            Route::post('/{user}/security/reset-password', [UserSecurityController::class, 'resetPassword'])
                ->middleware('throttle:auth-context')
                ->name('security.reset-password');
            Route::post('/{user}/security/lock', [UserSecurityController::class, 'lock'])
                ->middleware('throttle:auth-context')
                ->name('security.lock');
            Route::post('/{user}/security/unlock', [UserSecurityController::class, 'unlock'])
                ->middleware('throttle:auth-context')
                ->name('security.unlock');
            Route::post('/{user}/security/reset-mfa', [UserSecurityController::class, 'resetMfa'])
                ->middleware('throttle:auth-context')
                ->name('security.reset-mfa');

            Route::get('/{user}/positions', [UserPositionController::class, 'index'])
                ->name('positions.index');
            Route::get('/{user}/positions/{position}', [UserPositionController::class, 'show'])
                ->name('positions.show');
            Route::post('/{user}/positions', [UserPositionController::class, 'store'])
                ->name('positions.store');
            Route::put('/{user}/positions/{position}', [UserPositionController::class, 'update'])
                ->name('positions.update');
            Route::post('/{user}/positions/{position}/activate', [UserPositionController::class, 'activate'])
                ->middleware('throttle:auth-context')
                ->name('positions.activate');
            Route::post('/{user}/positions/{position}/deactivate', [UserPositionController::class, 'deactivate'])
                ->middleware('throttle:auth-context')
                ->name('positions.deactivate');
            Route::delete('/{user}/positions/{position}', [UserPositionController::class, 'destroy'])
                ->name('positions.destroy');
            Route::post('/{user}/positions/{position}/historical-year-access/grant', [UserPositionController::class, 'grantHistoricalWriteAccess'])
                ->middleware('throttle:auth-context')
                ->name('positions.year-access.grant');
            Route::post('/{user}/positions/{position}/historical-year-access/revoke', [UserPositionController::class, 'revokeHistoricalWriteAccess'])
                ->middleware('throttle:auth-context')
                ->name('positions.year-access.revoke');
        });
    });
});
