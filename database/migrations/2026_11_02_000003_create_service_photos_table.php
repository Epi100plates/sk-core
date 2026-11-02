<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo metadata (section 6.1). The files themselves live on the private local
 * disk and are served by a policy protected controller (defect D-52).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('disk', 20)->default('local');
            $table->string('path', 255);
            $table->string('thumb_path', 255);
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('checksum', 64);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index('service_id');
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_photos');
    }
};
