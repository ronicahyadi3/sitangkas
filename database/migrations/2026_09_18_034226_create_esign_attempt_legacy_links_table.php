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
        Schema::create('esign_attempt_legacy_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esign_attempt_id')->nullable();
            $table->foreignId('document_artifact_id')->nullable();
            $table->foreignId('document_signing_workflow_id')->nullable();
            $table->foreignId('document_signing_step_id')->nullable();

            $table->string('legacy_table', 50);
            $table->unsignedBigInteger('legacy_id');
            $table->integer('legacy_document_id')->nullable();
            $table->integer('legacy_document_process_id')->nullable();
            $table->string('legacy_src_name', 150)->nullable();
            $table->string('legacy_checksum', 64)->nullable();

            $table->string('mapping_status', 30)->default('pending');
            $table->string('mapping_reason_code', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('mapped_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['legacy_table', 'legacy_id'],
                'uq_esign_attempt_legacy_links_source'
            );

            $table->index(
                ['legacy_document_id', 'mapping_status'],
                'ix_esign_attempt_legacy_links_document'
            );

            $table->index(
                ['mapping_status', 'updated_at'],
                'ix_esign_attempt_legacy_links_status'
            );

            $table->foreign('esign_attempt_id', 'fk_eall_attempt')
                ->references('id')
                ->on('esign_attempts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('document_artifact_id', 'fk_eall_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('document_signing_workflow_id', 'fk_eall_workflow')
                ->references('id')
                ->on('document_signing_workflows')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('document_signing_step_id', 'fk_eall_step')
                ->references('id')
                ->on('document_signing_steps')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esign_attempt_legacy_links');
    }
};
