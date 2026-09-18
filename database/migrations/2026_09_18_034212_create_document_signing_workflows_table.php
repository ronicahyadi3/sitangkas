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
        Schema::create('document_signing_workflows', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            /** Both columns match signed INT keys on the legacy `document` table. */
            $table->integer('document_id');
            $table->integer('root_document_id')->nullable();

            $table->string('payment_type', 35);
            $table->string('document_type', 30);
            $table->string('workflow_variant', 30)->default('default');
            $table->unsignedSmallInteger('definition_version')->default(1);
            $table->unsignedInteger('cycle_number')->default(1);

            $table->string('status', 30)->default('draft');
            $table->unsignedSmallInteger('current_sequence')->nullable();
            $table->foreignId('current_artifact_id')->nullable();
            $table->unsignedInteger('lock_version')->default(0);

            $table->foreignId('unit_kerja_id')->nullable();
            $table->foreignId('instansi_id')->nullable();
            $table->foreignId('owner_user_id')->nullable();
            $table->foreignId('owner_user_position_id')->nullable();

            $table->string('source_system', 50)->default('application');
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['document_id', 'cycle_number'],
                'uq_document_signing_workflows_cycle'
            );

            $table->index(
                ['status', 'updated_at'],
                'ix_document_signing_workflows_status_time'
            );

            $table->index(
                ['root_document_id', 'cycle_number', 'status'],
                'ix_document_signing_workflows_package'
            );

            $table->index(
                ['payment_type', 'document_type', 'workflow_variant'],
                'ix_document_signing_workflows_definition'
            );

            $table->foreign('current_artifact_id', 'fk_dsw_current_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('unit_kerja_id', 'fk_dsw_unit_kerja')
                ->references('id')
                ->on('unit_kerjas')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('instansi_id', 'fk_dsw_instansi')
                ->references('id')
                ->on('instansis')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('owner_user_id', 'fk_dsw_owner_user')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('owner_user_position_id', 'fk_dsw_owner_position')
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
        Schema::dropIfExists('document_signing_workflows');
    }
};
