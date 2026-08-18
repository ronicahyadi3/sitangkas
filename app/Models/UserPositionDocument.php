<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPositionDocument extends Model
{
    use SoftDeletes;

    public const TYPE_AMENDMENT_SK = 'amendment_sk';

    public const TYPE_APPOINTMENT_SK = 'appointment_sk';

    public const TYPE_REVOCATION_SK = 'revocation_sk';

    public const TYPE_SUPPORTING = 'supporting';

    public const TYPE_TASK_ORDER = 'task_order';

    public const VERIFICATION_DRAFT = 'draft';

    public const VERIFICATION_VERIFIED = 'verified';

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_APPOINTMENT_SK => 'SK Pengangkatan/Penetapan',
            self::TYPE_AMENDMENT_SK => 'SK Perubahan/Mutasi',
            self::TYPE_REVOCATION_SK => 'SK Pencabutan',
            self::TYPE_TASK_ORDER => 'Surat Perintah/Penugasan',
            self::TYPE_SUPPORTING => 'Dokumen Pendukung Lainnya',
        ];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_position_id',
        'document_type',
        'document_number',
        'document_date',
        'issued_by',
        'effective_from',
        'effective_until',
        'storage_disk',
        'file_path',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size_bytes',
        'file_sha256',
        'version',
        'is_primary',
        'verification_status',
        'verified_at',
        'verified_by_user_id',
        'verification_notes',
        'uploaded_at',
        'uploaded_by_user_id',
        'source_system',
        'external_id',
        'last_synced_at',
        'metadata',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_by_user_id',
    ];

    public function userPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function scopeAppointmentSk(Builder $query): Builder
    {
        return $query->where('document_type', self::TYPE_APPOINTMENT_SK);
    }

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_primary' => 'boolean',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
