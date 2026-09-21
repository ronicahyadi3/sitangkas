<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureAccountIsAccessible;
use App\Http\Middleware\EnsureActiveUserPosition;
use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Middleware\EnsurePasswordIsFresh;
use App\Http\Middleware\EnsureSingleDeviceSession;
use App\Http\Middleware\EnsureUserHasSelectablePosition;
use App\Http\Middleware\EnsureUserManagementAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            AddSecurityHeaders::class,
        ]);

        $middleware->alias([
            'account.accessible' => EnsureAccountIsAccessible::class,
            'active.position' => EnsureActiveUserPosition::class,
            'has.position' => EnsureUserHasSelectablePosition::class,
            'mfa.verified' => EnsureMfaVerified::class,
            'password.fresh' => EnsurePasswordIsFresh::class,
            'single.device.session' => EnsureSingleDeviceSession::class,
            'user.management' => EnsureUserManagementAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'passphrase',
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*')
                || $request->is('esign/*')
                || $request->expectsJson(),
        );
    })->create();
