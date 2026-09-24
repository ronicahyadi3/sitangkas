<?php

namespace App\Models\Esign;

use App\Enums\Esign\EsignSignatureOperationStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsignSignatureOperation extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'retryable' => false,
        'status' => 'pending',
    ];

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'esign_attempt_id',
        'esign_attempt_signature_property_id',
        'operation_index',
        'status',
        'input_artifact_id',
        'input_sha256',
        'output_artifact_id',
        'output_sha256',
        'provider_correlation_id',
        'safe_provider_reference',
        'application_error_code',
        'retryable',
        'safe_error_context',
        'started_at',
        'request_sent_at',
        'output_received_at',
        'completed_at',
        'failed_at',
        'public_id_activated_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'input_sha256',
        'output_sha256',
        'safe_error_context',
        'safe_provider_reference',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $operation): void {
            if ($operation->isDirty([
                'public_id',
                'esign_attempt_id',
                'esign_attempt_signature_property_id',
                'operation_index',
            ])) {
                throw new EsignInvariantViolationException('signature_operation_identity_immutable');
            }

            if ($operation->getOriginal('status') === EsignSignatureOperationStatus::Completed->value) {
                $allowedDirtyAttributes = ['public_id_activated_at', 'updated_at'];
                $unexpectedDirtyAttributes = array_diff(
                    array_keys($operation->getDirty()),
                    $allowedDirtyAttributes,
                );

                if ($unexpectedDirtyAttributes !== []) {
                    throw new EsignInvariantViolationException('completed_signature_operation_immutable');
                }
            }

            if ($operation->isDirty('public_id_activated_at')
                && $operation->getOriginal('public_id_activated_at') !== null) {
                throw new EsignInvariantViolationException('signature_operation_public_id_activation_immutable');
            }
        });

        static::deleting(function (): never {
            throw new EsignInvariantViolationException('signature_operation_deletion_prohibited');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EsignAttempt::class, 'esign_attempt_id');
    }

    public function signatureProperty(): BelongsTo
    {
        return $this->belongsTo(
            EsignAttemptSignatureProperty::class,
            'esign_attempt_signature_property_id',
        );
    }

    public function inputArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'input_artifact_id');
    }

    public function outputArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'output_artifact_id');
    }

    public function providerResponses(): HasMany
    {
        return $this->hasMany(EsignProviderResponse::class, 'esign_signature_operation_id')
            ->orderBy('response_sequence');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EsignAttemptEvent::class, 'esign_signature_operation_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'operation_index' => 'integer',
            'output_received_at' => 'datetime',
            'public_id_activated_at' => 'datetime',
            'request_sent_at' => 'datetime',
            'retryable' => 'boolean',
            'safe_error_context' => 'array',
            'started_at' => 'datetime',
            'status' => EsignSignatureOperationStatus::class,
        ];
    }
}
