<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Candidate key ini sudah dibuat langsung pada migration
         * create_unit_kerjas_table karena juga dibutuhkan oleh composite
         * self-reference parent unit kerja. Migration ini dipertahankan
         * sebagai no-op agar urutan migration lama tetap kompatibel.
         */
    }

    public function down(): void
    {
        /*
         * No-op: index uq_unit_kerjas_id_instansi dimiliki oleh migration
         * create_unit_kerjas_table dan akan hilang saat tabelnya di-drop.
         */
    }
};
