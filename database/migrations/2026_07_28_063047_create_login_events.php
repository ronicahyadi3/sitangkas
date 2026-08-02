<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat log autentikasi yang append-only dan terpisah dari users.
     *
     * event_type dan result sengaja dipisahkan:
     * - event_type: login, logout, lockout, unlock, session_timeout,
     *               session_revoked, mfa_challenge, dan lain-lain.
     * - result    : success, failed, blocked, expired, revoked.
     *
     * Tabel ini tidak memakai updated_at dan softDeletes agar catatan audit
     * tidak diedit atau dihapus melalui alur aplikasi biasa.
     */
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();

            /*
            |------------------------------------------------------------------
            | Subjek, aktor, dan snapshot identitas
            |------------------------------------------------------------------
            */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /* Untuk login-as/impersonation atau tindakan admin. */
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Nilai identifier sebaiknya dienkripsi pada layer aplikasi.
             * Hash dipakai untuk pencarian/korelasi tanpa membuka identifier.
             */
            $table->string('login_identifier_type', 30)->nullable();
            $table->text('login_identifier')->nullable();
            $table->char('login_identifier_hash', 64)->nullable();

            /* Snapshot role/unit/status saat event terjadi. */
            $table->json('user_context')->nullable();

            /*
            |------------------------------------------------------------------
            | Jenis kejadian dan hasil
            |------------------------------------------------------------------
            */
            $table->string('event_type', 50);
            $table->string('result', 30);
            $table->string('failure_code', 100)->nullable();
            $table->string('message', 500)->nullable();
            $table->unsignedSmallInteger('attempt_number')->default(1);

            /*
            |------------------------------------------------------------------
            | Metode autentikasi
            |------------------------------------------------------------------
            */
            $table->string('auth_guard', 50)->nullable();
            $table->string('auth_method', 50)->nullable();
            $table->string('auth_provider', 100)->nullable();
            $table->boolean('remember_me')->nullable();
            $table->string('mfa_method', 50)->nullable();
            $table->string('mfa_result', 30)->nullable();

            /*
            |------------------------------------------------------------------
            | Korelasi request, session, dan token
            |------------------------------------------------------------------
            |
            | Session/token asli tidak disimpan. Hanya hash SHA-256.
            */
            $table->uuid('request_id')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->char('session_id_hash', 64)->nullable();
            $table->char('token_id_hash', 64)->nullable();

            $table->string('source_channel', 30)->default('web');
            $table->string('route_name', 150)->nullable();
            $table->string('request_path', 500)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();

            /*
            |------------------------------------------------------------------
            | Informasi jaringan
            |------------------------------------------------------------------
            */
            $table->ipAddress('ip_address')->nullable();
            $table->ipAddress('proxy_ip_address')->nullable();
            $table->json('forwarded_for')->nullable();

            $table->string('network_asn', 30)->nullable();
            $table->string('network_organization')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();

            $table->boolean('is_vpn')->nullable();
            $table->boolean('is_proxy')->nullable();
            $table->boolean('is_tor')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();

            /*
            |------------------------------------------------------------------
            | User agent dan perangkat
            |------------------------------------------------------------------
            */
            $table->text('user_agent')->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('device_name', 100)->nullable();
            $table->string('browser_name', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('platform_name', 100)->nullable();
            $table->string('platform_version', 50)->nullable();
            $table->string('client_timezone', 100)->nullable();
            $table->string('accept_language', 255)->nullable();
            $table->char('device_fingerprint_hash', 64)->nullable();

            /*
            |------------------------------------------------------------------
            | CAPTCHA / bot protection
            |------------------------------------------------------------------
            */
            $table->string('captcha_provider', 30)->nullable();
            $table->decimal('captcha_score', 4, 3)->nullable();
            $table->string('captcha_action', 100)->nullable();
            $table->boolean('captcha_success')->nullable();
            $table->json('captcha_error_codes')->nullable();

            /*
            |------------------------------------------------------------------
            | Konteks aplikasi dan integritas
            |------------------------------------------------------------------
            */
            $table->string('application_version', 50)->nullable();
            $table->string('environment', 30)->nullable();
            $table->string('server_node', 100)->nullable();

            /* Data tambahan yang jarang dicari/filter. */
            $table->json('metadata')->nullable();

            /* Hash dari payload event yang sudah dinormalisasi. */
            $table->char('event_hash', 64)->nullable();

            $table->timestamp('occurred_at', 6)->useCurrent();
            $table->timestamp('retention_until')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();

            /*
            |------------------------------------------------------------------
            | Index audit dan investigasi
            |------------------------------------------------------------------
            */
            $table->index(
                ['user_id', 'occurred_at'],
                'ix_login_events_user_time'
            );

            $table->index(
                ['actor_user_id', 'occurred_at'],
                'ix_login_events_actor_time'
            );

            $table->index(
                ['login_identifier_hash', 'occurred_at'],
                'ix_login_events_identifier_time'
            );

            $table->index(
                ['ip_address', 'occurred_at'],
                'ix_login_events_ip_time'
            );

            $table->index(
                ['event_type', 'result', 'occurred_at'],
                'ix_login_events_type_result_time'
            );

            $table->index(
                ['session_id_hash', 'occurred_at'],
                'ix_login_events_session_time'
            );

            $table->index('request_id', 'ix_login_events_request_id');
            $table->index('occurred_at', 'ix_login_events_occurred_at');
            $table->index('retention_until', 'ix_login_events_retention');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
