<?php

namespace App\Models\Esign;

use App\Enums\Esign\EsignAttemptEventType;
use App\Enums\Esign\EsignAttemptStatus;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\User;
use App\Models\UserPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsignAttemptEvent extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'result' => 'success',
    ];

    /** @var list<string> */
    protected $fillable = [
        'event_uuid',
        'esign_attempt_id',
        'esign_provider_response_id',
        'esign_signature_operation_id',
        'event_type',
        'from_status',
        'to_status',
        'result',
        'actor_user_id',
        'actor_user_position_id',
        'reason_code',
        'message',
        'correlation_id',
        'safe_metadata',
        'occurred_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'safe_metadata',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new EsignInvariantViolationException('attempt_event_is_append_only');
        });

        static::deleting(function (): never {
            throw new EsignInvariantViolationException('attempt_event_is_append_only');
        });
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EsignAttempt::class, 'esign_attempt_id');
    }

    public function providerResponse(): BelongsTo
    {
        return $this->belongsTo(EsignProviderResponse::class, 'esign_provider_response_id');
    }

    public function signatureOperation(): BelongsTo
    {
        return $this->belongsTo(EsignSignatureOperation::class, 'esign_signature_operation_id');
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
            'event_type' => EsignAttemptEventType::class,
            'from_status' => EsignAttemptStatus::class,
            'occurred_at' => 'datetime',
            'safe_metadata' => 'array',
            'to_status' => EsignAttemptStatus::class,
        ];
    }
}
