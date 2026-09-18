<?php

namespace App\Models\Esign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentArtifactSignatureCertificate extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'document_artifact_signature_id',
        'chain_index',
        'provider_certificate_id',
        'signature_algorithm',
        'not_before_at',
        'not_after_at',
        'key_usages',
        'issuer_name',
        'serial_number',
        'common_name',
        'certificate_sha256',
        'safe_metadata',
    ];

    /** @var list<string> */
    protected $hidden = [
        'safe_metadata',
    ];

    public function signature(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifactSignature::class, 'document_artifact_signature_id');
    }

    protected function casts(): array
    {
        return [
            'chain_index' => 'integer',
            'key_usages' => 'array',
            'not_after_at' => 'datetime',
            'not_before_at' => 'datetime',
            'safe_metadata' => 'array',
        ];
    }
}
