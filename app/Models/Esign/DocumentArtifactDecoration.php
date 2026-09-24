<?php

namespace App\Models\Esign;

use App\Enums\Esign\DocumentArtifactDecorationScope;
use App\Enums\Esign\DocumentArtifactDecorationType;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentArtifactDecoration extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'decoration_index' => 0,
        'is_bold' => false,
        'is_italic' => false,
        'is_underline' => false,
        'page_scope' => 'all_pages',
        'text_alignment' => 'left',
        'text_color' => '#000000',
    ];

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'document_artifact_id',
        'decoration_index',
        'decoration_type',
        'text',
        'font_key',
        'font_size_pt',
        'is_bold',
        'is_italic',
        'is_underline',
        'text_alignment',
        'text_color',
        'page_scope',
        'renderer_version',
        'configuration_sha256',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new EsignInvariantViolationException('artifact_decoration_is_append_only');
        });

        static::deleting(function (): never {
            throw new EsignInvariantViolationException('artifact_decoration_is_append_only');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function artifact(): BelongsTo
    {
        return $this->belongsTo(DocumentArtifact::class, 'document_artifact_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function placements(): HasMany
    {
        return $this->hasMany(DocumentArtifactDecorationPlacement::class)
            ->orderBy('page_number');
    }

    protected function casts(): array
    {
        return [
            'decoration_index' => 'integer',
            'decoration_type' => DocumentArtifactDecorationType::class,
            'font_size_pt' => 'decimal:3',
            'is_bold' => 'boolean',
            'is_italic' => 'boolean',
            'is_underline' => 'boolean',
            'page_scope' => DocumentArtifactDecorationScope::class,
        ];
    }
}
