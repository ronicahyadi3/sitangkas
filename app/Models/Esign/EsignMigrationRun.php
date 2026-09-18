<?php

namespace App\Models\Esign;

use App\Enums\Esign\EsignMigrationRunStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsignMigrationRun extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'failed_items' => 0,
        'needs_review_items' => 0,
        'processed_items' => 0,
        'source_system' => 'sitangkas_legacy',
        'status' => 'pending',
        'succeeded_items' => 0,
        'total_items' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'run_uuid',
        'migration_type',
        'source_system',
        'status',
        'high_watermark',
        'last_checkpoint',
        'total_items',
        'processed_items',
        'succeeded_items',
        'failed_items',
        'needs_review_items',
        'lease_token',
        'lease_owner',
        'leased_until',
        'heartbeat_at',
        'started_at',
        'paused_at',
        'finished_at',
        'parameters',
        'error_summary',
        'created_by_user_id',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(EsignMigrationItem::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'failed_items' => 'integer',
            'finished_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'high_watermark' => 'integer',
            'last_checkpoint' => 'integer',
            'leased_until' => 'datetime',
            'needs_review_items' => 'integer',
            'parameters' => 'array',
            'paused_at' => 'datetime',
            'processed_items' => 'integer',
            'started_at' => 'datetime',
            'status' => EsignMigrationRunStatus::class,
            'succeeded_items' => 'integer',
            'total_items' => 'integer',
        ];
    }
}
