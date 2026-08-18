<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\CurrentUserContext;
use App\Services\Realtime\OnlinePresence;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeOnlineUsersController extends Controller
{
    public function __construct(
        private CurrentUserContext $currentUserContext,
        private OnlinePresence $onlinePresence
    ) {}

    public function __invoke(Request $request): View
    {
        $this->authorizeMonitoring($request);

        return view('admin.realtime.online-users', [
            ...$this->currentUserContext->viewData($request),
            'presenceState' => $this->onlinePresence->dashboardState(),
        ]);
    }

    public function state(Request $request): JsonResponse
    {
        $this->authorizeMonitoring($request);

        return response()->json($this->onlinePresence->dashboardState());
    }

    private function authorizeMonitoring(Request $request): void
    {
        abort_unless($this->currentUserContext->isRealActivePositionAdminSuper($request), 403);
    }
}
