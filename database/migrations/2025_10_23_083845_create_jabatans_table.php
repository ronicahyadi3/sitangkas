<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jabatans', function (Blueprint $table) {
            $table->id();

            /*
             * Kode stabil untuk business rule dan authorization.
             * Contoh: ADMIN_SUPER, BUD, KUASA_BUD, KPA, PPTK, AUDITOR.
             * Jangan mengandalkan numeric id atau nama jabatan dalam kode.
             */
            $table->string('kode', 50)->unique();

            /* Nama posisi/kapasitas pengguna di dalam SITANGKAS. */
            $table->string('nama', 150)->unique();
            $table->text('deskripsi')->nullable();

            /*
             * Jabatan aktif dapat diberikan pada user_positions baru.
             * is_system melindungi jabatan inti agar tidak dihapus melalui UI.
             */
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            /* Audit aktor pengelola master data. */
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /* Query daftar jabatan aktif untuk pemilih posisi dan master. */
            $table->index(
                ['is_active', 'sort_order'],
                'ix_jabatans_active_sort'
            );

            $table->index(
                ['is_system', 'is_active'],
                'ix_jabatans_system_active'
            );

            $table->foreign('created_by_user_id', 'fk_jabatans_created_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('updated_by_user_id', 'fk_jabatans_updated_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('deleted_by_user_id', 'fk_jabatans_deleted_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatans');
    }
};
