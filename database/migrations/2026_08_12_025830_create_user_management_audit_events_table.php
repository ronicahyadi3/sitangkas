<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log administrasi management users yang append-only.
     *
     * Tabel ini dipakai untuk audit tindakan admin seperti create/update user,
     * tambah/nonaktifkan posisi, grant/revoke izin historis, upload SK,
     * force change password, lock/unlock, dan reset MFA.
     */
    public function up(): void
    {
        Schema::create('user_management_audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();

            /*
            |------------------------------------------------------------------
            | Aktor dan target
            |------------------------------------------------------------------
            |
            | actor_user_position_id disimpan karena PA/KPA bekerja dalam scope
            | posisi tertentu. Jika user/posisi dihapus, audit tetap dipertahankan
            | dan relasi dibuat NULL.
            */
            $table->foreignId('actor_user_id')->nullable();
            $table->foreignId('actor_user_position_id')->nullable();
            $table->foreignId('target_user_id')->nullable();
            $table->foreignId('target_user_position_id')->nullable();

            /*
            |------------------------------------------------------------------
            | Jenis tindakan dan hasil
            |------------------------------------------------------------------
            */
            $table->string('event_type', 80);
            $table->string('result', 30);
            $table->string('resource_type', 100)->nullable();
            $table->string('resource_id', 100)->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->text('reason')->nullable();
            $table->string('message', 500)->nullable();

            /*
            |------------------------------------------------------------------
            | Snapshot perubahan
            |------------------------------------------------------------------
            */
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->char('before_state_hash', 64)->nullable();
            $table->char('after_state_hash', 64)->nullable();
            $table->char('event_hash', 64)->nullable();

            /*
            |------------------------------------------------------------------
            | Konteks request
            |------------------------------------------------------------------
            */
            $table->uuid('request_id')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->char('session_id_hash', 64)->nullable();
            $table->string('source_channel', 30)->default('web');
            $table->string('route_name', 150)->nullable();
            $table->string('request_path', 500)->nullable();
            $table->string('http_method', 10)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('occurred_at', 6)->useCurrent();
            $table->timestamp('retention_until')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();

            /*
            |------------------------------------------------------------------
            | Index audit dan laporan
            |------------------------------------------------------------------
            */
            $table->index(
                ['actor_user_id', 'occurred_at'],
                'ix_user_mgmt_audit_actor_time'
            );

            $table->index(
                ['actor_user_position_id', 'occurred_at'],
                'ix_user_mgmt_audit_actor_position_time'
            );

            $table->index(
                ['target_user_id', 'occurred_at'],
                'ix_user_mgmt_audit_target_user_time'
            );

            $table->index(
                ['target_user_position_id', 'occurred_at'],
                'ix_user_mgmt_audit_target_position_time'
            );

            $table->index(
                ['resource_type', 'resource_id', 'occurred_at'],
                'ix_user_mgmt_audit_resource_time'
            );

            $table->index(
                ['event_type', 'result', 'occurred_at'],
                'ix_user_mgmt_audit_type_result_time'
            );

            $table->index(
                ['ip_address', 'occurred_at'],
                'ix_user_mgmt_audit_ip_time'
            );

            $table->index('request_id', 'ix_user_mgmt_audit_request_id');
            $table->index('occurred_at', 'ix_user_mgmt_audit_occurred_at');
            $table->index('retention_until', 'ix_user_mgmt_audit_retention');

            $table->foreign('actor_user_id', 'fk_user_mgmt_audit_actor_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('actor_user_position_id', 'fk_user_mgmt_audit_actor_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('target_user_id', 'fk_user_mgmt_audit_target_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('target_user_position_id', 'fk_user_mgmt_audit_target_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_management_audit_events');
    }
};
