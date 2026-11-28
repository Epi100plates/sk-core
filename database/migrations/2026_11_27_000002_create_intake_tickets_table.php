<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Submissions from the public site form (owner request, 2026-11-27).
 *
 * `number` is the CMS ticket number and the idempotency key: a repeated HMAC
 * delivery must not create a second row. Nothing is deleted; a submission ends
 * as converted (with the repair order it produced) or rejected (with a reason).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('name', 100)->default('');
            $table->string('phone', 30);
            $table->string('device', 100)->default('');
            $table->text('problem');
            $table->string('source_page', 255)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->string('status', 20)->default('new');
            $table->string('rejection_reason', 255)->nullable();
            $table->dateTime('contacted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->dateTime('converted_at')->nullable();
            $table->unsignedBigInteger('converted_service_id')->nullable();
            $table->string('converted_service_reference', 50)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_tickets');
    }
};
