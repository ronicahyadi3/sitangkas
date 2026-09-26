<?php

namespace App\Models;

use App\Models\Concerns\TracksUserAudit;
use App\Support\EncryptedId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class UserPosition extends Model
{
    use SoftDeletes, TracksUserAudit;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_canonical' => true,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'jabatan_id',
        'instansi_id',
        'unit_kerja_id',
        'is_active',
        'is_canonical',
        'canonical_user_position_id',
        'legacy_duplicate_reason',
        'started_at',
        'ended_at',
        'last_used_at',
        'activated_at',
        'activated_by_user_id',
        'deactivated_at',
        'deactivated_by_user_id',
        'deactivation_reason',
        'notes',
        'source_system',
        'external_id',
        'last_synced_at',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_by_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function canonicalPosition(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_user_position_id')->withTrashed();
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(self::class, 'canonical_user_position_id')->withTrashed();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(UserPositionDocument::class);
    }

    public function primaryDocument(): HasOne
    {
        return $this->hasOne(UserPositionDocument::class)
            ->primary()
            ->latestOfMany();
    }

    public function actedManagementAuditEvents(): HasMany
    {
        return $this->hasMany(UserManagementAuditEvent::class, 'actor_user_position_id');
    }

    public function targetManagementAuditEvents(): HasMany
    {
        return $this->hasMany(UserManagementAuditEvent::class, 'target_user_position_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
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

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        if (ctype_digit(trim((string) $value))) {
            return $query->whereKey([]);
        }

        $id = EncryptedId::tryDecode($value);

        if ($id === null) {
            return $query->whereKey([]);
        }

        return $query->whereKey($id);
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCanonical(Builder $query): Builder
    {
        return $query->where('is_canonical', true);
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereNull('started_at')
                    ->orWhereDate('started_at', '<=', today());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', today());
            });
    }

    public function scopeAvailableForSelection(Builder $query): Builder
    {
        return $query->canonical()->active()->effective();
    }

    public function scopeWithActiveReferences(Builder $query): Builder
    {
        return $query
            ->whereHas('jabatan', fn (Builder $query): Builder => $query->active())
            ->whereHas('instansi', fn (Builder $query): Builder => $query->active())
            ->whereHas('unitKerja', fn (Builder $query): Builder => $query->active()->effective());
    }

    public function scopePreferredFirst(Builder $query): Builder
    {
        return $query->orderByDesc('last_used_at')->orderBy('id');
    }

    public function isEffective(): bool
    {
        $today = today();

        if ($this->started_at !== null && $this->started_at->isAfter($today)) {
            return false;
        }

        return $this->ended_at === null || $this->ended_at->isSameDay($today) || $this->ended_at->isAfter($today);
    }

    public function isAvailableForSelection(): bool
    {
        return $this->is_canonical && $this->is_active && $this->isEffective() && ! $this->trashed();
    }

    public function markAsUsed(): bool
    {
        $usedAt = now();
        $updates = ['last_used_at' => $usedAt];

        if (auth()->id() !== null) {
            $updates['updated_by_user_id'] = auth()->id();
        }

        $updated = DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update($updates);

        if ($updated > 0) {
            $this->forceFill($updates);
        }

        return $updated > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAuthenticationContext(): array
    {
        return [
            'user_position_id' => $this->id,
            'jabatan_id' => $this->jabatan_id,
            'nama_jabatan' => $this->relationLoaded('jabatan') ? $this->jabatan?->nama : null,
            'instansi_id' => $this->instansi_id,
            'nama_instansi' => $this->relationLoaded('instansi') ? $this->instansi?->nama : null,
            'unit_kerja_id' => $this->unit_kerja_id,
            'nama_unit_kerja' => $this->relationLoaded('unitKerja') ? $this->unitKerja?->nama : null,
        ];
    }

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'ended_at' => 'date',
            'is_active' => 'boolean',
            'is_canonical' => 'boolean',
            'pdf_watermark_required' => 'boolean',
            'last_synced_at' => 'datetime',
            'last_used_at' => 'datetime',
            'started_at' => 'date',
        ];
    }
}
