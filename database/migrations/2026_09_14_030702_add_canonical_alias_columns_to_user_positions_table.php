<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_positions', function (Blueprint $table) {
            $table->boolean('is_canonical')
                ->default(true)
                ->after('is_active')
                ->comment('Posisi utama untuk satu konteks; alias legacy bernilai false');

            $table->foreignId('canonical_user_position_id')
                ->nullable()
                ->after('is_canonical')
                ->comment('Posisi canonical yang mewakili alias legacy');

            $table->string('legacy_duplicate_reason', 100)
                ->nullable()
                ->after('canonical_user_position_id')
                ->comment('Alasan row legacy dipertahankan sebagai alias');

            $table->unsignedTinyInteger('canonical_context_marker')
                ->nullable()
                ->storedAs('IF(is_canonical = 1, 1, NULL)')
                ->after('legacy_duplicate_reason')
                ->comment('Marker internal untuk unique constraint posisi canonical');

            $table->dropUnique('uq_user_positions_context');

            $table->unique(
                [
                    'user_id',
                    'jabatan_id',
                    'instansi_id',
                    'unit_kerja_id',
                    'canonical_context_marker',
                ],
                'uq_user_positions_canonical_context'
            );

            $table->index(
                'canonical_user_position_id',
                'ix_user_positions_canonical_reference'
            );

            $table->index(
                ['user_id', 'is_canonical', 'is_active', 'deleted_at'],
                'ix_user_positions_user_canonical_active'
            );

            $table->foreign('canonical_user_position_id', 'fk_user_positions_canonical')
                ->references('id')
                ->on('user_positions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        /*
         * Restore the old constraint first. After aliases are imported this
         * intentionally fails without partially removing the alias schema.
         */
        Schema::table('user_positions', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'jabatan_id', 'instansi_id', 'unit_kerja_id'],
                'uq_user_positions_context'
            );
        });

        Schema::table('user_positions', function (Blueprint $table) {
            $table->dropForeign('fk_user_positions_canonical');
            $table->dropUnique('uq_user_positions_canonical_context');
            $table->dropIndex('ix_user_positions_canonical_reference');
            $table->dropIndex('ix_user_positions_user_canonical_active');
            $table->dropColumn([
                'canonical_context_marker',
                'legacy_duplicate_reason',
                'canonical_user_position_id',
                'is_canonical',
            ]);
        });
    }
};
