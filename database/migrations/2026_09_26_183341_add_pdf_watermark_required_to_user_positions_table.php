<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_positions', function (Blueprint $table): void {
            $table->boolean('pdf_watermark_required')
                ->default(false)
                ->after('is_active')
                ->comment('Seluruh delivery PDF untuk posisi ini wajib memakai derivative watermark');
        });
    }

    public function down(): void
    {
        Schema::table('user_positions', function (Blueprint $table): void {
            $table->dropColumn('pdf_watermark_required');
        });
    }
};
