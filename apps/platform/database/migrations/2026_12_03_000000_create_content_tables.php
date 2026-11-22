<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Content and the knowledge base (A5.3, ADR 0020).
 *
 * - `courses` › `course_modules` › `lessons`: tagged by state (`states`, null
 *   for every state). CE and qualifying courses keep their approval number
 *   per state; none shows its label without one (screen 63).
 * - `lesson_media`: videos, slides and documents uploaded straight to
 *   storage, scanned, then processed (video on Mux, slides read for text).
 * - `kb_passages`: every lesson's text, slide text and video transcript in
 *   short passages with where they came from (heading, page or second),
 *   searchable by keyword (PostgreSQL full text, English or Spanish) and by meaning
 *   (Titan Text Embeddings V2, 1,024 dimensions, in pgvector).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            // qualifying, ce, post_licence, test_prep or elective.
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->json('states')->nullable();
            $table->decimal('hours', 5, 1)->nullable();
            // State => {number, expires_on}: the regulator's approval (CE and qualifying).
            $table->json('approvals')->nullable();
            // draft, in_review, live or hidden.
            $table->string('status', 20)->default('draft');
            $table->string('status_note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
        });

        Schema::create('course_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->unsignedSmallInteger('position')->default(0);
            // en or es.
            $table->string('language', 5)->default('en');
            $table->longText('body')->nullable();
            $table->unsignedSmallInteger('minutes')->nullable();
            // pending, processing, ready or failed: the knowledge base copy.
            $table->string('ingest_status', 20)->default('pending');
            $table->timestamp('ingested_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lesson_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            // video, slides or document.
            $table->string('kind', 20);
            $table->string('path', 300);
            $table->string('original_name', 200);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            // awaiting_upload, processing, ready, rejected or failed.
            $table->string('status', 20)->default('awaiting_upload');
            $table->string('failure', 300)->nullable();
            $table->string('mux_asset_id', 100)->nullable()->index();
            $table->string('mux_playback_id', 100)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('pages')->nullable();
            // Captions as WebVTT (a captions file, or the video host's generated captions).
            $table->longText('transcript')->nullable();
            // Slides and documents: the words on each page, read once when processed.
            $table->json('sections')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kb_passages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_media_id')->nullable()->constrained('lesson_media')->cascadeOnDelete();
            $table->json('states')->nullable();
            $table->string('language', 5);
            // text, slide or transcript.
            $table->string('kind', 20);
            $table->string('heading', 200)->nullable();
            $table->unsignedSmallInteger('page')->nullable();
            $table->unsignedInteger('seconds')->nullable();
            $table->unsignedSmallInteger('position');
            $table->text('content');
            $table->char('content_hash', 64);
            $table->timestamp('embedded_at')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Keyword search in the passage's own language (English or Spanish stemming).
            DB::statement("ALTER TABLE kb_passages ADD COLUMN search tsvector GENERATED ALWAYS AS (to_tsvector(CASE WHEN language = 'es' THEN 'spanish'::regconfig ELSE 'english'::regconfig END, content)) STORED");
            DB::statement('CREATE INDEX kb_passages_search ON kb_passages USING gin (search)');

            if (self::vectors()) {
                DB::statement('ALTER TABLE kb_passages ADD COLUMN embedding vector(1024)');
                DB::statement('CREATE INDEX kb_passages_embedding ON kb_passages USING hnsw (embedding vector_cosine_ops)');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_passages');
        Schema::dropIfExists('lesson_media');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('courses');
    }

    /** pgvector is installed by the database's owner (docker/postgres/init.sql, CI, Aurora parameter setup). */
    private static function vectors(): bool
    {
        return DB::selectOne("SELECT 1 AS present FROM pg_extension WHERE extname = 'vector'") !== null;
    }
};
