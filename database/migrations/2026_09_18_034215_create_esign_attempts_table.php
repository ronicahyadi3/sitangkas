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
        Schema::create('esign_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('idempotency_key')->unique();
            $table->uuid('request_correlation_id')->unique();

            /**
             * `document.id` is a signed INT legacy key and has no canonical FK.
             * Nullable values preserve orphaned legacy history for manual review.
             */
            $table->integer('document_id')->nullable();
            $table->foreignId('document_signing_step_id');
            $table->unsignedSmallInteger('attempt_number');
            $table->foreignId('source_artifact_id');
            $table->foreignId('result_artifact_id')->nullable();

            $table->foreignId('actor_user_id')->nullable();
            $table->foreignId('actor_user_position_id')->nullable();
            $table->foreignId('signer_user_id')->nullable();
            $table->foreignId('signer_user_position_id')->nullable();
            $table->boolean('is_acting')->default(false);
            $table->string('effective_role_code', 50)->nullable();
            $table->foreignId('effective_unit_kerja_id')->nullable();
            $table->foreignId('effective_instansi_id')->nullable();
            $table->json('actor_context_snapshot')->nullable();

            $table->string('provider', 30)->default('bsre');
            $table->char('request_fingerprint', 64);
            $table->char('source_artifact_sha256', 64);
            $table->char('preview_artifact_sha256', 64)->nullable();
            $table->string('status', 30)->default('prepared');
            $table->string('application_error_code', 100)->nullable();
            $table->boolean('retryable')->default(false);
            $table->json('safe_error_context')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('request_sent_at')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['document_signing_step_id', 'attempt_number'],
                'uq_esign_attempts_step_number'
            );

            $table->index(
                ['document_signing_step_id', 'status', 'created_at'],
                'ix_esign_attempts_step_status'
            );

            $table->index(
                ['document_id', 'created_at'],
                'ix_esign_attempts_document_time'
            );

            $table->index(
                ['actor_user_id', 'status', 'created_at'],
                'ix_esign_attempts_actor_status'
            );

            $table->index(
                ['request_fingerprint', 'status'],
                'ix_esign_attempts_fingerprint_status'
            );

            $table->foreign('document_signing_step_id', 'fk_esign_attempts_step')
                ->references('id')
                ->on('document_signing_steps')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('source_artifact_id', 'fk_esign_attempts_source_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('result_artifact_id', 'fk_esign_attempts_result_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('actor_user_id', 'fk_esign_attempts_actor_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('actor_user_position_id', 'fk_esign_attempts_actor_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('signer_user_id', 'fk_esign_attempts_signer_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('signer_user_position_id', 'fk_esign_attempts_signer_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('effective_unit_kerja_id', 'fk_esign_attempts_effective_unit')
                ->references('id')
                ->on('unit_kerjas')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('effective_instansi_id', 'fk_esign_attempts_effective_instansi')
                ->references('id')
                ->on('instansis')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esign_attempts');
    }
};
