<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AfterSign extends Model
{
    use SoftDeletes;

    protected $table = 'after_signs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nik',
        'src_name',
        'md5',
        'response',
        'id_data',
        'status',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'id_data')->withTrashed();
    }

    public function scopeForDocument(Builder $query, Document|int $document): Builder
    {
        return $query->where('id_data', $document instanceof Document ? $document->getKey() : $document);
    }

    public function scopeWithHash(Builder $query, string $md5): Builder
    {
        return $query->where('md5', $md5);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    protected function casts(): array
    {
        return [
            'id_data' => 'integer',
            'status' => 'boolean',
        ];
    }
}
