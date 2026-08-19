<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index(
                ['account_type', 'deleted_at'],
                'ix_users_account_type_deleted'
            );
        });

        Schema::table('user_positions', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'jabatan_id', 'instansi_id', 'unit_kerja_id', 'is_active', 'deleted_at'],
                'ix_user_positions_user_context_active_deleted'
            );

            $table->index(
                ['instansi_id', 'unit_kerja_id', 'jabatan_id', 'is_active', 'deleted_at', 'user_id'],
                'ix_user_positions_scope_active_deleted_user'
            );
        });
    }

    public function down(): void
    {
        Schema::table('user_positions', function (Blueprint $table): void {
            $table->dropIndex('ix_user_positions_scope_active_deleted_user');
            $table->dropIndex('ix_user_positions_user_context_active_deleted');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('ix_users_account_type_deleted');
        });
    }
};
