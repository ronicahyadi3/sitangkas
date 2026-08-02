<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_position_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_position_id')
                ->constrained('user_positions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            /*
             * appointment_sk : SK penetapan awal
             * amendment_sk   : perubahan penetapan
             * revocation_sk  : pencabutan
             * supporting     : dokumen pendukung lain
             */
            $table->string('document_type', 30)
                ->default('appointment_sk');

            $table->string('document_number', 150)->nullable();
            $table->date('document_date')->nullable();
            $table->string('issued_by', 200)->nullable();

            /* Periode berlaku dokumen, bila tercantum pada SK. */
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            /* Metadata penyimpanan file. */
            $table->string('storage_disk', 50)->default('private');
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('stored_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('file_sha256', 64)->nullable();

            /*
             * Satu posisi dapat memiliki beberapa versi / jenis dokumen.
             * is_primary menandai dokumen utama yang ditampilkan aplikasi.
             */
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_primary')->default(false);

            /* draft, verified, rejected, archived */
            $table->string('verification_status', 20)->default('draft');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable();
            $table->text('verification_notes')->nullable();

            $table->timestamp('uploaded_at')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable();

            $table->string('source_system', 50)->default('manual');
            $table->string('external_id', 100)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();

            $table->foreignId('created_by_user_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /* Mencegah file yang sama ditempel dua kali pada posisi yang sama. */
            $table->unique(
                ['user_position_id', 'file_sha256'],
                'uq_user_position_documents_file_hash'
            );

            $table->unique(
                ['source_system', 'external_id'],
                'uq_user_position_documents_source_external'
            );

            $table->index(
                ['user_position_id', 'is_primary', 'verification_status'],
                'ix_user_position_documents_primary_status'
            );

            $table->index(
                ['document_number', 'document_date'],
                'ix_user_position_documents_number_date'
            );

            $table->index(
                ['effective_until', 'verification_status'],
                'ix_user_position_documents_expiry'
            );

            $table->foreign('verified_by_user_id', 'fk_user_position_documents_verified_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('uploaded_by_user_id', 'fk_user_position_documents_uploaded_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('created_by_user_id', 'fk_user_position_documents_created_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('updated_by_user_id', 'fk_user_position_documents_updated_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreign('deleted_by_user_id', 'fk_user_position_documents_deleted_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE user_position_documents
            ADD CONSTRAINT chk_user_position_documents_period
            CHECK (
                effective_until IS NULL
                OR effective_from IS NULL
                OR effective_until >= effective_from
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_position_documents');
    }
};
