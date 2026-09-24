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
        Schema::create('document_artifact_decorations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('document_artifact_id');
            $table->unsignedSmallInteger('decoration_index')->default(0);
            $table->string('decoration_type', 30);
            $table->text('text');
            $table->string('font_key', 50);
            $table->decimal('font_size_pt', 8, 3);
            $table->boolean('is_bold')->default(false);
            $table->boolean('is_italic')->default(false);
            $table->boolean('is_underline')->default(false);
            $table->string('text_alignment', 20)->default('left');
            $table->char('text_color', 7)->default('#000000');
            $table->string('page_scope', 30)->default('all_pages');
            $table->string('renderer_version', 50);
            $table->char('configuration_sha256', 64);
            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['document_artifact_id', 'decoration_index'],
                'uq_document_artifact_decorations_index',
            );
            $table->index(
                ['document_artifact_id', 'decoration_type'],
                'ix_document_artifact_decorations_artifact_type',
            );
            $table->index(
                ['configuration_sha256', 'created_at'],
                'ix_document_artifact_decorations_config',
            );

            $table->foreign('document_artifact_id', 'fk_dad_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_dad_creator')
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
        Schema::dropIfExists('document_artifact_decorations');
    }
};
