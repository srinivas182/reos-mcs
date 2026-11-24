<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CE reporting to TREC (A11.3, ADR 0045; school admin web screen 51, agent
 * screen 104): a school's report of its courses' completions for a date
 * range, as TREC's upload file or submitted by REOS on the school's
 * behalf, then the TREC reference; each completion in it, reported or
 * rejected with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ce_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->char('state_code', 2);
            $table->date('from_on');
            $table->date('to_on');
            // file (the school uploads TREC's file) or reos (REOS submits, with the school's authority).
            $table->string('method', 8);
            // pending_upload, submitted or reported.
            $table->string('status', 16);
            $table->string('reference', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'created_at']);
        });

        Schema::create('ce_report_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ce_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ce_completion_id')->constrained()->cascadeOnDelete();
            // The licence and course numbers as sent.
            $table->string('licence_number', 40)->nullable();
            $table->string('course_number', 60)->nullable();
            // included, reported or rejected.
            $table->string('status', 10);
            $table->string('reason', 300)->nullable();
            $table->timestamps();
            $table->unique(['ce_report_id', 'ce_completion_id']);
            $table->index(['ce_completion_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ce_report_lines');
        Schema::dropIfExists('ce_reports');
    }
};
