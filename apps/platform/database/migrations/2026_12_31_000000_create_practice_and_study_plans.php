<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidate start (A8.3, ADR 0032): the Texas diagnostic and topic quizzes as
 * timed tests saved after every answer, and a study plan of tasks per day up
 * to the exam, placed on the person's calendar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            // REOS's own seeded questions, so seeding again updates rather than duplicates.
            $table->string('key', 60)->nullable()->unique();
            $table->boolean('in_diagnostic')->default(false);
        });

        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // diagnostic or topic (A9.1 adds the portion tests and the full simulation).
            $table->string('kind', 20);
            // exam: feedback at the end; practice: after each answer.
            $table->string('mode', 10);
            $table->char('state_code', 2)->nullable();
            $table->string('topic', 30)->nullable();
            // The questions in order, and {question id: choice} and flagged ids as answered.
            $table->json('question_ids');
            $table->json('answers')->nullable();
            $table->json('flags')->nullable();
            $table->timestamp('started_at');
            // Timed tests end here whether or not the app is open.
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedSmallInteger('correct')->nullable();
            $table->unsignedSmallInteger('total');
            $table->unsignedTinyInteger('score')->nullable();
            // {"agency": {"correct": 6, "total": 9}, ...}
            $table->json('by_topic')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind', 'submitted_at']);
        });

        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('exam_on');
            $table->unsignedSmallInteger('minutes_per_day');
            $table->json('weak_topics')->nullable();
            // setup, diagnostic, test, missed, minutes, exam_date, moved
            $table->string('reason', 20);
            // {"2026-11-24": calendar event id}: study blocks placed on the calendar.
            $table->json('calendar_events')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'active']);
        });

        Schema::create('study_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedSmallInteger('position');
            // diagnostic, lesson, practice or review
            $table->string('kind', 12);
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic', 30)->nullable();
            $table->unsignedSmallInteger('minutes');
            // Why it's here, in a word the screen turns into a sentence: next, weak, review, catch_up, baseline.
            $table->string('why', 12);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_tasks');
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('test_attempts');
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'in_diagnostic']);
        });
    }
};
