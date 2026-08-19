<?php

namespace App\Http\Middleware;

use App\Models\UserManagementAuditEvent;
use App\Services\User\ActivePositionService;
use App\Services\User\UserManagementAccessService;
use App\Services\User\UserManagementAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserManagementAccess
{
    public function __construct(
        private ActivePositionService $activePositionService,
        private UserManagementAccessService $userManagementAccessService,
        private UserManagementAuditLogger $userManagementAuditLogger
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $this->activePositionService->managementActor();

        if ($this->userManagementAccessService->canAccessModule($actor)) {
            return $next($request);
        }

        $roleLabel = $actor?->jabatan?->nama ?? 'jabatan ini';
        $message = "Jabatan {$roleLabel} tidak memiliki akses ke Manajemen Pengguna.";

        $this->userManagementAuditLogger->blocked(UserManagementAuditEvent::EVENT_MODULE_ACCESS, [
            'actor_user' => $request->user(),
            'actor_position' => $actor,
            'reason_code' => 'module_access_denied',
            'reason' => $message,
            'message' => 'Akses Management Users ditolak.',
            'resource_type' => 'management_users',
            'resource_id' => $request->route()?->getName(),
            'metadata' => [
                'actor_position_id' => $actor?->getKey(),
                'actor_jabatan_id' => $actor?->jabatan_id,
                'actor_instansi_id' => $actor?->instansi_id,
                'actor_unit_kerja_id' => $actor?->unit_kerja_id,
            ],
        ], $request);

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
