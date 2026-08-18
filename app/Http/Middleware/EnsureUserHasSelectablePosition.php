<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\CurrentUserContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasSelectablePosition
{
    public function __construct(private CurrentUserContext $currentUserContext) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->currentUserContext->user($request);

        if (! $user instanceof User) {
            return $this->redirectOrJson($request, 'login', 'Session pengguna tidak valid.', 401);
        }

        if (! $this->currentUserContext->hasSelectablePositions($user)) {
            $this->currentUserContext->forgetActivePosition($request);

            return $this->redirectOrJson(
                $request,
                'login.no_active_position',
                'Akun Anda belum memiliki posisi aktif.'
            );
        }

        return $next($request);
    }

    private function redirectOrJson(
        Request $request,
        string $routeName,
        string $message,
        int $status = 409
    ): RedirectResponse|JsonResponse {
        $redirectTo = $this->routeUrl($routeName);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'redirect_to' => $redirectTo,
            ], $status);
        }

        return redirect()->to($redirectTo)->with('status', $message);
    }

    private function routeUrl(string $routeName): string
    {
        if (Route::has($routeName)) {
            return route($routeName);
        }

        return Route::has('login') ? route('login') : url('/');
    }
}
