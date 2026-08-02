<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request): array {
            return [
                Limit::perMinute(10)->by($this->loginThrottleKey($request)),
                Limit::perMinute(60)->by($this->ipThrottleKey($request, 'auth-login-ip')),
            ];
        });

        RateLimiter::for('auth-context', function (Request $request): Limit {
            return Limit::perMinute(30)->by($this->authenticatedThrottleKey($request, 'auth-context'));
        });

        RateLimiter::for('auth-context-options', function (Request $request): Limit {
            return Limit::perMinute(120)->by($this->authenticatedThrottleKey($request, 'auth-context-options'));
        });

        RateLimiter::for('auth-mfa', function (Request $request): Limit {
            return Limit::perMinute(10)->by($this->authenticatedThrottleKey($request, 'auth-mfa'));
        });

        RateLimiter::for('auth-mfa-setup', function (Request $request): Limit {
            return Limit::perMinute(10)->by($this->authenticatedThrottleKey($request, 'auth-mfa-setup'));
        });
    }

    private function loginThrottleKey(Request $request): string
    {
        $identifier = trim((string) $request->input('nik', ''));

        return 'auth-login:'.hash('sha256', $identifier.'|'.$request->ip());
    }

    private function ipThrottleKey(Request $request, string $prefix): string
    {
        return $prefix.':'.hash('sha256', (string) $request->ip());
    }

    private function authenticatedThrottleKey(Request $request, string $prefix): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        if ($userId !== null) {
            return $prefix.':user:'.$userId;
        }

        return $this->ipThrottleKey($request, $prefix.':ip');
    }
}
