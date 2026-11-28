<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Release approval flow (owner request, 2026-11-27).
 *
 * The boss returns a requested release to the repair queue with short remarks;
 * they stay on the order (visible to the technician) until the release is
 * accepted, at which point the action clears the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->text('approval_remarks')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('approval_remarks');
        });
    }
};
