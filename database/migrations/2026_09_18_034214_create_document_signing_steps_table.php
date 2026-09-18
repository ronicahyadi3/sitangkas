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
        Schema::create('document_signing_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('document_signing_workflow_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('step_type', 20)->default('sign');
            $table->string('role_code', 50);

            $table->foreignId('assigned_user_id')->nullable();
            $table->foreignId('assigned_user_position_id')->nullable();
            $table->foreignId('assigned_unit_kerja_id')->nullable();
            $table->foreignId('assigned_instansi_id')->nullable();

            $table->string('status', 30)->default('pending');
            $table->boolean('is_required')->default(true);
            $table->boolean('placement_required')->default(true);
            $table->foreignId('source_artifact_id')->nullable();
            $table->foreignId('result_artifact_id')->nullable();

            $table->json('assignment_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['document_signing_workflow_id', 'sequence'],
                'uq_document_signing_steps_sequence'
            );

            $table->index(
                ['document_signing_workflow_id', 'status', 'sequence'],
                'ix_document_signing_steps_workflow_status'
            );

            $table->index(
                ['assigned_user_position_id', 'status'],
                'ix_document_signing_steps_position_status'
            );

            $table->index(
                ['role_code', 'status'],
                'ix_document_signing_steps_role_status'
            );

            $table->foreign('document_signing_workflow_id', 'fk_dss_workflow')
                ->references('id')
                ->on('document_signing_workflows')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('assigned_user_id', 'fk_dss_assigned_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('assigned_user_position_id', 'fk_dss_assigned_position')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('assigned_unit_kerja_id', 'fk_dss_assigned_unit')
                ->references('id')
                ->on('unit_kerjas')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('assigned_instansi_id', 'fk_dss_assigned_instansi')
                ->references('id')
                ->on('instansis')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('source_artifact_id', 'fk_dss_source_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('result_artifact_id', 'fk_dss_result_artifact')
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
        Schema::dropIfExists('document_signing_steps');
    }
};
