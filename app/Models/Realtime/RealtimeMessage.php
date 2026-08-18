<?php

namespace App\Models\Realtime;

use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RealtimeMessage extends Model
{
    public const SEVERITY_INFO = 'info';

    public const SEVERITY_SUCCESS = 'success';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_ERROR = 'error';

    public const TYPE_HELPER = 'helper';

    public const TYPE_NOTIFICATION = 'notification';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_uuid',
        'sender_user_id',
        'recipient_user_id',
        'sender_user_position_id',
        'recipient_user_position_id',
        'type',
        'severity',
        'title',
        'body',
        'action_url',
        'data',
        'broadcasted_at',
        'read_at',
        'archived_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => self::TYPE_HELPER,
        'severity' => self::SEVERITY_INFO,
    ];

    protected static function booted(): void
    {
        static::creating(function (RealtimeMessage $message): void {
            $message->message_uuid ??= (string) Str::uuid();
        });
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function senderUserPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'sender_user_position_id');
    }

    public function recipientUserPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'recipient_user_position_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastPayload(): array
    {
        return [
            'message_uuid' => $this->message_uuid,
            'type' => $this->type,
            'severity' => $this->severity,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->action_url,
            'created_at' => $this->created_at?->toISOString(),
            'data' => $this->data ?? [],
        ];
    }

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'data' => 'array',
            'broadcasted_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
