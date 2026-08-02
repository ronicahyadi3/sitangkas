<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_position_year_permission_events', function (Blueprint $table) {
            $table->id();

            /*
             * UUID event untuk korelasi lintas aplikasi, log server, SIEM,
             * dan proses integrasi.
             */
            $table->uuid('event_uuid')->unique();

            $table->foreignId('user_position_year_permission_id');

            /*
             * requested : permohonan dibuat
             * granted   : izin diberikan/diaktifkan
             * used      : izin digunakan untuk aktivitas tahun historis
             * denied    : percobaan ditolak
             * revoked   : izin dicabut
             * expired   : izin kedaluwarsa
             * rejected  : permohonan ditolak
             * cancelled : permohonan dibatalkan
             */
            $table->string('event_type', 30);

            /*
             * success, failed, denied.
             * denied dibedakan agar audit keamanan mudah difilter.
             */
            $table->string('result', 20)->default('success');
            $table->timestamp('occurred_at')->useCurrent();

            /*
             * Aktor yang melakukan event dan posisi/konteks kerja yang
             * digunakannya saat itu.
             */
            $table->foreignId('actor_user_id')->nullable();
            $table->foreignId('actor_position_id')->nullable();

            /*
             * Detail aktivitas ketika izin dipakai.
             * action: create, update, delete, restore, upload, submit,
             * verify, approve, sign, dan sebagainya.
             */
            $table->string('action', 50)->nullable();
            $table->string('resource_type', 100)->nullable();
            $table->string('resource_id', 100)->nullable();

            /*
             * Kode terstruktur untuk pelaporan, serta pesan manusia.
             */
            $table->string('reason_code', 100)->nullable();
            $table->text('message')->nullable();

            /*
             * Korelasi HTTP/session. Session ID tidak disimpan mentah.
             */
            $table->uuid('request_id')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->char('session_id_hash', 64)->nullable();

            $table->string('route_name', 255)->nullable();
            $table->string('request_path', 500)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            /*
             * Hash opsional dari state sebelum/sesudah perubahan.
             * Jangan menyimpan password, token, passphrase, cookie,
             * Authorization header, atau payload rahasia.
             */
            $table->char('before_state_hash', 64)->nullable();
            $table->char('after_state_hash', 64)->nullable();

            /*
             * Hash event opsional untuk pemeriksaan integritas.
             */
            $table->char('event_hash', 64)->nullable();

            /*
             * Event audit bersifat append-only: tidak memiliki updated_at
             * maupun deleted_at.
             */
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['user_position_year_permission_id', 'occurred_at'],
                'ix_upype_permission_time'
            );

            $table->index(
                ['actor_user_id', 'occurred_at'],
                'ix_upype_actor_user_time'
            );

            $table->index(
                ['actor_position_id', 'occurred_at'],
                'ix_upype_actor_position_time'
            );

            $table->index(
                ['event_type', 'result', 'occurred_at'],
                'ix_upype_event_result_time'
            );

            $table->index(
                ['resource_type', 'resource_id', 'occurred_at'],
                'ix_upype_resource_time'
            );

            $table->index('request_id', 'ix_upype_request_id');

            $table->foreign(
                'user_position_year_permission_id',
                'fk_upype_permission'
            )
                ->references('id')
                ->on('user_position_year_permissions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('actor_user_id', 'fk_upype_actor_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('actor_position_id', 'fk_upype_actor_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE user_position_year_permission_events
            ADD CONSTRAINT chk_upype_event_type
            CHECK (
                event_type IN (
                    'requested',
                    'granted',
                    'used',
                    'denied',
                    'revoked',
                    'expired',
                    'rejected',
                    'cancelled'
                )
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_position_year_permission_events
            ADD CONSTRAINT chk_upype_result
            CHECK (
                result IN ('success', 'failed', 'denied')
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE user_position_year_permission_events
            ADD CONSTRAINT chk_upype_http_status
            CHECK (
                http_status IS NULL
                OR http_status BETWEEN 100 AND 599
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_position_year_permission_events');
    }
};
