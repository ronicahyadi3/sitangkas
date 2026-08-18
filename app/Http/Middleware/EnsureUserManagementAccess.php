<?php

namespace App\Http\Middleware;

use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserManagementAccess
{
    public function __construct(
        private ActivePositionService $activePositionService,
        private UserManagementAccessService $userManagementAccessService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $this->activePositionService->get();

        if ($this->userManagementAccessService->canAccessModule($actor)) {
            return $next($request);
        }

        $roleLabel = $actor?->jabatan?->nama ?? 'jabatan ini';
        $message = "Jabatan {$roleLabel} tidak memiliki akses ke Manajemen Pengguna.";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 403);
        }

        return redirect()
            ->route('dashboard')
            ->withErrors(['access' => $message]);
    }
}
