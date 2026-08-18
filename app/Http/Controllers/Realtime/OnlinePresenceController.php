<?php

namespace App\Http\Controllers\Realtime;

use App\Events\Realtime\UserPresenceChanged;
use App\Http\Controllers\Controller;
use App\Services\Realtime\OnlinePresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlinePresenceController extends Controller
{
    public function __construct(private OnlinePresence $onlinePresence) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visibility_state' => ['nullable', 'string', 'in:visible,hidden,prerender'],
            'idle_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'activity_state' => ['nullable', 'string', 'in:active,idle,away'],
        ]);

        $session = $this->onlinePresence->heartbeat($request, $validated);
        $payload = $this->onlinePresence->broadcastPayload($session);

        if ($payload !== []) {
            broadcast(new UserPresenceChanged($payload))->toOthers();
        }

        return response()->json([
            'presence' => $payload,
        ]);
    }

    public function leave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'in:closed,navigated,manual'],
        ]);

        $session = $this->onlinePresence->leave($request, $validated['reason'] ?? 'closed');
        $payload = $this->onlinePresence->broadcastPayload($session);

        if ($payload !== []) {
            broadcast(new UserPresenceChanged($payload))->toOthers();
        }

        return response()->json([
            'presence' => $payload,
        ]);
    }
}
