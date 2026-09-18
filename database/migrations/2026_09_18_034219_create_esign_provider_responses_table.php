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
        Schema::create('esign_provider_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('esign_attempt_id')->nullable();
            $table->unsignedSmallInteger('response_sequence')->default(1);
            $table->string('provider', 30)->default('bsre');
            $table->string('operation', 30);
            $table->string('outcome', 30);

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_code', 100)->nullable();
            $table->text('provider_message')->nullable();
            $table->unsignedInteger('provider_time')->nullable();
            $table->string('provider_time_unit', 20)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('content_type', 150)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->unsignedBigInteger('response_size')->nullable();
            $table->char('response_sha256', 64)->nullable();
            $table->json('safe_payload')->nullable();

            $table->foreignId('input_artifact_id')->nullable();
            $table->foreignId('output_artifact_id')->nullable();
            $table->string('source', 30)->default('live');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['esign_attempt_id', 'response_sequence'],
                'uq_esign_provider_responses_sequence'
            );

            $table->index(
                ['provider', 'operation', 'outcome', 'received_at'],
                'ix_esign_provider_responses_operation'
            );

            $table->index(
                ['provider_code', 'received_at'],
                'ix_esign_provider_responses_code'
            );

            $table->index('correlation_id', 'ix_esign_provider_responses_correlation');

            $table->index(
                ['input_artifact_id', 'operation', 'received_at'],
                'ix_esign_provider_responses_input_artifact'
            );

            $table->foreign('esign_attempt_id', 'fk_epr_attempt')
                ->references('id')
                ->on('esign_attempts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('input_artifact_id', 'fk_epr_input_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('output_artifact_id', 'fk_epr_output_artifact')
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
        Schema::dropIfExists('esign_provider_responses');
    }
};
