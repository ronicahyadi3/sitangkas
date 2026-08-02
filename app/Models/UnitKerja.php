<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitKerja extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'instansi_id',
        'parent_id',
        'kode',
        'nama',
        'nama_singkat',
        'jenis',
        'deskripsi',
        'is_active',
        'effective_from',
        'effective_until',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_by_user_id',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function userPositions(): HasMany
    {
        return $this->hasMany(UserPosition::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', today());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', today());
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('nama');
    }

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
