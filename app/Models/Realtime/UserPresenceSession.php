<?php

namespace App\Models\Realtime;

use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserPresenceSession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_IDLE = 'idle';

    public const STATUS_AWAY = 'away';

    public const STATUS_OFFLINE = 'offline';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'presence_id',
        'user_id',
        'active_user_position_id',
        'real_user_position_id',
        'session_id_hash',
        'connection_count',
        'user_name',
        'status',
        'visibility_state',
        'activity_state',
        'account_type',
        'account_status',
        'ip_address',
        'proxy_ip_address',
        'user_agent',
        'device_type',
        'device_name',
        'browser_name',
        'browser_version',
        'platform_name',
        'platform_version',
        'position_snapshot',
        'connected_at',
        'last_seen_at',
        'last_activity_at',
        'heartbeat_expires_at',
        'disconnected_at',
        'disconnect_reason',
        'metadata',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'connection_count' => 1,
        'status' => self::STATUS_ACTIVE,
        'visibility_state' => 'visible',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeUserPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'active_user_position_id');
    }

    public function realUserPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'real_user_position_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(UserPresenceEvent::class);
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_ACTIVE,
            self::STATUS_IDLE,
            self::STATUS_AWAY,
        ]);
    }

    public function scopeStale(Builder $query): Builder
    {
        return $query
            ->whereNot('status', self::STATUS_OFFLINE)
            ->whereNotNull('heartbeat_expires_at')
            ->where('heartbeat_expires_at', '<=', now());
    }

    protected function casts(): array
    {
        return [
            'connected_at' => 'datetime',
            'connection_count' => 'integer',
            'disconnected_at' => 'datetime',
            'heartbeat_expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
            'position_snapshot' => 'array',
        ];
    }
}
