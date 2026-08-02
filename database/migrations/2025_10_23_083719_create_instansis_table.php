<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instansis', function (Blueprint $table) {
            $table->id();

            /*
             * Kode stabil untuk referensi aplikasi, seeder, konfigurasi,
             * integrasi, dan authorization. Business logic tidak boleh
             * bergantung pada numeric id atau nama yang dapat berubah.
             */
            $table->string('kode', 50)->unique();

            /* Nama resmi/label ruang lingkup organisasi. */
            $table->string('nama', 150)->unique();
            $table->string('nama_singkat', 100)->nullable();
            $table->text('deskripsi')->nullable();

            /*
             * Menentukan apakah instansi masih dapat dipilih untuk membuat
             * user position atau unit kerja baru. Record nonaktif tetap
             * dipertahankan agar histori relasi tidak hilang.
             */
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            /* Audit aktor pengelola master data. */
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /* Query daftar instansi aktif untuk form dan halaman master. */
            $table->index(
                ['is_active', 'sort_order'],
                'ix_instansis_active_sort'
            );

            $table->foreign('created_by_user_id', 'fk_instansis_created_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('updated_by_user_id', 'fk_instansis_updated_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('deleted_by_user_id', 'fk_instansis_deleted_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instansis');
    }
};
