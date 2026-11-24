<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The content calendar and celebrations (B12.3, ADR 0052):
 *
 * - `social_posts.planned_on`: a draft placed on a day of the calendar;
 * - `celebrations`: a milestone (just listed, under contract, just sold,
 *   first closing, CE complete, license renewed, anniversary) made into
 *   a free post, shown at most once a day; the rest wait in My posts;
 * - `agent_profiles.photo_path`: the agent's profile photo for their
 *   designs (screen 162), with its quality check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->date('planned_on')->nullable();
            $table->index(['user_id', 'planned_on']);
        });

        Schema::create('celebrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('milestone', 20);
            // What it celebrates, so it's made once: listing:<uuid>, contract:<deal>, closing:<deal>, ce:<licence>:<due>, renewal:<id>, anniversary:<year>.
            $table->string('subject', 120);
            $table->string('label', 160)->nullable();
            $table->foreignId('studio_generation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('shown_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'subject']);
            $table->index(['user_id', 'shown_at']);
        });

        Schema::table('agent_profiles', function (Blueprint $table): void {
            $table->string('photo_path')->nullable();
            // {width, height, brightness, issues: list<string>}
            $table->json('photo_check')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table): void {
            $table->dropColumn(['photo_path', 'photo_check']);
        });
        Schema::dropIfExists('celebrations');
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'planned_on']);
            $table->dropColumn('planned_on');
        });
    }
};
