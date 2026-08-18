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
        Schema::create('user_presence_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('user_presence_session_id')->nullable()->constrained('user_presence_sessions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('active_user_position_id')->nullable()->constrained('user_positions')->nullOnDelete();
            $table->char('presence_id', 24)->nullable()->index();
            $table->char('session_id_hash', 64)->nullable()->index();
            $table->string('event_type', 50)->index();
            $table->string('status', 20)->nullable()->index();
            $table->string('previous_status', 20)->nullable();
            $table->string('visibility_state', 20)->nullable();
            $table->string('disconnect_reason', 50)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_type', 50)->nullable();
            $table->string('browser_name', 100)->nullable();
            $table->string('platform_name', 100)->nullable();
            $table->json('position_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at', 6)->useCurrent()->index();
            $table->timestamp('retention_until')->nullable()->index();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['user_id', 'occurred_at'], 'presence_events_user_occurred_idx');
            $table->index(['event_type', 'occurred_at'], 'presence_events_type_occurred_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_presence_events');
    }
};
