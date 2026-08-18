<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_presence_sessions', function (Blueprint $table) {
            $table->id();
            $table->char('presence_id', 24)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('active_user_position_id')->nullable()->constrained('user_positions')->nullOnDelete();
            $table->foreignId('real_user_position_id')->nullable()->constrained('user_positions')->nullOnDelete();
            $table->char('session_id_hash', 64)->unique();
            $table->unsignedSmallInteger('connection_count')->default(1);
            $table->string('user_name')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->string('visibility_state', 20)->default('visible');
            $table->string('activity_state', 20)->nullable();
            $table->string('account_type', 30)->nullable();
            $table->string('account_status', 20)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('proxy_ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('device_name', 100)->nullable();
            $table->string('browser_name', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('platform_name', 100)->nullable();
            $table->string('platform_version', 50)->nullable();
            $table->json('position_snapshot')->nullable();
            $table->timestamp('connected_at', 6)->nullable()->index();
            $table->timestamp('last_seen_at', 6)->nullable()->index();
            $table->timestamp('last_activity_at', 6)->nullable();
            $table->timestamp('heartbeat_expires_at', 6)->nullable()->index();
            $table->timestamp('disconnected_at', 6)->nullable()->index();
            $table->string('disconnect_reason', 50)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps(6);

            $table->index(['user_id', 'status'], 'presence_sessions_user_status_idx');
            $table->index(['status', 'last_seen_at'], 'presence_sessions_status_seen_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_presence_sessions');
    }
};
