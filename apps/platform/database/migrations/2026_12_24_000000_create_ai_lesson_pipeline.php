<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI lesson pipeline (A7.3, ADR 0026): each generation of a lesson is a
 * version (its script line by line in English and Spanish, each line citing
 * its source, and its legal flags), rendered once per language by the
 * avatar vendor; the check-ins, questions and flashcards wait for review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            // drafting, flagged (a legal line stops rendering), rendering, ready, failed.
            $table->string('status', 12);
            // The module passages the lesson was written from (each line cites one).
            $table->json('sources');
            $table->json('lines')->nullable();
            $table->json('flags')->nullable();
            $table->string('presenter', 40);
            $table->string('vendor', 20);
            $table->unsignedInteger('credits')->default(0);
            $table->string('failure', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lesson_id', 'version']);
        });

        Schema::create('lesson_renders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_version_id')->constrained()->cascadeOnDelete();
            $table->string('language', 2);
            $table->string('vendor', 20);
            $table->string('external_id', 120)->nullable();
            // queued, rendering, ready, failed.
            $table->string('status', 12);
            $table->unsignedInteger('seconds')->nullable();
            $table->foreignId('lesson_media_id')->nullable()->constrained('lesson_media')->nullOnDelete();
            $table->boolean('simulated')->default(false);
            $table->string('failure', 200)->nullable();
            $table->unsignedSmallInteger('checks')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['lesson_version_id', 'language']);
            $table->index(['status', 'checked_at']);
        });

        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_version_id')->constrained()->cascadeOnDelete();
            // checkin, question or flashcard.
            $table->string('kind', 10);
            $table->unsignedSmallInteger('after_line')->nullable();
            $table->string('topic', 30)->nullable();
            $table->json('content');
            // to_review, approved, rejected (A8.1).
            $table->string('status', 12)->default('to_review');
            $table->timestamps();

            $table->index(['lesson_id', 'kind', 'status']);
        });

        Schema::table('lesson_media', function (Blueprint $table) {
            // The spoken language of a video (AI lessons have one per language, A7.3); null for others.
            $table->string('language', 2)->nullable()->after('kind');
            // An AI lesson video's captions in each language, timed to that video (WebVTT by language).
            $table->json('captions')->nullable()->after('transcript');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            $table->dropColumn(['language', 'captions']);
        });
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('lesson_renders');
        Schema::dropIfExists('lesson_versions');
    }
};
