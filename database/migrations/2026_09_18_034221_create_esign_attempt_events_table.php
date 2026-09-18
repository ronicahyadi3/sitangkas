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
        Schema::create('esign_attempt_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('esign_attempt_id');
            $table->foreignId('esign_provider_response_id')->nullable();

            $table->string('event_type', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('result', 20)->default('success');

            $table->foreignId('actor_user_id')->nullable();
            $table->foreignId('actor_user_position_id')->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->text('message')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['esign_attempt_id', 'occurred_at'],
                'ix_esign_attempt_events_attempt_time'
            );

            $table->index(
                ['event_type', 'result', 'occurred_at'],
                'ix_esign_attempt_events_type_result'
            );

            $table->index(
                ['actor_user_id', 'occurred_at'],
                'ix_esign_attempt_events_actor_time'
            );

            $table->foreign('esign_attempt_id', 'fk_eae_attempt')
                ->references('id')
                ->on('esign_attempts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('esign_provider_response_id', 'fk_eae_provider_response')
                ->references('id')
                ->on('esign_provider_responses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('actor_user_id', 'fk_eae_actor_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('actor_user_position_id', 'fk_eae_actor_position')
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
        Schema::dropIfExists('esign_attempt_events');
    }
};
