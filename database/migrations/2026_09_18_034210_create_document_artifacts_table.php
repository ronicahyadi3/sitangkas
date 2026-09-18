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
        Schema::create('document_artifacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();

            /**
             * `document.id` is a signed INT legacy key and has no canonical FK.
             * Nullable values preserve orphaned legacy history for manual review.
             */
            $table->integer('document_id')->nullable();
            $table->foreignId('parent_artifact_id')->nullable();

            $table->string('artifact_type', 30);
            $table->unsignedInteger('version');
            $table->boolean('is_current')->default(false);

            $table->string('storage_disk', 50)->default('private');
            $table->string('file_path', 500);
            $table->char('storage_path_sha256', 64)->unique();
            $table->string('original_name', 255)->nullable();
            $table->string('stored_name', 255);
            $table->string('mime_type', 100)->default('application/pdf');
            $table->string('extension', 20)->default('pdf');
            $table->unsignedBigInteger('size_bytes');
            $table->char('file_sha256', 64);

            $table->unsignedSmallInteger('document_year')->nullable();
            $table->unsignedTinyInteger('document_month')->nullable();

            $table->string('source_system', 50)->default('application');
            $table->string('source_reference_type', 50)->nullable();
            $table->string('source_reference_id', 100)->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['document_id', 'version'],
                'uq_document_artifacts_document_version'
            );

            $table->index(
                ['document_id', 'is_current', 'created_at'],
                'ix_document_artifacts_current'
            );

            $table->index(
                ['document_year', 'document_month', 'id'],
                'ix_document_artifacts_year_month'
            );

            $table->index(
                ['artifact_type', 'created_at'],
                'ix_document_artifacts_type_time'
            );

            $table->index('file_sha256', 'ix_document_artifacts_file_hash');

            $table->foreign('parent_artifact_id', 'fk_document_artifacts_parent')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('created_by_user_id', 'fk_document_artifacts_creator')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_artifacts');
    }
};
