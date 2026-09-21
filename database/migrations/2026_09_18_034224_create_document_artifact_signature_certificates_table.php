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
        Schema::create('document_artifact_signature_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_artifact_signature_id');
            $table->unsignedSmallInteger('chain_index');
            $table->string('provider_certificate_id', 255)->nullable();
            $table->string('signature_algorithm', 100)->nullable();
            $table->dateTime('not_before_at')->nullable();
            $table->dateTime('not_after_at')->nullable();
            $table->json('key_usages')->nullable();
            $table->string('issuer_name', 500)->nullable();
            $table->string('serial_number', 255)->nullable();
            $table->string('common_name', 255)->nullable();
            $table->char('certificate_sha256', 64)->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['document_artifact_signature_id', 'chain_index'],
                'uq_document_artifact_signature_certificates_chain'
            );

            $table->index(
                ['serial_number', 'not_after_at'],
                'ix_document_artifact_signature_certificates_serial'
            );

            $table->index(
                ['not_after_at', 'not_before_at'],
                'ix_document_artifact_signature_certificates_validity'
            );

            $table->foreign('document_artifact_signature_id', 'fk_dasc_signature')
                ->references('id')
                ->on('document_artifact_signatures')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_artifact_signature_certificates');
    }
};
