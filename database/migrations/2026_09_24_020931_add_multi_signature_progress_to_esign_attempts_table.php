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
        Schema::table('esign_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('planned_signature_count')
                ->default(1)
                ->after('attempt_number');
            $table->unsignedSmallInteger('completed_signature_count')
                ->default(0)
                ->after('planned_signature_count');
            $table->unsignedSmallInteger('current_signature_index')
                ->default(0)
                ->after('completed_signature_count');

            $table->index(
                ['status', 'completed_signature_count', 'updated_at'],
                'ix_esign_attempts_status_progress',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('esign_attempts', function (Blueprint $table) {
            $table->dropIndex('ix_esign_attempts_status_progress');
            $table->dropColumn([
                'planned_signature_count',
                'completed_signature_count',
                'current_signature_index',
            ]);
        });
    }
};
