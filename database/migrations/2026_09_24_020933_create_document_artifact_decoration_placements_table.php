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
        Schema::create('document_artifact_decoration_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_artifact_decoration_id');
            $table->unsignedSmallInteger('page_number');
            $table->decimal('page_width', 14, 4);
            $table->decimal('page_height', 14, 4);
            $table->decimal('origin_x', 14, 4);
            $table->decimal('origin_y', 14, 4);
            $table->decimal('width', 14, 4);
            $table->decimal('height', 14, 4);
            $table->string('coordinate_origin', 20)->default('top_left');
            $table->smallInteger('page_rotation')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['document_artifact_decoration_id', 'page_number'],
                'uq_document_artifact_decoration_placements_page',
            );
            $table->index(
                ['page_number', 'id'],
                'ix_document_artifact_decoration_placements_page',
            );

            $table->foreign('document_artifact_decoration_id', 'fk_dadp_decoration')
                ->references('id')
                ->on('document_artifact_decorations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_artifact_decoration_placements');
    }
};
