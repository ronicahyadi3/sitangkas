<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel master akun pengguna SITANGKAS.
     *
     * Catatan desain:
     * - Tabel users menyimpan kondisi akun TERKINI dan ringkasan keamanan.
     * - Riwayat setiap login/logout/gagal/blocked disimpan pada login_events.
     * - Password, token, cookie, passphrase TTE, dan secret lain tidak boleh
     *   dicatat pada kolom audit maupun log.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | Identitas pengguna dari struktur legacy
            |------------------------------------------------------------------
            */
            $table->string('nik', 25)->unique();
            $table->string('nip', 30)->nullable()->index();
            $table->string('nama')->index();
            $table->string('email')->nullable()->unique();

            /*
            |------------------------------------------------------------------
            | Klasifikasi dan status akun
            |------------------------------------------------------------------
            |
            | account_type: personal, functional, service, emergency
            | status      : pending, active, inactive, locked, suspended
            */
            $table->string('account_type', 30)->default('personal');
            $table->string('status', 20)->default('active');
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by_user_id')->nullable();
            $table->string('status_reason', 500)->nullable();

            /*
            |------------------------------------------------------------------
            | Kredensial dan kebijakan password
            |------------------------------------------------------------------
            */
            $table->string('password');
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('password_expires_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_reset_at')->nullable();
            $table->foreignId('password_reset_by_user_id')->nullable();
            $table->timestamp('sessions_invalidated_at')->nullable();

            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();

            /*
            |------------------------------------------------------------------
            | Kondisi keamanan akun terkini
            |------------------------------------------------------------------
            |
            | Kolom ini dipakai untuk proses lock/unlock saat autentikasi.
            | Histori lengkapnya tetap ditulis ke login_events.
            */
            $table->unsignedSmallInteger('consecutive_failed_login_count')->default(0);
            $table->timestamp('last_failed_login_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->string('lock_reason', 255)->nullable();

            /*
            | Ringkasan untuk query dashboard cepat. Detail ada di login_events.
            */
            $table->timestamp('last_login_at')->nullable();

            /*
            |------------------------------------------------------------------
            | Verifikasi identitas
            |------------------------------------------------------------------
            */
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignId('identity_verified_by_user_id')->nullable();

            /*
            |------------------------------------------------------------------
            | Asal dan sinkronisasi data
            |------------------------------------------------------------------
            |
            | source_system: legacy, manual, bkpsdm, singo, api, dan lain-lain.
            */
            $table->string('source_system', 50)->default('manual');
            $table->string('external_id', 100)->nullable();
            $table->timestamp('last_synced_at')->nullable();

            /* Preferensi tahun aktif dari database lama. Bukan hak akses. */
            $table->year('tahun_aktif')->nullable();

            /*
            |------------------------------------------------------------------
            | Aktor perubahan terakhir
            |------------------------------------------------------------------
            |
            | Riwayat detail perubahan tetap harus masuk ke audit_logs.
            */
            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
            |------------------------------------------------------------------
            | Index yang mengikuti pola query operasional
            |------------------------------------------------------------------
            */
            $table->index(
                ['status', 'account_type', 'deleted_at'],
                'ix_users_status_type_deleted'
            );

            $table->index(
                ['locked_until', 'status'],
                'ix_users_lock_status'
            );

            $table->index(
                ['last_login_at', 'status'],
                'ix_users_last_login_status'
            );

            $table->unique(
                ['source_system', 'external_id'],
                'uq_users_source_external'
            );
        });

        /*
         * Foreign key yang menunjuk kembali ke tabel users dibuat setelah
         * tabel users selesai dibuat.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->foreign(
                'status_changed_by_user_id',
                'fk_users_status_changed_by'
            )->references('id')->on('users')->nullOnDelete();

            $table->foreign(
                'password_reset_by_user_id',
                'fk_users_password_reset_by'
            )->references('id')->on('users')->nullOnDelete();

            $table->foreign(
                'identity_verified_by_user_id',
                'fk_users_identity_verified_by'
            )->references('id')->on('users')->nullOnDelete();

            $table->foreign(
                'created_by_user_id',
                'fk_users_created_by'
            )->references('id')->on('users')->nullOnDelete();

            $table->foreign(
                'updated_by_user_id',
                'fk_users_updated_by'
            )->references('id')->on('users')->nullOnDelete();

            $table->foreign(
                'deleted_by_user_id',
                'fk_users_deleted_by'
            )->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
