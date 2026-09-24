<?php

namespace App\Models\Esign;

use App\Enums\Esign\EsignProviderOperation;
use App\Enums\Esign\EsignProviderOutcome;
use App\Exceptions\Esign\EsignInvariantViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EsignProviderResponse extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'provider' => 'bsre',
        'response_sequence' => 1,
        'source' => 'live',
    ];

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'esign_attempt_id',
        'esign_signature_operation_id',
        'response_sequence',
        'provider',
        'operation',
        'outcome',
        'http_status',
        'provider_code',
        'provider_message',
        'provider_time',
        'provider_time_unit',
        'latency_ms',
        'content_type',
        'correlation_id',
        'response_size',
        'response_sha256',
        'safe_payload',
        'input_artifact_id',
        'output_artifact_id',
        'source',
        'received_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'safe_payload',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new EsignInvariantViolationException('provider_response_is_append_only');
        });

        static::deleting(function (): never {
            throw new EsignInvariantViolationException('provider_response_is_append_only');
        });
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EsignAttempt::class, 'esign_attempt_id');
    }

    public function signatureOperation(): BelongsTo
    {
        return $this->belongsTo(EsignSignatureOperation::class, 'esign_signature_operation_id');
    }

    public function inputArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'input_artifact_id');
    }

    public function outputArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'output_artifact_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EsignAttemptEvent::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentArtifactSignature::class);
    }

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
            'latency_ms' => 'integer',
            'operation' => EsignProviderOperation::class,
            'outcome' => EsignProviderOutcome::class,
            'provider_time' => 'integer',
            'received_at' => 'datetime',
            'response_sequence' => 'integer',
            'response_size' => 'integer',
            'safe_payload' => 'array',
        ];
    }
}
