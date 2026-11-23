<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flashcards and the speed round (A9.2d, ADR 0035): each candidate's deck
 * with its FSRS schedule, every review (synced from the phone by its own
 * id, so a review counts once), and speed rounds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flashcards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->foreignId('review_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quiz_question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic', 30)->nullable();
            $table->json('content');
            $table->float('stability')->nullable();
            $table->float('difficulty')->nullable();
            $table->unsignedInteger('reps')->default(0);
            $table->unsignedInteger('lapses')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'review_item_id']);
            $table->unique(['user_id', 'quiz_question_id']);
            $table->index(['user_id', 'due_at']);
        });

        Schema::create('flashcard_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('flashcard_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->unsignedTinyInteger('rating');
            $table->timestamp('reviewed_at');
            $table->unsignedSmallInteger('interval_days');
            $table->timestamps();
        });

        Schema::create('speed_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('statements');
            $table->json('answers')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('answered')->nullable();
            $table->json('by_topic')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speed_rounds');
        Schema::dropIfExists('flashcard_reviews');
        Schema::dropIfExists('flashcards');
    }
};
