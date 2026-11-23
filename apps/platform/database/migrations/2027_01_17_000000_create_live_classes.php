<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live classes (A10.2b, ADR 0040; candidate screen L12, instructor i11 and
 * i12): a live class is a video session with a course, a capacity and, for
 * a CE class, the attendance rules of the course's approval. In class:
 * attendance checks, polls, raised hands, questions with likes and chat.
 * Seat time is counted for every session from joins and leaves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // The approval that makes it a CE class (a live delivery approval in force when scheduled).
            $table->foreignId('course_approval_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('capacity');
            // Copied from the approval when scheduled; null for a class without CE.
            $table->unsignedSmallInteger('checkin_minutes')->nullable();
            $table->unsignedTinyInteger('presence_percent')->nullable();
            $table->timestamp('reminded_hour_at')->nullable();
            $table->timestamps();
            $table->index('course_id');
        });

        Schema::table('video_session_participants', function (Blueprint $table): void {
            $table->timestamp('hand_raised_at')->nullable();
            $table->boolean('on_stage')->default(false);
            // Seconds in the room between the booked start and end (seat time).
            $table->unsignedInteger('seconds_present')->default(0);
        });

        Schema::create('class_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->cascadeOnDelete();
            // Null: sent automatically on the approval's interval.
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['video_session_id', 'opens_at']);
        });

        Schema::create('class_check_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('answered_at');
            $table->unique(['class_check_id', 'user_id']);
        });

        Schema::create('class_polls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->cascadeOnDelete();
            $table->string('question', 200);
            $table->json('options');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index('video_session_id');
        });

        Schema::create('class_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('option');
            $table->timestamps();
            $table->unique(['class_poll_id', 'user_id']);
        });

        Schema::create('class_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->unsignedInteger('likes')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->index('video_session_id');
        });

        Schema::create('class_question_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['class_question_id', 'user_id']);
        });

        Schema::create('class_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('body', 500);
            $table->timestamp('created_at');
            $table->index(['video_session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_messages');
        Schema::dropIfExists('class_question_likes');
        Schema::dropIfExists('class_questions');
        Schema::dropIfExists('class_poll_votes');
        Schema::dropIfExists('class_polls');
        Schema::dropIfExists('class_check_answers');
        Schema::dropIfExists('class_checks');
        Schema::table('video_session_participants', function (Blueprint $table): void {
            $table->dropColumn(['hand_raised_at', 'on_stage', 'seconds_present']);
        });
        Schema::dropIfExists('live_classes');
    }
};
