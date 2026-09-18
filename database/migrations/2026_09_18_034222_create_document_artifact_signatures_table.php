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
        Schema::create('document_artifact_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_artifact_id');
            $table->foreignId('esign_provider_response_id')->nullable();
            $table->unsignedSmallInteger('signature_index');

            $table->string('provider_signature_id', 255)->nullable();
            $table->string('field_name', 255)->nullable();
            $table->foreignId('signer_user_id')->nullable();
            $table->string('signer_name', 255)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('reason', 500)->nullable();

            $table->unsignedSmallInteger('certificate_level_code')->nullable();
            $table->boolean('integrity_valid')->nullable();
            $table->boolean('certificate_trusted')->nullable();
            $table->string('signature_format', 100)->nullable();
            $table->boolean('is_last_signature')->nullable();
            $table->boolean('long_term_validation')->nullable();
            $table->string('digest_algorithm', 100)->nullable();
            $table->string('signature_algorithm', 100)->nullable();

            $table->string('timestamp_id', 255)->nullable();
            $table->timestamp('timestamp_at')->nullable();
            $table->string('timestamp_signer_name', 255)->nullable();

            $table->string('verification_conclusion', 30);
            $table->timestamp('verified_at');
            $table->json('safe_metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['document_artifact_id', 'signature_index'],
                'uq_document_artifact_signatures_index'
            );

            $table->index(
                ['signer_user_id', 'signed_at'],
                'ix_document_artifact_signatures_signer'
            );

            $table->index(
                ['verification_conclusion', 'verified_at'],
                'ix_document_artifact_signatures_verification'
            );

            $table->foreign('document_artifact_id', 'fk_das_artifact')
                ->references('id')
                ->on('document_artifacts')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('esign_provider_response_id', 'fk_das_provider_response')
                ->references('id')
                ->on('esign_provider_responses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('signer_user_id', 'fk_das_signer_user')
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
        Schema::dropIfExists('document_artifact_signatures');
    }
};
