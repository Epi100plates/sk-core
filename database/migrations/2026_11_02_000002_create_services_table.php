<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service orders (section 6.1). One order = one row, the customer is identified
 * by phone number only (decision D3), and `confirmation_issued_at` carries the
 * re-purposed "confirmation printed" flag (decision D6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('reference', 20)->unique();
            $table->string('phone', 30);
            $table->string('phone_normalized', 20)->nullable();
            $table->string('device_model', 100);
            $table->string('serial_number', 100)->nullable();
            $table->text('issue');
            $table->text('device_password')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_express')->default(false);
            $table->dateTime('planned_at');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('seal_number', 50)->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('accepted_by')->constrained('users');
            $table->dateTime('accepted_at');
            $table->dateTime('confirmation_issued_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancellation_reason', 255)->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('planned_at');
            $table->index('released_at');
            $table->index('seal_number');
            $table->index('phone_normalized');
            $table->index(['status', 'planned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
