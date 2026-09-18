<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeforeSign extends Model
{
    use SoftDeletes;

    protected $table = 'before_signs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nik',
        'id_data',
        'src_name',
        'md5',
        'size',
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

    protected function casts(): array
    {
        return [
            'id_data' => 'integer',
        ];
    }
}
