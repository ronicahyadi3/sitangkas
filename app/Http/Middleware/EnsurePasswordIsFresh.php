<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsFresh
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->requiresPasswordChange() || $this->isBypassedRoute($request)) {
            return $next($request);
        }

        return $this->redirectOrJson(
            $request,
            'Password Anda wajib diperbarui sebelum melanjutkan.'
        );
    }

    private function isBypassedRoute(Request $request): bool
    {
        return $request->routeIs(
            'password.change',
            'password.change.save',
            'logout',
            'login.*'
        );
    }

    private function redirectOrJson(Request $request, string $message): RedirectResponse|JsonResponse
    {
        $redirectTo = Route::has('password.change') ? route('password.change') : url('/');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'redirect_to' => $redirectTo,
            ], 409);
        }

        return redirect()->to($redirectTo)->with('password_change_required', $message);
    }
}
