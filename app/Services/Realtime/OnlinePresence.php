<?php

namespace App\Services\Realtime;

use App\Actions\Auth\RecordAuthenticationEvent;
use App\Models\Realtime\UserPresenceEvent;
use App\Models\Realtime\UserPresenceSession;
use App\Models\User;
use App\Services\Auth\CurrentUserContext;
use App\Services\Auth\RequestNetworkContext;
use App\Services\Auth\UserAgentContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OnlinePresence
{
    private const LOCK_KEY = 'realtime:online-presence:sessions:lock';

    private const HEARTBEAT_TIMEOUT_SECONDS = 120;

    private const IDLE_THRESHOLD_SECONDS = 300;

    private const OFFLINE_RETENTION_SECONDS = 600;

    private const AUDIT_RETENTION_DAYS = 30;

    public function __construct(
        private CurrentUserContext $currentUserContext,
        private RecordAuthenticationEvent $recordAuthenticationEvent,
        private RequestNetworkContext $requestNetworkContext,
        private UserAgentContext $userAgentContext
    ) {}

    /**
     * @param  array{visibility_state?: string|null, idle_seconds?: int|null, activity_state?: string|null}  $signals
     * @return array<string, mixed>|null
     */
    public function heartbeat(Request $request, array $signals): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $sessionIdHash = $this->recordAuthenticationEvent->sessionIdHash($request);

        if ($sessionIdHash === null) {
            return null;
        }

        $now = now();
        $networkContext = $this->requestNetworkContext->fromRequest($request);
        $userAgentContext = $this->userAgentContext->fromRequest($request);
        $positionContext = $this->currentUserContext->snapshot($request);
        $visibilityState = $this->visibilityState($signals['visibility_state'] ?? null);
        $idleSeconds = max(0, (int) ($signals['idle_seconds'] ?? 0));
        $status = $this->statusForSignals($visibilityState, $idleSeconds);
        $lastActivityAt = $now->copy()->subSeconds($idleSeconds);

        $sessionAttributes = [
            'presence_id' => $this->presenceId($sessionIdHash),
            'session_id_hash' => $sessionIdHash,
            'user_id' => $user->getKey(),
            'active_user_position_id' => $this->integerOrNull($positionContext['user_position_id'] ?? null),
            'real_user_position_id' => $this->integerOrNull($positionContext['real_user_position_id'] ?? null),
            'user_name' => $user->nama,
            'account_type' => $user->account_type,
            'account_status' => $user->status,
            'connection_count' => 1,
            'status' => $status,
            'visibility_state' => $visibilityState,
            'activity_state' => $status === UserPresenceSession::STATUS_ACTIVE ? UserPresenceSession::STATUS_ACTIVE : $status,
            'ip_address' => $networkContext['ip_address'],
            'proxy_ip_address' => $networkContext['proxy_ip_address'],
            'user_agent' => $request->userAgent(),
            'device_type' => $userAgentContext['device_type'],
            'device_name' => $userAgentContext['device_name'],
            'browser_name' => $userAgentContext['browser_name'],
            'browser_version' => $userAgentContext['browser_version'],
            'platform_name' => $userAgentContext['platform_name'],
            'platform_version' => $userAgentContext['platform_version'],
            'position_snapshot' => $positionContext,
            'last_seen_at' => $now,
            'last_activity_at' => $lastActivityAt,
            'heartbeat_expires_at' => $now->copy()->addSeconds(self::HEARTBEAT_TIMEOUT_SECONDS),
            'disconnected_at' => null,
            'disconnect_reason' => null,
        ];

        return $this->locked(function () use ($sessionAttributes, $sessionIdHash, $now): ?array {
            $this->markTimedOutSessions($now);

            $session = UserPresenceSession::query()
                ->where('session_id_hash', $sessionIdHash)
                ->first();

            $previousStatus = $session?->status;
            $eventType = null;

            if (! $session instanceof UserPresenceSession) {
                $session = new UserPresenceSession;
                $eventType = UserPresenceEvent::TYPE_CONNECTED;
            } elseif ($session->status === UserPresenceSession::STATUS_OFFLINE) {
                $eventType = UserPresenceEvent::TYPE_CONNECTED;
            } elseif ($previousStatus !== $sessionAttributes['status']) {
                $eventType = UserPresenceEvent::TYPE_STATUS_CHANGED;
            }

            $session->fill([
                ...$sessionAttributes,
                'connected_at' => $session->connected_at ?? $now,
            ]);
            $session->save();

            if ($eventType !== null) {
                $this->recordEvent($session, $eventType, $previousStatus);
            }

            return $this->sessionArray($session);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function leave(Request $request, string $reason = 'closed'): ?array
    {
        $sessionIdHash = $this->recordAuthenticationEvent->sessionIdHash($request);

        if ($sessionIdHash === null) {
            return null;
        }

        $now = now();

        return $this->locked(function () use ($sessionIdHash, $reason, $now): ?array {
            $this->markTimedOutSessions($now);

            $session = UserPresenceSession::query()
                ->where('session_id_hash', $sessionIdHash)
                ->first();

            if (! $session instanceof UserPresenceSession) {
                return null;
            }

            $previousStatus = $session->status;

            $session->fill([
                'connection_count' => 0,
                'status' => UserPresenceSession::STATUS_OFFLINE,
                'visibility_state' => 'hidden',
                'activity_state' => UserPresenceSession::STATUS_OFFLINE,
                'heartbeat_expires_at' => $now,
                'disconnected_at' => $now,
                'disconnect_reason' => $reason,
            ]);
            $session->save();

            if ($previousStatus !== UserPresenceSession::STATUS_OFFLINE) {
                $this->recordEvent($session, UserPresenceEvent::TYPE_DISCONNECTED, $previousStatus);
            }

            return $this->sessionArray($session);
        });
    }

    /**
     * @return array{summary: array<string, int>, sessions: list<array<string, mixed>>}
     */
    public function dashboardState(): array
    {
        $this->cleanup();

        $dashboardSessions = UserPresenceSession::query()
            ->where(function ($query): void {
                $query->online()
                    ->orWhere('disconnected_at', '>=', now()->subSeconds(self::OFFLINE_RETENTION_SECONDS));
            })
            ->orderByRaw("FIELD(status, 'active', 'idle', 'away', 'offline')")
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (UserPresenceSession $session): array => $this->dashboardSession($session))
            ->values();

        return [
            'summary' => [
                'online' => $dashboardSessions->whereIn('status', [
                    UserPresenceSession::STATUS_ACTIVE,
                    UserPresenceSession::STATUS_IDLE,
                    UserPresenceSession::STATUS_AWAY,
                ])->count(),
                'active' => $dashboardSessions->where('status', UserPresenceSession::STATUS_ACTIVE)->count(),
                'idle' => $dashboardSessions->where('status', UserPresenceSession::STATUS_IDLE)->count(),
                'away' => $dashboardSessions->where('status', UserPresenceSession::STATUS_AWAY)->count(),
                'offline' => $dashboardSessions->where('status', UserPresenceSession::STATUS_OFFLINE)->count(),
            ],
            'sessions' => $dashboardSessions->all(),
        ];
    }

    /**
     * @return array{timed_out: int, pruned_sessions: int, pruned_events: int}
     */
    public function cleanup(): array
    {
        $now = now();

        return $this->locked(fn (): array => [
            'timed_out' => $this->markTimedOutSessions($now),
            'pruned_sessions' => $this->pruneOfflineSessions($now),
            'pruned_events' => $this->pruneExpiredEvents($now),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastPayload(?array $session): array
    {
        if ($session === null) {
            return [];
        }

        return [
            'presence_id' => $session['presence_id'] ?? null,
            'user_id' => $session['user_id'] ?? null,
            'status' => $session['status'] ?? null,
            'visibility_state' => $session['visibility_state'] ?? null,
            'last_seen_at' => $session['last_seen_at'] ?? null,
            'last_activity_at' => $session['last_activity_at'] ?? null,
            'heartbeat_expires_at' => $session['heartbeat_expires_at'] ?? null,
            'disconnect_reason' => $session['disconnect_reason'] ?? null,
        ];
    }

    private function locked(callable $callback): mixed
    {
        return Cache::lock(self::LOCK_KEY, 5)->block(2, $callback);
    }

    private function markTimedOutSessions(Carbon $now): int
    {
        $count = 0;

        UserPresenceSession::query()
            ->stale()
            ->orderBy('id')
            ->each(function (UserPresenceSession $session) use ($now, &$count): void {
                $previousStatus = $session->status;

                $session->fill([
                    'connection_count' => 0,
                    'status' => UserPresenceSession::STATUS_OFFLINE,
                    'visibility_state' => 'hidden',
                    'activity_state' => UserPresenceSession::STATUS_OFFLINE,
                    'disconnected_at' => $session->heartbeat_expires_at ?? $now,
                    'disconnect_reason' => 'heartbeat_timeout',
                ]);
                $session->save();

                $this->recordEvent($session, UserPresenceEvent::TYPE_HEARTBEAT_TIMEOUT, $previousStatus);
                $count++;
            });

        return $count;
    }

    private function pruneOfflineSessions(Carbon $now): int
    {
        $count = 0;

        UserPresenceSession::query()
            ->where('status', UserPresenceSession::STATUS_OFFLINE)
            ->whereNotNull('disconnected_at')
            ->where('disconnected_at', '<', $now->copy()->subSeconds(self::OFFLINE_RETENTION_SECONDS))
            ->orderBy('id')
            ->each(function (UserPresenceSession $session) use (&$count): void {
                $this->recordEvent($session, UserPresenceEvent::TYPE_PRUNED, $session->status);
                $session->delete();
                $count++;
            });

        return $count;
    }

    private function pruneExpiredEvents(Carbon $now): int
    {
        return UserPresenceEvent::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<', $now)
            ->delete();
    }

    private function recordEvent(UserPresenceSession $session, string $eventType, ?string $previousStatus = null): void
    {
        UserPresenceEvent::create([
            'user_presence_session_id' => $session->getKey(),
            'user_id' => $session->user_id,
            'active_user_position_id' => $session->active_user_position_id,
            'presence_id' => $session->presence_id,
            'session_id_hash' => $session->session_id_hash,
            'event_type' => $eventType,
            'status' => $session->status,
            'previous_status' => $previousStatus,
            'visibility_state' => $session->visibility_state,
            'disconnect_reason' => $session->disconnect_reason,
            'ip_address' => $session->ip_address,
            'device_type' => $session->device_type,
            'browser_name' => $session->browser_name,
            'platform_name' => $session->platform_name,
            'position_snapshot' => $session->position_snapshot,
            'retention_until' => now()->addDays(self::AUDIT_RETENTION_DAYS),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionArray(UserPresenceSession $session): array
    {
        return [
            'presence_id' => $session->presence_id,
            'session_id_hash' => $session->session_id_hash,
            'user_id' => $session->user_id,
            'user_name' => $session->user_name,
            'account_type' => $session->account_type,
            'account_status' => $session->account_status,
            'connection_count' => $session->connection_count,
            'status' => $session->status,
            'visibility_state' => $session->visibility_state,
            'activity_state' => $session->activity_state,
            'ip_address' => $session->ip_address,
            'proxy_ip_address' => $session->proxy_ip_address,
            'user_agent' => $session->user_agent,
            'device_type' => $session->device_type,
            'device_name' => $session->device_name,
            'browser_name' => $session->browser_name,
            'browser_version' => $session->browser_version,
            'platform_name' => $session->platform_name,
            'platform_version' => $session->platform_version,
            'position' => $session->position_snapshot,
            'connected_at' => $session->connected_at?->toISOString(),
            'last_seen_at' => $session->last_seen_at?->toISOString(),
            'last_activity_at' => $session->last_activity_at?->toISOString(),
            'heartbeat_expires_at' => $session->heartbeat_expires_at?->toISOString(),
            'disconnected_at' => $session->disconnected_at?->toISOString(),
            'disconnect_reason' => $session->disconnect_reason,
            'updated_at' => $session->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardSession(UserPresenceSession $session): array
    {
        $payload = $this->sessionArray($session);

        unset($payload['session_id_hash']);

        $payload['status_sort'] = match ($payload['status'] ?? UserPresenceSession::STATUS_OFFLINE) {
            UserPresenceSession::STATUS_ACTIVE => 10,
            UserPresenceSession::STATUS_IDLE => 20,
            UserPresenceSession::STATUS_AWAY => 30,
            default => 90,
        };

        return $payload;
    }

    private function statusForSignals(string $visibilityState, int $idleSeconds): string
    {
        if ($visibilityState === 'hidden') {
            return UserPresenceSession::STATUS_AWAY;
        }

        if ($idleSeconds >= self::IDLE_THRESHOLD_SECONDS) {
            return UserPresenceSession::STATUS_IDLE;
        }

        return UserPresenceSession::STATUS_ACTIVE;
    }

    private function visibilityState(?string $visibilityState): string
    {
        return in_array($visibilityState, ['visible', 'hidden', 'prerender'], true)
            ? $visibilityState
            : 'visible';
    }

    private function presenceId(string $sessionIdHash): string
    {
        return Str::substr(hash('sha256', $sessionIdHash), 0, 24);
    }

    private function integerOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
