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
        Schema::create('esign_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_uuid')->unique();
            $table->string('migration_type', 50);
            $table->string('source_system', 50)->default('sitangkas_legacy');
            $table->string('status', 30)->default('pending');

            $table->unsignedBigInteger('high_watermark')->nullable();
            $table->unsignedBigInteger('last_checkpoint')->nullable();
            $table->unsignedBigInteger('total_items')->default(0);
            $table->unsignedBigInteger('processed_items')->default(0);
            $table->unsignedBigInteger('succeeded_items')->default(0);
            $table->unsignedBigInteger('failed_items')->default(0);
            $table->unsignedBigInteger('needs_review_items')->default(0);

            $table->uuid('lease_token')->nullable();
            $table->string('lease_owner', 100)->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->json('parameters')->nullable();
            $table->text('error_summary')->nullable();
            $table->foreignId('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(
                ['migration_type', 'status', 'created_at'],
                'ix_esign_migration_runs_type_status'
            );

            $table->index(
                ['status', 'leased_until'],
                'ix_esign_migration_runs_lease'
            );

            $table->foreign('created_by_user_id', 'fk_emr_creator')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esign_migration_runs');
    }
};
