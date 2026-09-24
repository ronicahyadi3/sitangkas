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
        Schema::create('esign_signature_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('esign_attempt_id');
            $table->foreignId('esign_attempt_signature_property_id');
            $table->unsignedSmallInteger('operation_index');
            $table->string('status', 30)->default('pending');

            $table->foreignId('input_artifact_id')->nullable();
            $table->char('input_sha256', 64)->nullable();
            $table->foreignId('output_artifact_id')->nullable();
            $table->char('output_sha256', 64)->nullable();

            $table->uuid('provider_correlation_id')->nullable();
            $table->string('safe_provider_reference', 191)->nullable();
            $table->string('application_error_code', 100)->nullable();
            $table->boolean('retryable')->default(false);
            $table->json('safe_error_context')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('request_sent_at')->nullable();
            $table->timestamp('output_received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('public_id_activated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['esign_attempt_id', 'operation_index'],
                'uq_esign_signature_operations_attempt_index',
            );
            $table->unique(
                'esign_attempt_signature_property_id',
                'uq_esign_signature_operations_property',
            );
            $table->unique(
                'provider_correlation_id',
                'uq_esign_signature_operations_correlation',
            );
            $table->index(
                ['esign_attempt_id', 'status', 'operation_index'],
                'ix_esign_signature_operations_attempt_status',
            );
            $table->index(
                ['status', 'updated_at', 'id'],
                'ix_esign_signature_operations_worker',
            );

            $table->foreign('esign_attempt_id', 'fk_eso_attempt')
                ->references('id')
                ->on('esign_attempts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('esign_attempt_signature_property_id', 'fk_eso_property')
                ->references('id')
                ->on('esign_attempt_signature_properties')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('input_artifact_id', 'fk_eso_input_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('output_artifact_id', 'fk_eso_output_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esign_signature_operations');
    }
};
