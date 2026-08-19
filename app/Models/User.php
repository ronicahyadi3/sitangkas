<?php

namespace App\Models;

use App\Models\Concerns\TracksUserAudit;
use App\Models\Realtime\RealtimeMessage;
use App\Models\Realtime\UserPresenceSession;
use App\Support\EncryptedId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'nik',
    'nip',
    'nama',
    'email',
    'account_type',
    'status',
    'status_changed_at',
    'status_changed_by_user_id',
    'status_reason',
    'password',
    'password_changed_at',
    'password_expires_at',
    'must_change_password',
    'password_reset_at',
    'password_reset_by_user_id',
    'sessions_invalidated_at',
    'remember_token',
    'email_verified_at',
    'consecutive_failed_login_count',
    'last_failed_login_at',
    'locked_at',
    'locked_until',
    'lock_reason',
    'last_login_at',
    'identity_verified_at',
    'identity_verified_by_user_id',
    'source_system',
    'external_id',
    'last_synced_at',
    'tahun_aktif',
    'created_by_user_id',
    'updated_by_user_id',
    'deleted_by_user_id',
])]
#[Hidden([
    'password',
    'remember_token',
    'mfa_secret',
    'mfa_recovery_codes',
    'mfa_pending_secret',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TracksUserAudit;

    public const ACCOUNT_TYPE_EMERGENCY = 'emergency';

    public const ACCOUNT_TYPE_FUNCTIONAL = 'functional';

    public const ACCOUNT_TYPE_PERSONAL = 'personal';

    public const ACCOUNT_TYPE_SERVICE = 'service';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUSPENDED = 'suspended';

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'status_changed_by_user_id');
    }

    public function passwordResetBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'password_reset_by_user_id');
    }

    public function identityVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'identity_verified_by_user_id');
    }

    public function userPositions(): HasMany
    {
        return $this->hasMany(UserPosition::class);
    }

    public function positions(): HasMany
    {
        return $this->userPositions();
    }

    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    public function presenceSessions(): HasMany
    {
        return $this->hasMany(UserPresenceSession::class);
    }

    public function receivedRealtimeMessages(): HasMany
    {
        return $this->hasMany(RealtimeMessage::class, 'recipient_user_id');
    }

    public function sentRealtimeMessages(): HasMany
    {
        return $this->hasMany(RealtimeMessage::class, 'sender_user_id');
    }

    public function actedLoginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class, 'actor_user_id');
    }

    public function managementAuditEvents(): HasMany
    {
        return $this->hasMany(UserManagementAuditEvent::class, 'target_user_id');
    }

    public function actedManagementAuditEvents(): HasMany
    {
        return $this->hasMany(UserManagementAuditEvent::class, 'actor_user_id');
    }

    public function getRouteKey()
    {
        return EncryptedId::encode($this->getKey());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (ctype_digit(trim((string) $value))) {
            return null;
        }

        $id = EncryptedId::tryDecode($value);

        if ($id === null) {
            return null;
        }

        return $this->newQuery()
            ->whereKey($id)
            ->firstOrFail();
    }

    public function activeUserPositions(): HasMany
    {
        return $this->userPositions()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', today());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', today());
            });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deleted_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeAvailableForPasswordLogin(Builder $query): Builder
    {
        return $query
            ->active()
            ->where(function (Builder $query): void {
                $query->whereNull('locked_until')
                    ->orWhere('locked_until', '<=', now());
            });
    }

    public function scopeWhereLoginIdentifier(Builder $query, string $identifier): Builder
    {
        $normalizedIdentifier = Str::of($identifier)->trim()->lower()->toString();

        if (filter_var($normalizedIdentifier, FILTER_VALIDATE_EMAIL)) {
            return $query->where('email', $normalizedIdentifier);
        }

        $digitsOnlyIdentifier = Str::of($identifier)
            ->replaceMatches('/\D+/', '')
            ->toString();

        return $query->where('nik', $digitsOnlyIdentifier !== '' ? $digitsOnlyIdentifier : $normalizedIdentifier);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isLocked(): bool
    {
        if ($this->locked_until !== null) {
            return $this->locked_until->isFuture();
        }

        if ($this->status === self::STATUS_LOCKED) {
            return true;
        }

        return false;
    }

    public function hasExpiredPassword(): bool
    {
        return $this->password_expires_at !== null && $this->password_expires_at->isPast();
    }

    public function requiresPasswordChange(): bool
    {
        return $this->must_change_password || $this->hasExpiredPassword();
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->nama);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'last_failed_login_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'locked_at' => 'datetime',
            'locked_until' => 'datetime',
            'must_change_password' => 'boolean',
            'mfa_confirmed_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'mfa_last_used_at' => 'datetime',
            'mfa_pending_secret' => 'encrypted',
            'mfa_pending_secret_created_at' => 'datetime',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_recovery_codes_generated_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'password_expires_at' => 'datetime',
            'password_reset_at' => 'datetime',
            'remember_token_expires_at' => 'datetime',
            'sessions_invalidated_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'tahun_aktif' => 'integer',
        ];
    }
}
