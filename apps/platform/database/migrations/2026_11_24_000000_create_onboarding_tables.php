<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding (A3.3): the states REOS serves and their waitlists, the free
 * sample quiz and its attempts, and what a person chose while getting
 * started. A5.1 adds licences and the full state rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table): void {
            $table->char('code', 2)->primary();
            $table->string('name', 60);
            // live, in_preparation, waitlist, not_planned (web journey screen 61).
            $table->string('status', 20)->default('waitlist')->index();
            $table->string('regulator', 20)->nullable();
            $table->string('exam_vendor', 40)->nullable();
            $table->unsignedSmallInteger('qualifying_hours')->nullable();
            $table->unsignedSmallInteger('ce_hours')->nullable();
            $table->timestamps();
        });

        Schema::create('state_waitlist', function (Blueprint $table): void {
            $table->id();
            $table->char('state_code', 2);
            $table->foreign('state_code')->references('code')->on('states')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Before an account exists, the phone or browser that joined.
            $table->uuid('device_id')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['state_code', 'device_id']);
            $table->unique(['state_code', 'user_id']);
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->char('state_code', 2)->nullable();
            $table->foreign('state_code')->references('code')->on('states')->nullOnDelete();
            $table->string('topic', 30)->index();
            // { "en": {question, options[4], explanation}, "es": {...} }
            $table->json('content');
            $table->unsignedTinyInteger('correct_option');
            $table->string('source');
            // Sample questions until the real Texas bank arrives (A5.3).
            $table->boolean('is_sample')->default(true);
            $table->boolean('in_free_quiz')->default(true);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('free_quiz');
            $table->char('state_code', 2)->nullable();
            // [{question_id, choice, correct}]
            $table->json('answers');
            $table->unsignedTinyInteger('correct');
            $table->unsignedTinyInteger('total');
            $table->timestamp('completed_at');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            // become_agent, licensed_agent, broker, instructor, provider (journey screen 1).
            $table->string('onboarding_path', 20)->nullable();
            $table->char('target_state', 2)->nullable();
            $table->date('exam_month')->nullable();
            $table->unsignedSmallInteger('minutes_per_day')->nullable();
            $table->time('reminder_time')->nullable();
            $table->timestamp('onboarded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['onboarding_path', 'target_state', 'exam_month', 'minutes_per_day', 'reminder_time', 'onboarded_at']);
        });
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('state_waitlist');
        Schema::dropIfExists('states');
    }
};
