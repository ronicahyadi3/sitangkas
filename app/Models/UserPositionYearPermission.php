<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPositionYearPermission extends Model
{
    public const TYPE_HISTORICAL_WRITE = 'historical_write';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVOKED = 'revoked';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_position_id',
        'tahun',
        'permission_type',
        'status',
        'valid_from',
        'valid_until',
        'reason',
        'reference_number',
        'reference_date',
        'requested_by_user_id',
        'requested_by_position_id',
        'requested_at',
        'granted_by_user_id',
        'granted_by_position_id',
        'granted_at',
        'grant_notes',
        'revoked_by_user_id',
        'revoked_by_position_id',
        'revoked_at',
        'revocation_reason',
        'usage_count',
        'last_used_at',
        'last_used_by_user_id',
        'last_used_by_position_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function userPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function requestedByPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'requested_by_position_id');
    }

    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function grantedByPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'granted_by_position_id');
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function revokedByPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'revoked_by_position_id');
    }

    public function lastUsedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_used_by_user_id');
    }

    public function lastUsedByPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'last_used_by_position_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function scopeHistoricalWrite(Builder $query): Builder
    {
        return $query->where('permission_type', self::TYPE_HISTORICAL_WRITE);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeCurrentlyEffective(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            });
    }

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'last_used_at' => 'datetime',
            'reference_date' => 'date',
            'requested_at' => 'datetime',
            'revoked_at' => 'datetime',
            'tahun' => 'integer',
            'usage_count' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }
}
