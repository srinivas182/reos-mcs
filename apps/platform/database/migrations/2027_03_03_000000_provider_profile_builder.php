<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The provider profile (B15.3, ADR 0075, provider screen 7): photo and
 * logo, a bio in English and Spanish, services, specialities, a
 * response-time goal, free resources and expert sessions. What agents see
 * is the last version the compliance engine passed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table): void {
            $table->string('photo_path', 300)->nullable();
            $table->string('logo_path', 300)->nullable();
            $table->text('bio')->nullable();
            $table->text('bio_es')->nullable();
            $table->json('services')->nullable();
            $table->json('specialities')->nullable();
            $table->unsignedSmallInteger('response_hours')->nullable();
            // Free resources: [{title, url}]; expert sessions: [{title, minutes}].
            $table->json('resources')->nullable();
            $table->json('sessions')->nullable();
            // The compliance engine's issues with the last save, and what agents see (the last version that passed).
            $table->json('profile_issues')->nullable();
            $table->json('published')->nullable();
            $table->timestamp('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table): void {
            $table->dropColumn(['photo_path', 'logo_path', 'bio', 'bio_es', 'services', 'specialities', 'response_hours', 'resources', 'sessions', 'profile_issues', 'published', 'published_at']);
        });
    }
};
