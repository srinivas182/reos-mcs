<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson review and the question bank (A8.1, ADR 0031): each AI lesson
 * version keeps its line-by-line review (approved, edited, suggestion,
 * comments) and whether it was sent back; questions approved from a lesson
 * join the owning school's bank with a source and an exam-outline mapping;
 * learners can dispute any question.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_versions', function (Blueprint $table) {
            // {"3": {"status": "approved|changed|to_review", "by": 7, "at": "…", "suggestion": {"en": "…", "es": "…"}, "comments": […]}}
            $table->json('reviews')->nullable()->after('flags');
            // in_review, sent_back or approved (the line-by-line review, A8.1).
            $table->string('review_state', 12)->nullable()->after('reviews');
            $table->text('review_note')->nullable()->after('review_state');
            $table->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        Schema::table('review_items', function (Blueprint $table) {
            // easy, medium or hard.
            $table->string('difficulty', 8)->nullable()->after('topic');
            // The exam portion it maps to (national or state); with the topic, the outline mapping.
            $table->string('portion', 10)->nullable()->after('difficulty');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            // A school's own questions (from its AI lessons); null for REOS's bank.
            $table->foreignId('organisation_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->after('organisation_id')->constrained()->nullOnDelete();
            $table->foreignId('review_item_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->string('difficulty', 8)->nullable()->after('topic');
            $table->string('portion', 10)->nullable()->after('difficulty');
            $table->timestamp('retired_at')->nullable();
        });

        Schema::create('question_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 20);
            $table->text('details')->nullable();
            // open, kept or corrected.
            $table->string('status', 10)->default('open');
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['quiz_question_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_disputes');
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('review_item_id');
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropColumn(['difficulty', 'portion', 'retired_at']);
        });
        Schema::table('review_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['difficulty', 'portion', 'reviewed_at']);
        });
        Schema::table('lesson_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviews', 'review_state', 'review_note', 'reviewed_at']);
        });
    }
};
