<?php

namespace App\Models;

use Database\Factories\UserManagementAuditEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserManagementAuditEvent extends Model
{
    /** @use HasFactory<UserManagementAuditEventFactory> */
    use HasFactory;

    public const EVENT_POSITION_ACTIVATED = 'position.activated';

    public const EVENT_POSITION_CREATED = 'position.created';

    public const EVENT_POSITION_DEACTIVATED = 'position.deactivated';

    public const EVENT_POSITION_DELETED = 'position.deleted';

    public const EVENT_POSITION_UPDATED = 'position.updated';

    public const EVENT_SECURITY_FORCE_PASSWORD_CHANGE = 'security.force_password_change';

    public const EVENT_SECURITY_LOCK = 'security.lock';

    public const EVENT_SECURITY_MFA_RESET = 'security.mfa_reset';

    public const EVENT_SECURITY_UNLOCK = 'security.unlock';

    public const EVENT_USER_CREATED = 'user.created';

    public const EVENT_USER_DELETED = 'user.deleted';

    public const EVENT_USER_UPDATED = 'user.updated';

    public const EVENT_YEAR_PERMISSION_GRANTED = 'year_permission.granted';

    public const EVENT_YEAR_PERMISSION_REVOKED = 'year_permission.revoked';

    public const RESULT_BLOCKED = 'blocked';

    public const RESULT_FAILED = 'failed';

    public const RESULT_SUCCESS = 'success';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_uuid',
        'actor_user_id',
        'actor_user_position_id',
        'target_user_id',
        'target_user_position_id',
        'event_type',
        'result',
        'resource_type',
        'resource_id',
        'reason_code',
        'reason',
        'message',
        'before_state',
        'after_state',
        'changed_fields',
        'metadata',
        'before_state_hash',
        'after_state_hash',
        'event_hash',
        'request_id',
        'correlation_id',
        'session_id_hash',
        'source_channel',
        'route_name',
        'request_path',
        'http_method',
        'http_status',
        'ip_address',
        'user_agent',
        'occurred_at',
        'retention_until',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (UserManagementAuditEvent $auditEvent): void {
            $auditEvent->event_uuid ??= (string) Str::uuid();
            $auditEvent->occurred_at ??= now();
            $auditEvent->created_at ??= now();
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'actor_user_position_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'target_user_position_id');
    }

    protected function casts(): array
    {
        return [
            'after_state' => 'array',
            'before_state' => 'array',
            'changed_fields' => 'array',
            'created_at' => 'datetime',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'retention_until' => 'datetime',
        ];
    }
}
