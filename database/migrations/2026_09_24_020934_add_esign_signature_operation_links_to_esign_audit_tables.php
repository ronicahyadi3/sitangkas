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
        Schema::table('esign_provider_responses', function (Blueprint $table) {
            $table->foreignId('esign_signature_operation_id')
                ->nullable()
                ->after('esign_attempt_id');

            $table->foreign('esign_signature_operation_id', 'fk_epr_signature_operation')
                ->references('id')
                ->on('esign_signature_operations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::table('esign_attempt_events', function (Blueprint $table) {
            $table->foreignId('esign_signature_operation_id')
                ->nullable()
                ->after('esign_provider_response_id');

            $table->foreign('esign_signature_operation_id', 'fk_eae_signature_operation')
                ->references('id')
                ->on('esign_signature_operations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('esign_attempt_events', function (Blueprint $table) {
            $table->dropForeign('fk_eae_signature_operation');
            $table->dropColumn('esign_signature_operation_id');
        });

        Schema::table('esign_provider_responses', function (Blueprint $table) {
            $table->dropForeign('fk_epr_signature_operation');
            $table->dropColumn('esign_signature_operation_id');
        });
    }
};
