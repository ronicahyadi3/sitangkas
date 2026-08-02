<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_kerjas', function (Blueprint $table) {
            $table->id();

            /*
             * Setiap unit kerja wajib berada dalam satu ruang lingkup
             * instansi. Contoh:
             * - instansi SKPD -> unit Diskominfo/BKAD
             * - instansi DIKBUD -> unit SMP Negeri
             * - instansi DINKES -> unit Puskesmas
             * - instansi Kecamatan -> unit Kecamatan/Kelurahan
             */
            $table->foreignId('instansi_id');

            /*
             * Hierarki opsional antarunit dalam instansi yang sama.
             * Contoh Kecamatan Klojen menjadi parent kelurahan-kelurahannya.
             */
            $table->foreignId('parent_id')->nullable();

            /*
             * Kode stabil dan unik secara global. Gunakan kode pada seeder,
             * konfigurasi, import, dan business rule; jangan gunakan nama.
             */
            $table->string('kode', 100)->unique();

            $table->string('nama', 200);
            $table->string('nama_singkat', 100)->nullable();

            /*
             * Jenis unit dikelola sebagai PHP backed enum/value object,
             * bukan ENUM database agar jenis baru tidak memerlukan ALTER.
             * Contoh: perangkat_daerah, sekolah, fasilitas_kesehatan,
             * bagian_setda, kecamatan, kelurahan, unit_lain.
             */
            $table->string('jenis', 50)->default('unit_lain');
            $table->text('deskripsi')->nullable();

            /*
             * Unit aktif masih dapat dipilih pada user_positions.
             * Periode berlaku opsional mendukung perubahan organisasi.
             */
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            /* Audit aktor pengelola master data. */
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * Candidate key untuk composite foreign key dari user_positions
             * dan untuk memastikan parent berada pada instansi yang sama.
             * Migration candidate-key terpisah tidak boleh dijalankan lagi.
             */
            $table->unique(
                ['id', 'instansi_id'],
                'uq_unit_kerjas_id_instansi'
            );

            /* Query daftar unit aktif dalam suatu instansi. */
            $table->index(
                ['instansi_id', 'is_active', 'sort_order'],
                'ix_unit_kerjas_instansi_active_sort'
            );

            /* Query child unit dan rendering struktur hierarki. */
            $table->index(
                ['parent_id', 'is_active', 'sort_order'],
                'ix_unit_kerjas_parent_active_sort'
            );

            /* Filter unit berdasarkan jenis. */
            $table->index(
                ['jenis', 'is_active'],
                'ix_unit_kerjas_jenis_active'
            );

            /* Pencarian prefix/nama dan job penonaktifan unit kedaluwarsa. */
            $table->index('nama', 'ix_unit_kerjas_nama');
            $table->index(
                ['effective_until', 'is_active'],
                'ix_unit_kerjas_expiry'
            );

            $table->foreign('instansi_id', 'fk_unit_kerjas_instansi')
                ->references('id')
                ->on('instansis')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
             * Composite self-reference menjamin parent_id berada dalam
             * instansi yang sama dengan child.
             */
            $table->foreign(
                ['parent_id', 'instansi_id'],
                'fk_unit_kerjas_parent_instansi'
            )
                ->references(['id', 'instansi_id'])
                ->on('unit_kerjas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('created_by_user_id', 'fk_unit_kerjas_created_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('updated_by_user_id', 'fk_unit_kerjas_updated_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('deleted_by_user_id', 'fk_unit_kerjas_deleted_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        /* MySQL 8.x: periode tidak boleh terbalik. */
        DB::statement(<<<'SQL'
            ALTER TABLE unit_kerjas
            ADD CONSTRAINT chk_unit_kerjas_effective_period
            CHECK (
                effective_until IS NULL
                OR effective_from IS NULL
                OR effective_until >= effective_from
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_kerjas');
    }
};
