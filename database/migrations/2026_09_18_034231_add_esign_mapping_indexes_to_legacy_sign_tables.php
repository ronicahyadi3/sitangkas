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
        if (Schema::hasTable('before_signs')) {
            if (! Schema::hasIndex('before_signs', 'ix_before_signs_document_time')) {
                Schema::table('before_signs', function (Blueprint $table) {
                    $table->index(
                        ['id_data', 'created_at', 'id'],
                        'ix_before_signs_document_time'
                    );
                });
            }

            if (! Schema::hasIndex('before_signs', 'ix_before_signs_source_time')) {
                Schema::table('before_signs', function (Blueprint $table) {
                    $table->index(
                        ['src_name', 'created_at', 'id'],
                        'ix_before_signs_source_time'
                    );
                });
            }
        }

        if (Schema::hasTable('after_signs')) {
            if (! Schema::hasIndex('after_signs', 'ix_after_signs_document_time')) {
                Schema::table('after_signs', function (Blueprint $table) {
                    $table->index(
                        ['id_data', 'created_at', 'id'],
                        'ix_after_signs_document_time'
                    );
                });
            }

            if (! Schema::hasIndex('after_signs', 'ix_after_signs_source_time')) {
                Schema::table('after_signs', function (Blueprint $table) {
                    $table->index(
                        ['src_name', 'created_at', 'id'],
                        'ix_after_signs_source_time'
                    );
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('after_signs')) {
            if (Schema::hasIndex('after_signs', 'ix_after_signs_document_time')) {
                Schema::table('after_signs', function (Blueprint $table) {
                    $table->dropIndex('ix_after_signs_document_time');
                });
            }

            if (Schema::hasIndex('after_signs', 'ix_after_signs_source_time')) {
                Schema::table('after_signs', function (Blueprint $table) {
                    $table->dropIndex('ix_after_signs_source_time');
                });
            }
        }

        if (Schema::hasTable('before_signs')) {
            if (Schema::hasIndex('before_signs', 'ix_before_signs_document_time')) {
                Schema::table('before_signs', function (Blueprint $table) {
                    $table->dropIndex('ix_before_signs_document_time');
                });
            }

            if (Schema::hasIndex('before_signs', 'ix_before_signs_source_time')) {
                Schema::table('before_signs', function (Blueprint $table) {
                    $table->dropIndex('ix_before_signs_source_time');
                });
            }
        }
    }
};
