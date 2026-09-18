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
        Schema::create('esign_migration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esign_migration_run_id');
            $table->string('source_key', 191);
            $table->string('source_table', 64);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->integer('legacy_document_id')->nullable();

            $table->string('status', 30)->default('pending');
            $table->string('current_stage', 30)->default('discovered');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->foreignId('document_signing_workflow_id')->nullable();
            $table->foreignId('document_artifact_id')->nullable();
            $table->foreignId('esign_attempt_id')->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->char('destination_sha256', 64)->nullable();

            $table->uuid('lease_token')->nullable();
            $table->string('lease_owner', 100)->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            $table->string('reason_code', 100)->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['esign_migration_run_id', 'source_key'],
                'uq_esign_migration_items_source'
            );

            $table->index(
                ['esign_migration_run_id', 'status', 'current_stage', 'id'],
                'ix_esign_migration_items_run_status_stage'
            );

            $table->index(
                ['status', 'next_retry_at'],
                'ix_esign_migration_items_retry'
            );

            $table->index(
                ['status', 'leased_until'],
                'ix_esign_migration_items_lease'
            );

            $table->index(
                ['source_table', 'source_id'],
                'ix_esign_migration_items_legacy_source'
            );

            $table->foreign('esign_migration_run_id', 'fk_emi_run')
                ->references('id')
                ->on('esign_migration_runs')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('document_signing_workflow_id', 'fk_emi_workflow')
                ->references('id')
                ->on('document_signing_workflows')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('document_artifact_id', 'fk_emi_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('esign_attempt_id', 'fk_emi_attempt')
                ->references('id')
                ->on('esign_attempts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esign_migration_items');
    }
};
