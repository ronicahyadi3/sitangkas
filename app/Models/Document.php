<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    public const TYPE_BMD = 'BMD';

    public const TYPE_SP = 'SP';

    public const TYPE_SP2D = 'SP2D';

    public const TYPE_SPJ = 'SPJ';

    public const TYPE_SPM = 'SPM';

    public const TYPE_SPP = 'SPP';

    public const TYPE_SPTJM = 'SPTJM';

    public const TYPE_SP_PENGAJUAN = 'SP_PENGAJUAN';

    protected $table = 'document';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nomor',
        'uraian',
        'nominal',
        'rekening',
        'src_name',
        'src_type',
        'billing',
        'spj_fungsional',
        'payment_type',
        'expenditure_type',
        'status',
        'reference_id',
        'parent_id',
        'id_unit_kerja',
        'uploaded_by',
        'verify',
        'rejected_by',
        'users_to',
        'notes',
        'submit',
        'assigned_to',
        'downloaded_by',
        'downloaded_at',
        'denied_billing_at',
        'signed_at',
        'finished_at',
    ];

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'id_unit_kerja')->withTrashed();
    }

    public function uploadedByPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'uploaded_by')->withTrashed();
    }

    public function recipientPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'users_to')->withTrashed();
    }

    public function rejectedByJabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'rejected_by')->withTrashed();
    }

    public function downloadedByPosition(): BelongsTo
    {
        return $this->belongsTo(UserPosition::class, 'downloaded_by')->withTrashed();
    }

    public function referenceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference_id')->withTrashed();
    }

    public function referencedDocuments(): HasMany
    {
        return $this->hasMany(self::class, 'reference_id');
    }

    public function parentDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withTrashed();
    }

    public function childDocuments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DocumentHistory::class, 'id_dokumen');
    }

    public function anggaranKegiatan(): HasMany
    {
        return $this->hasMany(AnggaranKegiatan::class, 'id_spp');
    }

    public function beforeSigns(): HasMany
    {
        return $this->hasMany(BeforeSign::class, 'id_data');
    }

    public function afterSigns(): HasMany
    {
        return $this->hasMany(AfterSign::class, 'id_data');
    }

    public function spj(): HasOne
    {
        return $this->hasOne(self::class, 'reference_id')
            ->where('src_type', self::TYPE_SPJ);
    }

    public function bmd(): HasOne
    {
        return $this->hasOne(self::class, 'reference_id')
            ->where('src_type', self::TYPE_BMD);
    }

    public function scopeForPaymentType(Builder $query, string $paymentType): Builder
    {
        return $query->where('payment_type', $paymentType);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('src_type', $type);
    }

    /**
     * @return list<string>
     */
    public function getStatusListAttribute(): array
    {
        return $this->parseLegacyIdList($this->status);
    }

    /**
     * @return list<string>
     */
    public function getSubmitListAttribute(): array
    {
        return $this->parseLegacyIdList($this->submit);
    }

    /**
     * @return list<string>
     */
    public function assignedPositionIds(): array
    {
        return $this->parseLegacyIdList($this->assigned_to);
    }

    protected function casts(): array
    {
        return [
            'denied_billing_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'downloaded_by' => 'integer',
            'finished_at' => 'datetime',
            'id_unit_kerja' => 'integer',
            'nominal' => 'decimal:2',
            'parent_id' => 'integer',
            'reference_id' => 'integer',
            'rejected_by' => 'integer',
            'signed_at' => 'datetime',
            'uploaded_by' => 'integer',
            'users_to' => 'integer',
            'verify' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    private function parseLegacyIdList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $id): bool => $id !== '',
        ));
    }
}
