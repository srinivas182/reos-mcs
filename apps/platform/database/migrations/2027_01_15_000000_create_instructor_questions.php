<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions for a course's instructors (A10.1b, ADR 0039; instructor
 * screens i09 and i10): what the course content couldn't answer, sent on
 * from Ask, and the instructor's answer. Who may see one is checked in the
 * app (the asker, and the course's instructors), so it has no
 * organisation_id: the student and the school are in different tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_questions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // The lesson the closest answer came from, if any (where an answer added to knowledge is filed).
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ask_thread_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            // What the course content said (or that it didn't cover it).
            $table->text('content_answer')->nullable();
            $table->string('language', 5);
            // waiting or answered
            $table->string('status', 10)->default('waiting');
            $table->text('answer')->nullable();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->foreignId('kb_passage_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('flashcard')->default(false);
            $table->timestamps();
            $table->index(['course_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_questions');
    }
};
