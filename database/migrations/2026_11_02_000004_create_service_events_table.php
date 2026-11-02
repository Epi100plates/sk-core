<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append only audit trail (section 6.1, F-38/F-50). One event per real change,
 * with the field diff, the actor and the request context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('type', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('changes')->nullable();
            $table->text('description')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['service_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_events');
    }
};
