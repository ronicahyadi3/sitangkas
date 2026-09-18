<?php

namespace App\Models\Esign;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentArtifactSignature extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'document_artifact_id',
        'esign_provider_response_id',
        'signature_index',
        'provider_signature_id',
        'field_name',
        'signer_user_id',
        'signer_name',
        'signed_at',
        'location',
        'reason',
        'certificate_level_code',
        'integrity_valid',
        'certificate_trusted',
        'signature_format',
        'is_last_signature',
        'long_term_validation',
        'digest_algorithm',
        'signature_algorithm',
        'timestamp_id',
        'timestamp_at',
        'timestamp_signer_name',
        'verification_conclusion',
        'verified_at',
        'safe_metadata',
    ];

    /** @var list<string> */
    protected $hidden = [
        'safe_metadata',
    ];

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'document_artifact_id');
    }

    public function providerResponse(): BelongsTo
    {
        return $this->belongsTo(EsignProviderResponse::class, 'esign_provider_response_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id')->withTrashed();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(DocumentArtifactSignatureCertificate::class)
            ->orderBy('chain_index');
    }

    protected function casts(): array
    {
        return [
            'certificate_level_code' => 'integer',
            'certificate_trusted' => 'boolean',
            'integrity_valid' => 'boolean',
            'is_last_signature' => 'boolean',
            'long_term_validation' => 'boolean',
            'safe_metadata' => 'array',
            'signature_index' => 'integer',
            'signed_at' => 'datetime',
            'timestamp_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
