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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('remember_token_expires_at')->nullable();

            $table->timestamp('mfa_enabled_at')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->timestamp('mfa_last_used_at')->nullable();
            $table->text('mfa_secret')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->timestamp('mfa_recovery_codes_generated_at')->nullable();
            $table->text('mfa_pending_secret')->nullable();
            $table->timestamp('mfa_pending_secret_created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'remember_token_expires_at',
                'mfa_enabled_at',
                'mfa_confirmed_at',
                'mfa_last_used_at',
                'mfa_secret',
                'mfa_recovery_codes',
                'mfa_recovery_codes_generated_at',
                'mfa_pending_secret',
                'mfa_pending_secret_created_at',
            ]);
        });
    }
};
