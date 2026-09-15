<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document', function (Blueprint $table) {
            $table->index(
                ['uploaded_by', 'deleted_at'],
                'ix_document_uploader_active'
            );

            $table->index(
                ['users_to', 'deleted_at'],
                'ix_document_recipient_active'
            );
        });

        Schema::table('document_process', function (Blueprint $table) {
            $table->index(
                ['id_user', 'created_at'],
                'ix_document_process_actor_time'
            );
        });
    }

    public function down(): void
    {
        Schema::table('document_process', function (Blueprint $table) {
            $table->dropIndex('ix_document_process_actor_time');
        });

        Schema::table('document', function (Blueprint $table) {
            $table->dropIndex('ix_document_uploader_active');
            $table->dropIndex('ix_document_recipient_active');
        });
    }
};
