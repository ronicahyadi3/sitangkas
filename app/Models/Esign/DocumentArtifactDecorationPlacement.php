<?php

namespace App\Models\Esign;

use App\Exceptions\Esign\EsignInvariantViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentArtifactDecorationPlacement extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'coordinate_origin' => 'top_left',
        'page_rotation' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'document_artifact_decoration_id',
        'page_number',
        'page_width',
        'page_height',
        'origin_x',
        'origin_y',
        'width',
        'height',
        'coordinate_origin',
        'page_rotation',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new EsignInvariantViolationException('artifact_decoration_placement_is_append_only');
        });

        static::deleting(function (): never {
            throw new EsignInvariantViolationException('artifact_decoration_placement_is_append_only');
        });
    }

    public function decoration(): BelongsTo
    {
        return $this->belongsTo(
            DocumentArtifactDecoration::class,
            'document_artifact_decoration_id',
        );
    }

    protected function casts(): array
    {
        return [
            'height' => 'decimal:4',
            'origin_x' => 'decimal:4',
            'origin_y' => 'decimal:4',
            'page_height' => 'decimal:4',
            'page_number' => 'integer',
            'page_rotation' => 'integer',
            'page_width' => 'decimal:4',
            'width' => 'decimal:4',
        ];
    }
}
