<?php

namespace App\Models\Esign;

use App\Enums\Esign\EsignMigrationItemStatus;
use App\Enums\Esign\EsignMigrationStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsignMigrationItem extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'attempt_count' => 0,
        'current_stage' => 'discovered',
        'status' => 'pending',
    ];

    /** @var list<string> */
    protected $fillable = [
        'esign_migration_run_id',
        'source_key',
        'source_table',
        'source_id',
        'source_updated_at',
        'legacy_document_id',
        'status',
        'current_stage',
        'attempt_count',
        'document_signing_workflow_id',
        'document_artifact_id',
        'esign_attempt_id',
        'source_sha256',
        'destination_sha256',
        'lease_token',
        'lease_owner',
        'leased_until',
        'heartbeat_at',
        'next_retry_at',
        'reason_code',
        'message',
        'metadata',
        'started_at',
        'completed_at',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(EsignMigrationRun::class, 'esign_migration_run_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(DocumentSigningWorkflow::class, 'document_signing_workflow_id');
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'document_artifact_id');
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EsignAttempt::class, 'esign_attempt_id');
    }

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'completed_at' => 'datetime',
            'current_stage' => EsignMigrationStage::class,
            'heartbeat_at' => 'datetime',
            'leased_until' => 'datetime',
            'legacy_document_id' => 'integer',
            'metadata' => 'array',
            'next_retry_at' => 'datetime',
            'source_id' => 'integer',
            'source_updated_at' => 'datetime',
            'started_at' => 'datetime',
            'status' => EsignMigrationItemStatus::class,
        ];
    }
}
