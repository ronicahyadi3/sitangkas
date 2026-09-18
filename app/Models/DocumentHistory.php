<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentHistory extends Model
{
    public const ACTION_DELETE = 'DELETE';

    public const ACTION_EDITED = 'EDITED';

    public const ACTION_REJECT = 'REJECT';

    public const ACTION_SUBMIT = 'SUBMIT';

    public const ACTION_TTE = 'TTE';

    public const ACTION_UPLOAD = 'UPLOAD';

    public const ACTION_VERIFY = 'VERIFY';

    public const UPDATED_AT = null;

    protected $table = 'document_process';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_user',
        'id_unit_kerja',
        'id_jabatan',
        'id_dokumen',
        'src_name',
        'md5',
        'assigned_to',
        'action',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'id_dokumen')->withTrashed();
    }

    public function actorPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'id_user')->withTrashed();
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'id_unit_kerja')->withTrashed();
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'id_jabatan')->withTrashed();
    }

    public function scopeForDocument(Builder $query, Document|int $document): Builder
    {
        return $query->where('id_dokumen', $document instanceof Document ? $document->getKey() : $document);
    }

    public function scopeWithAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'id_dokumen' => 'integer',
            'id_jabatan' => 'integer',
            'id_unit_kerja' => 'integer',
            'id_user' => 'integer',
        ];
    }
}
