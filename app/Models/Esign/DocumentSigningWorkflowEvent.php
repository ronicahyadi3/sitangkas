<?php

namespace App\Models\Esign;

use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowEventType;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSigningWorkflowEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'actor_is_acting' => false,
    ];

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'document_signing_workflow_id',
        'document_signing_step_id',
        'event_type',
        'from_workflow_status',
        'to_workflow_status',
        'from_step_status',
        'to_step_status',
        'actor_user_id',
        'actor_user_position_id',
        'actor_is_acting',
        'reason_code',
        'message',
        'correlation_id',
        'metadata',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new EsignInvariantViolationException('workflow_event_is_append_only');
        });

        static::deleting(function (): never {
            throw new EsignInvariantViolationException('workflow_event_is_append_only');
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(DocumentSigningWorkflow::class, 'document_signing_workflow_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(DocumentSigningStep::class, 'document_signing_step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }

    public function actorPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'actor_user_position_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'actor_is_acting' => 'boolean',
            'event_type' => DocumentSigningWorkflowEventType::class,
            'from_step_status' => DocumentSigningStepStatus::class,
            'from_workflow_status' => DocumentSigningWorkflowStatus::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'to_step_status' => DocumentSigningStepStatus::class,
            'to_workflow_status' => DocumentSigningWorkflowStatus::class,
        ];
    }
}
