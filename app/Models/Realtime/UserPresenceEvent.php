<?php

namespace App\Models\Realtime;

use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserPresenceEvent extends Model
{
    public const TYPE_CONNECTED = 'connected';

    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_DISCONNECTED = 'disconnected';

    public const TYPE_HEARTBEAT_TIMEOUT = 'heartbeat_timeout';

    public const TYPE_PRUNED = 'pruned';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_uuid',
        'user_presence_session_id',
        'user_id',
        'active_user_position_id',
        'presence_id',
        'session_id_hash',
        'event_type',
        'status',
        'previous_status',
        'visibility_state',
        'disconnect_reason',
        'ip_address',
        'device_type',
        'browser_name',
        'platform_name',
        'position_snapshot',
        'metadata',
        'occurred_at',
        'retention_until',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserPresenceEvent $event): void {
            $event->event_uuid ??= (string) Str::uuid();
            $event->occurred_at ??= now();
            $event->created_at ??= now();
        });
    }

    public function presenceSession(): BelongsTo
    {
        return $this->belongsTo(UserPresenceSession::class, 'user_presence_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeUserPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'active_user_position_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'position_snapshot' => 'array',
            'retention_until' => 'datetime',
        ];
    }
}
