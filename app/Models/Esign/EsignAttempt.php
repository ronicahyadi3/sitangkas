<?php

namespace App\Models\Esign;

use App\Enums\Esign\EsignAttemptStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\Instansi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPosition;
use App\Policies\Esign\EsignAttemptPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(EsignAttemptPolicy::class)]
class EsignAttempt extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'is_acting' => false,
        'provider' => 'bsre',
        'retryable' => false,
        'status' => 'prepared',
    ];

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'idempotency_key',
        'request_correlation_id',
        'document_id',
        'document_signing_step_id',
        'attempt_number',
        'source_artifact_id',
        'result_artifact_id',
        'actor_user_id',
        'actor_user_position_id',
        'signer_user_id',
        'signer_user_position_id',
        'is_acting',
        'effective_role_code',
        'effective_unit_kerja_id',
        'effective_instansi_id',
        'actor_context_snapshot',
        'provider',
        'request_fingerprint',
        'source_artifact_sha256',
        'preview_artifact_sha256',
        'status',
        'application_error_code',
        'retryable',
        'safe_error_context',
        'started_at',
        'request_sent_at',
        'response_received_at',
        'completed_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'actor_context_snapshot',
        'safe_error_context',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $attempt): void {
            if ($attempt->isDirty('document_id')) {
                throw new EsignInvariantViolationException('attempt_document_id_immutable');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id')->withTrashed();
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(DocumentSigningStep::class, 'document_signing_step_id');
    }

    public function sourceArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'source_artifact_id');
    }

    public function resultArtifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'result_artifact_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }

    public function actorPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'actor_user_position_id')->withTrashed();
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id')->withTrashed();
    }

    public function signerPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'signer_user_position_id')->withTrashed();
    }

    public function effectiveUnitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'effective_unit_kerja_id')->withTrashed();
    }

    public function effectiveInstansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class, 'effective_instansi_id')->withTrashed();
    }

    public function signatureProperties(): HasMany
    {
        return $this->hasMany(EsignAttemptSignatureProperty::class)->orderBy('property_index');
    }

    public function providerResponses(): HasMany
    {
        return $this->hasMany(EsignProviderResponse::class)->orderBy('response_sequence');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EsignAttemptEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function legacyLinks(): HasMany
    {
        return $this->hasMany(EsignAttemptLegacyLink::class);
    }

    public function migrationItems(): HasMany
    {
        return $this->hasMany(EsignMigrationItem::class, 'esign_attempt_id');
    }

    protected function casts(): array
    {
        return [
            'actor_context_snapshot' => 'array',
            'attempt_number' => 'integer',
            'completed_at' => 'datetime',
            'document_id' => 'integer',
            'is_acting' => 'boolean',
            'request_sent_at' => 'datetime',
            'response_received_at' => 'datetime',
            'retryable' => 'boolean',
            'safe_error_context' => 'array',
            'started_at' => 'datetime',
            'status' => EsignAttemptStatus::class,
        ];
    }
}
