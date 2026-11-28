<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CE compliance and sponsorship (A16.2, ADR 0077): courses a brokerage
 * assigns to its agents, the broker's CE reminders, and each sponsorship
 * join and release with its handover, confirmed against TREC's file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'course_id', 'user_id']);
        });

        Schema::create('ce_broker_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('hours_left', 5, 1);
            $table->date('due_on')->nullable();
            $table->timestamp('sent_at');
            $table->index(['organisation_id', 'user_id', 'sent_at']);
        });

        Schema::create('sponsorship_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // join or release.
            $table->string('kind', 8);
            $table->date('effective_on');
            $table->foreignId('handover_to')->nullable()->constrained('users')->nullOnDelete();
            // The open deals handed over: [{uuid, property}].
            $table->json('handed_over')->nullable();
            // recorded, then confirmed when TREC's file shows the change.
            $table->string('status', 10)->default('recorded');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'kind']);
        });

        RowLevelSecurity::protect('course_assignments');
        RowLevelSecurity::protect('ce_broker_reminders');
        RowLevelSecurity::protect('sponsorship_changes');
    }

    public function down(): void
    {
        RowLevelSecurity::release('sponsorship_changes');
        RowLevelSecurity::release('ce_broker_reminders');
        RowLevelSecurity::release('course_assignments');
        Schema::dropIfExists('sponsorship_changes');
        Schema::dropIfExists('ce_broker_reminders');
        Schema::dropIfExists('course_assignments');
    }
};
