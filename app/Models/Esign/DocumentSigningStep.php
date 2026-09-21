<?php

namespace App\Models\Esign;

use App\Enums\Esign\DocumentSigningStepStatus;
use App\Models\Instansi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use App\Policies\Esign\DocumentSigningStepPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(DocumentSigningStepPolicy::class)]
class DocumentSigningStep extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'is_required' => true,
        'placement_required' => true,
        'status' => 'pending',
        'step_type' => 'sign',
    ];

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'document_signing_workflow_id',
        'sequence',
        'step_type',
        'role_code',
        'assigned_user_id',
        'assigned_user_position_id',
        'assigned_unit_kerja_id',
        'assigned_instansi_id',
        'status',
        'is_required',
        'placement_required',
        'source_artifact_id',
        'result_artifact_id',
        'assignment_snapshot',
        'metadata',
        'activated_at',
        'completed_at',
        'rejected_at',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(DocumentSigningWorkflow::class, 'document_signing_workflow_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id')->withTrashed();
    }

    public function assignedPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'assigned_user_position_id')->withTrashed();
    }

    public function assignedUnitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'assigned_unit_kerja_id')->withTrashed();
    }

    public function assignedInstansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class, 'assigned_instansi_id')->withTrashed();
    }

    public function sourceArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'source_artifact_id');
    }

    public function resultArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'result_artifact_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(EsignAttempt::class, 'document_signing_step_id')->orderBy('attempt_number');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DocumentSigningWorkflowEvent::class, 'document_signing_step_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function legacyLinks(): HasMany
    {
        return $this->hasMany(EsignAttemptLegacyLink::class, 'document_signing_step_id');
    }

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'assignment_snapshot' => 'array',
            'completed_at' => 'datetime',
            'is_required' => 'boolean',
            'metadata' => 'array',
            'placement_required' => 'boolean',
            'rejected_at' => 'datetime',
            'sequence' => 'integer',
            'status' => DocumentSigningStepStatus::class,
        ];
    }
}
