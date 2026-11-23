<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificate OCR (B10.3b, ADR 0043): an uploaded CE or course certificate
 * is read (AWS Textract) into name, course, hours, date and provider for the
 * person to review; saving it records the completion with its certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A certificate read and waiting for the person's review (kept a day).
        Schema::create('certificate_reads', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path', 300);
            $table->string('mime', 60);
            // textract or simulator; null when nothing could read it.
            $table->string('reader', 12)->nullable();
            $table->jsonb('fields')->default('{}');
            // pending, clean or infected (B6.1b's scanner).
            $table->string('scan_status', 10)->default('pending');
            $table->timestamps();
        });

        Schema::table('ce_completions', function (Blueprint $table): void {
            $table->string('certificate_path', 300)->nullable();
            $table->string('read_by', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ce_completions', function (Blueprint $table): void {
            $table->dropColumn(['certificate_path', 'read_by']);
        });
        Schema::dropIfExists('certificate_reads');
    }
};
