<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUserPosition
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
            return redirect()->route('login');
        }

        if (! $this->currentUserContext->hasSessionContext($request)) {
            if (! $this->currentUserContext->hasSelectablePositions($user)) {
                return $this->redirectToNoActivePosition($request, 'Akun Anda belum memiliki posisi aktif.');
            }

            return $this->redirectToContextSelection($request, 'Silakan pilih konteks kerja sebelum membuka dashboard.');
        }

        $realActiveUserPosition = $this->currentUserContext->realActivePosition($request);

        if (! $realActiveUserPosition instanceof UserPosition) {
            $this->currentUserContext->forgetActivePosition($request);

            if (! $this->currentUserContext->hasSelectablePositions($user)) {
                return $this->redirectToNoActivePosition($request, 'Akun Anda belum memiliki posisi aktif.');
            }

            return $this->redirectToContextSelection($request, 'Konteks kerja sudah tidak aktif. Silakan pilih konteks kerja kembali.');
        }

        if (! $this->currentUserContext->isAdminSuperPosition($realActiveUserPosition)) {
            $this->currentUserContext->forgetAdminSuperActingContext($request);
            $this->currentUserContext->activePosition($request);

            return $next($request);
        }

        if ($this->isAdminSuperActingRoute($request)) {
            return $next($request);
        }

        if (! $this->currentUserContext->hasCompleteAdminSuperActingContext($request)) {
            return $this->redirectToAdminSuperActingContext($request, 'Silakan pilih acting context Admin Super sebelum membuka dashboard.');
        }

        $activeUserPosition = $this->currentUserContext->activePosition($request);

        if (! $activeUserPosition instanceof UserPosition || ! $this->currentUserContext->effectiveContextIsActing($request)) {
            return $this->redirectToAdminSuperActingContext($request, 'Acting context Admin Super sudah tidak valid. Silakan pilih kembali.');
        }

        return $next($request);
    }

    private function redirectToContextSelection(Request $request, string $message): RedirectResponse
    {
        if (Route::has('positions.index')) {
            return redirect()->route('positions.index')->with('status', $message);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'nik' => $message,
        ]);
    }

    private function redirectToAdminSuperActingContext(Request $request, string $message): RedirectResponse
    {
        if (Route::has('login.post')) {
            return redirect()->route('login.post')->with('status', $message);
        }

        return $this->redirectToContextSelection($request, $message);
    }

    private function redirectToNoActivePosition(Request $request, string $message): RedirectResponse
    {
        if (Route::has('login.no_active_position')) {
            return redirect()->route('login.no_active_position')->with('status', $message);
        }

        return $this->redirectToContextSelection($request, $message);
    }

    private function isAdminSuperActingRoute(Request $request): bool
    {
        return $request->routeIs(
            'login.post',
            'login.post.store',
            'login.post.options.*',
            'positions.index',
            'positions.store',
            'login.no_active_position',
            'logout'
        );
    }
}
