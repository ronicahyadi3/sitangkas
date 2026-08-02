<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_positions', function (Blueprint $table) {
            $table->id();

            /*
             * Empat kolom inti pembentuk konteks kerja pengguna.
             * Seluruhnya wajib terisi agar setiap aktivitas memiliki
             * konteks jabatan dan organisasi yang lengkap.
             */
            $table->foreignId('user_id');
            $table->foreignId('jabatan_id');
            $table->foreignId('instansi_id');
            $table->foreignId('unit_kerja_id');

            /*
             * Menyatakan posisi masih tersedia untuk dipilih.
             * BUKAN penanda posisi yang sedang dipakai pada request/session.
             * Beberapa posisi milik satu pengguna boleh sama-sama aktif.
             */
            $table->boolean('is_active')
                ->default(true)
                ->comment('Posisi tersedia untuk dipilih; bukan posisi yang sedang dipakai di session');

            /*
             * Masa berlaku opsional. Posisi efektif jika:
             * - is_active = true
             * - started_at NULL atau <= tanggal saat ini
             * - ended_at NULL atau >= tanggal saat ini
             * - deleted_at IS NULL
             */
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();

            /*
             * Preferensi posisi terakhir. Setelah login, aplikasi memilih
             * posisi aktif dengan last_used_at terbaru. Posisi yang sedang
             * dipakai tetap disimpan di session sebagai user_position_id.
             */
            $table->timestamp('last_used_at')->nullable();

            /* Audit aktivasi / penonaktifan posisi. */
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable();

            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('deactivated_by_user_id')->nullable();
            $table->string('deactivation_reason', 500)->nullable();

            /* Keterangan administratif yang tidak cocok menjadi kolom khusus. */
            $table->text('notes')->nullable();

            /*
             * Identitas sumber untuk proses impor / sinkronisasi idempotent.
             * Contoh source_system: legacy, manual, bkpsdm, api.
             */
            $table->string('source_system', 50)->default('manual');
            $table->string('external_id', 100)->nullable();
            $table->timestamp('last_synced_at')->nullable();

            /* Audit aktor pengelola record. */
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * Satu kombinasi konteks hanya boleh mempunyai satu record.
             * Jika pernah dihapus lalu dibutuhkan kembali, restore record lama
             * dan aktifkan kembali; jangan membuat duplikat baru.
             */
            $table->unique(
                ['user_id', 'jabatan_id', 'instansi_id', 'unit_kerja_id'],
                'uq_user_positions_context'
            );

            /* Query utama saat login dan saat membuka pemilih posisi. */
            $table->index(
                ['user_id', 'is_active', 'last_used_at'],
                'ix_user_positions_user_active_last_used'
            );

            /* Daftar pengguna menurut unit kerja dan jabatan. */
            $table->index(
                ['unit_kerja_id', 'jabatan_id', 'is_active'],
                'ix_user_positions_unit_jabatan_active'
            );

            /* Daftar seluruh posisi aktif di suatu instansi. */
            $table->index(
                ['instansi_id', 'is_active'],
                'ix_user_positions_instansi_active'
            );

            /* Mendukung job yang menonaktifkan posisi kedaluwarsa. */
            $table->index(
                ['ended_at', 'is_active'],
                'ix_user_positions_expiry'
            );

            /* Mencegah duplikasi dari sistem sumber saat sinkronisasi ulang. */
            $table->unique(
                ['source_system', 'external_id'],
                'uq_user_positions_source_external'
            );

            /* Foreign key utama. */
            $table->foreign('user_id', 'fk_user_positions_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('jabatan_id', 'fk_user_positions_jabatan')
                ->references('id')
                ->on('jabatans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
             * Composite FK sekaligus memastikan instansi_id sesuai dengan
             * instansi milik unit_kerja_id.
             */
            $table->foreign(
                ['unit_kerja_id', 'instansi_id'],
                'fk_user_positions_unit_instansi'
            )
                ->references(['id', 'instansi_id'])
                ->on('unit_kerjas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /* Foreign key audit actor. */
            $table->foreign('activated_by_user_id', 'fk_user_positions_activated_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('deactivated_by_user_id', 'fk_user_positions_deactivated_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('created_by_user_id', 'fk_user_positions_created_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('updated_by_user_id', 'fk_user_positions_updated_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('deleted_by_user_id', 'fk_user_positions_deleted_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        /* MySQL 8.x: tanggal selesai tidak boleh lebih awal dari tanggal mulai. */
        DB::statement(<<<'SQL'
            ALTER TABLE user_positions
            ADD CONSTRAINT chk_user_positions_period
            CHECK (
                ended_at IS NULL
                OR started_at IS NULL
                OR ended_at >= started_at
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_positions');
    }
};
