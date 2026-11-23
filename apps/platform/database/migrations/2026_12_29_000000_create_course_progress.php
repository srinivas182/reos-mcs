<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The course player's seat time and progress (A8.2, ADR 0030): what counted
 * per lesson and course, per day (for the approval's daily maximum), and a
 * log of every counted or uncounted stretch with why, kept for the regulator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('counted_seconds')->default(0);
            $table->unsignedInteger('required_seconds')->default(0);
            // Where the learner is in the video (seconds), to continue from.
            $table->unsignedInteger('position')->default(0);
            // {"301": {"correct": true, "at": "…"}, "presence:2": {…}}: check-ins answered.
            $table->json('checkins')->nullable();
            $table->string('session', 40)->nullable();
            $table->timestamp('last_beat_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('course_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('counted_seconds')->default(0);
            $table->unsignedSmallInteger('lessons_done')->default(0);
            $table->unsignedSmallInteger('lessons_total')->default(0);
            $table->unsignedTinyInteger('percent')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('seat_time_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // The learner's own calendar day.
            $table->date('day');
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'day']);
        });

        Schema::create('seat_time_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('counted')->default(0);
            $table->unsignedInteger('uncounted')->default(0);
            // Why time didn't count: paused, hidden, checkin_due, daily_max, gap, replayed.
            $table->string('reason', 20)->nullable();
            // web, app or offline.
            $table->string('source', 10);
            // An offline stretch's own id, so the same stretch is never counted twice.
            $table->string('segment', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'lesson_id', 'occurred_at']);
            $table->unique(['user_id', 'segment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_time_events');
        Schema::dropIfExists('seat_time_days');
        Schema::dropIfExists('course_progress');
        Schema::dropIfExists('lesson_progress');
    }
};
