<?php

namespace App\Models\Esign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsignAttemptLegacyLink extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'mapping_status' => 'pending',
    ];

    /** @var list<string> */
    protected $fillable = [
        'esign_attempt_id',
        'document_artifact_id',
        'document_signing_workflow_id',
        'document_signing_step_id',
        'legacy_table',
        'legacy_id',
        'legacy_document_id',
        'legacy_document_process_id',
        'legacy_src_name',
        'legacy_checksum',
        'mapping_status',
        'mapping_reason_code',
        'metadata',
        'mapped_at',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EsignAttempt::class, 'esign_attempt_id');
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'document_artifact_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(DocumentSigningWorkflow::class, 'document_signing_workflow_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(DocumentSigningStep::class, 'document_signing_step_id');
    }

    protected function casts(): array
    {
        return [
            'legacy_document_id' => 'integer',
            'legacy_document_process_id' => 'integer',
            'legacy_id' => 'integer',
            'mapped_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
