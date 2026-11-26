<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public status link returned by the CMS (decision D19/0019). The app stores the
 * address so the order card can show it, and the timestamp of the last
 * successful sync so the command knows what still needs sending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('client_status_url', 255)->nullable()->after('seal_number');
            $table->timestamp('client_status_synced_at')->nullable()->after('client_status_url');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['client_status_url', 'client_status_synced_at']);
        });
    }
};
