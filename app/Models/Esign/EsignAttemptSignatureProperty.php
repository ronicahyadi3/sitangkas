<?php

namespace App\Models\Esign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EsignAttemptSignatureProperty extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'property_index' => 0,
        'provider_schema_version' => 'bsre-v2.2.0',
        'visual_type' => 'none',
    ];

    /** @var list<string> */
    protected $fillable = [
        'esign_attempt_id',
        'property_index',
        'display_mode',
        'page_number',
        'origin_x',
        'origin_y',
        'width',
        'height',
        'coordinate_origin',
        'page_rotation',
        'location',
        'reason',
        'contact_info',
        'visual_type',
        'visual_storage_disk',
        'visual_file_path',
        'visual_sha256',
        'provider_schema_version',
        'safe_provider_properties',
    ];

    /** @var list<string> */
    protected $hidden = [
        'safe_provider_properties',
        'visual_file_path',
        'visual_sha256',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EsignAttempt::class, 'esign_attempt_id');
    }

    protected function casts(): array
    {
        return [
            'height' => 'decimal:4',
            'origin_x' => 'decimal:4',
            'origin_y' => 'decimal:4',
            'page_number' => 'integer',
            'page_rotation' => 'integer',
            'property_index' => 'integer',
            'safe_provider_properties' => 'array',
            'width' => 'decimal:4',
        ];
    }
}
