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
        Schema::create('esign_attempt_signature_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esign_attempt_id');
            $table->unsignedSmallInteger('property_index')->default(0);
            $table->string('display_mode', 20);

            $table->unsignedSmallInteger('page_number')->nullable();
            $table->decimal('origin_x', 14, 4)->nullable();
            $table->decimal('origin_y', 14, 4)->nullable();
            $table->decimal('width', 14, 4)->nullable();
            $table->decimal('height', 14, 4)->nullable();
            $table->string('coordinate_origin', 20)->nullable();
            $table->smallInteger('page_rotation')->nullable();

            $table->string('location', 255)->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('contact_info', 255)->nullable();
            $table->string('visual_type', 30)->default('none');
            $table->string('visual_storage_disk', 50)->nullable();
            $table->string('visual_file_path', 500)->nullable();
            $table->char('visual_sha256', 64)->nullable();
            $table->string('provider_schema_version', 30)->default('bsre-v2.2.0');
            $table->json('safe_provider_properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['esign_attempt_id', 'property_index'],
                'uq_esign_attempt_signature_properties_index'
            );

            $table->index(
                ['display_mode', 'page_number'],
                'ix_esign_attempt_signature_properties_display'
            );

            $table->foreign('esign_attempt_id', 'fk_easp_attempt')
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
        Schema::dropIfExists('esign_attempt_signature_properties');
    }
};
