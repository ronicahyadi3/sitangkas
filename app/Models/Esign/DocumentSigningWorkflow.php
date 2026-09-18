<?php

namespace App\Models\Esign;

use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Models\Document;
use App\Models\Instansi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSigningWorkflow extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'cycle_number' => 1,
        'definition_version' => 1,
        'lock_version' => 0,
        'source_system' => 'application',
        'status' => 'draft',
        'workflow_variant' => 'default',
    ];

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'document_id',
        'root_document_id',
        'payment_type',
        'document_type',
        'workflow_variant',
        'definition_version',
        'cycle_number',
        'status',
        'current_sequence',
        'current_artifact_id',
        'lock_version',
        'unit_kerja_id',
        'instansi_id',
        'owner_user_id',
        'owner_user_position_id',
        'source_system',
        'metadata',
        'started_at',
        'completed_at',
        'rejected_at',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id')->withTrashed();
    }

    public function rootDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'root_document_id')->withTrashed();
    }

    public function currentArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'current_artifact_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class)->withTrashed();
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id')->withTrashed();
    }

    public function ownerPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'owner_user_position_id')->withTrashed();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(DocumentSigningStep::class)->orderBy('sequence');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentSigningWorkflowEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function legacyLinks(): HasMany
    {
        return $this->hasMany(EsignAttemptLegacyLink::class, 'document_signing_workflow_id');
    }

    public function migrationItems(): HasMany
    {
        return $this->hasMany(EsignMigrationItem::class, 'document_signing_workflow_id');
    }

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'current_sequence' => 'integer',
            'cycle_number' => 'integer',
            'definition_version' => 'integer',
            'document_id' => 'integer',
            'lock_version' => 'integer',
            'metadata' => 'array',
            'rejected_at' => 'datetime',
            'root_document_id' => 'integer',
            'started_at' => 'datetime',
            'status' => DocumentSigningWorkflowStatus::class,
        ];
    }
}
