<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnggaranKegiatanTemp extends Model
{
    use SoftDeletes;

    protected $table = 'anggaran_kegiatan_temp';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tahun',
        'kode_urusan',
        'nama_urusan',
        'kode_skpd',
        'nama_skpd',
        'kode_sub_unit',
        'nama_sub_unit',
        'kode_bidang_urusan',
        'nama_bidang_urusan',
        'kode_program',
        'nama_program',
        'kode_kegiatan',
        'nama_kegiatan',
        'kode_sub_kegiatan',
        'nama_sub_kegiatan',
        'kode_sumber_dana',
        'nama_sumber_dana',
        'kode_rekening',
        'nama_rekening',
        'pagu',
        'id_rekening',
        'id_unit_kerja',
    ];

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'id_unit_kerja')->withTrashed();
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('tahun', $year);
    }

    public function scopeForUnit(Builder $query, int $unitKerjaId): Builder
    {
        return $query->where('id_unit_kerja', $unitKerjaId);
    }

    public function scopeForAccount(Builder $query, string $accountId): Builder
    {
        return $query->where('id_rekening', $accountId);
    }

    public function scopeTahunAktif(Builder $query): Builder
    {
        $year = session('tahun_aktif');

        if (! is_numeric($year) || (int) $year <= 0) {
            return $query;
        }

        return $query->forYear((int) $year);
    }

    protected function casts(): array
    {
        return [
            'id_unit_kerja' => 'integer',
            'pagu' => 'decimal:2',
            'tahun' => 'integer',
        ];
    }
}
