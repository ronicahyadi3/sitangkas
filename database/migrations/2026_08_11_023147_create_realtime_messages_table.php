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
        Schema::create('realtime_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_uuid')->unique();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_user_position_id')->nullable()->constrained('user_positions')->nullOnDelete();
            $table->foreignId('recipient_user_position_id')->nullable()->constrained('user_positions')->nullOnDelete();
            $table->string('type', 40)->default('helper')->index();
            $table->string('severity', 20)->default('info')->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('broadcasted_at', 6)->nullable();
            $table->timestamp('read_at', 6)->nullable()->index();
            $table->timestamp('archived_at', 6)->nullable()->index();
            $table->timestamps(6);

            $table->index(['recipient_user_id', 'read_at', 'created_at'], 'realtime_messages_recipient_read_idx');
            $table->index(['recipient_user_id', 'created_at'], 'realtime_messages_recipient_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('realtime_messages');
    }
};
