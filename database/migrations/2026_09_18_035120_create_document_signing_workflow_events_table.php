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
        Schema::create('document_signing_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('document_signing_workflow_id');
            $table->foreignId('document_signing_step_id')->nullable();

            $table->string('event_type', 50);
            $table->string('from_workflow_status', 30)->nullable();
            $table->string('to_workflow_status', 30)->nullable();
            $table->string('from_step_status', 30)->nullable();
            $table->string('to_step_status', 30)->nullable();

            $table->foreignId('actor_user_id')->nullable();
            $table->foreignId('actor_user_position_id')->nullable();
            $table->boolean('actor_is_acting')->default(false);
            $table->string('reason_code', 50)->nullable();
            $table->text('message')->nullable();
            $table->string('correlation_id', 100)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['document_signing_workflow_id', 'occurred_at', 'id'],
                'ix_dsw_events_workflow_timeline'
            );

            $table->index(
                ['document_signing_step_id', 'occurred_at', 'id'],
                'ix_dsw_events_step_timeline'
            );

            $table->index(
                ['event_type', 'occurred_at'],
                'ix_dsw_events_type_time'
            );

            $table->index(
                ['actor_user_id', 'occurred_at'],
                'ix_dsw_events_actor_time'
            );

            $table->index('correlation_id', 'ix_dsw_events_correlation');

            $table->foreign('document_signing_workflow_id', 'fk_dswe_workflow')
                ->references('id')
                ->on('document_signing_workflows')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('document_signing_step_id', 'fk_dswe_step')
                ->references('id')
                ->on('document_signing_steps')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('actor_user_id', 'fk_dswe_actor_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('actor_user_position_id', 'fk_dswe_actor_position')
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
        Schema::dropIfExists('document_signing_workflow_events');
    }
};
