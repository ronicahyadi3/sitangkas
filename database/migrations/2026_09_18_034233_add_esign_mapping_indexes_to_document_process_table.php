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
        if (! Schema::hasTable('document_process')) {
            return;
        }

        Schema::table('document_process', function (Blueprint $table) {
            $table->index(
                ['id_dokumen', 'action', 'created_at', 'id'],
                'ix_document_process_document_action_time'
            );

            $table->index(
                ['action', 'created_at', 'id'],
                'ix_document_process_action_time'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('document_process')) {
            return;
        }

        Schema::table('document_process', function (Blueprint $table) {
            $table->dropIndex('ix_document_process_document_action_time');
            $table->dropIndex('ix_document_process_action_time');
        });
    }
};
