<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The course builder (A7.2; instructor web screen 43): each module's source
 * material (scripts, slides, forms, recorded classes), the course's builder
 * settings, and a review status on lessons, so nothing a school builds
 * reaches learners before it's approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            // What it is to the instructor: script, slides, form, recording or captions.
            $table->string('role', 12);
            $table->string('kind', 12);
            $table->string('path');
            $table->string('original_name', 190);
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('status', 20);
            $table->string('failure', 120)->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->json('sections')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->unsignedInteger('words')->nullable();
            $table->longText('transcript')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_module_id', 'position']);
        });

        Schema::table('courses', function (Blueprint $table) {
            // Language, target lesson length and presenter for AI lessons (A7.3).
            $table->json('builder')->nullable()->after('cleared_flags');
        });

        Schema::table('lessons', function (Blueprint $table) {
            // Null for REOS library lessons; school lessons are draft, generating, in_review or approved.
            $table->string('review_status', 12)->nullable()->after('ingest_status');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('builder');
        });
        Schema::dropIfExists('module_sources');
    }
};
