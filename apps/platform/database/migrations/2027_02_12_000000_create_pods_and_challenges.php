<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accountability pods and brokerage challenges (A13.2, ADR 0054; agent
 * screen 131):
 *
 * - `pod_requests`: an agent asking to join a pod, matched inside their
 *   brokerage (or among independent agents) by state, goal, language and
 *   the part-time flag;
 * - `pods` and `pod_members`: five agents with the same goal, a weekly
 *   15-minute call and what each one shares;
 * - `pod_goals`: each member's goal for the week;
 * - `brokerage_challenges` and `challenge_entries`: opt-in challenges with
 *   leaderboards (total and most improved).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pod_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The agent's brokerage; null for independent agents.
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('state_code', 2);
            $table->string('goal', 8);
            $table->string('language', 2);
            $table->boolean('part_time')->default(false);
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['state_code', 'goal', 'language', 'part_time', 'matched_at']);
        });

        Schema::create('pods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 80);
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('state_code', 2);
            $table->string('goal', 8);
            $table->string('language', 2);
            $table->boolean('part_time')->default(false);
            // The weekly call: day, time and time zone.
            $table->string('call_day', 3)->default('mon');
            $table->string('call_time', 5)->default('08:30');
            $table->string('timezone', 40)->default('America/Chicago');
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pod_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pod_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Which scorecard numbers the member shares inside the pod.
            $table->json('share');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'left_at']);
            $table->index(['pod_id', 'left_at']);
        });

        Schema::create('pod_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pod_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_of');
            $table->string('text', 200);
            $table->timestamps();
            $table->unique(['pod_id', 'user_id', 'week_of']);
        });

        Schema::create('brokerage_challenges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            // conversations, appointments, ce_hours or posts
            $table->string('metric', 16);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organisation_id', 'ends_on']);
        });

        Schema::create('challenge_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('brokerage_challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The same metric in the same length of time just before (for "most improved").
            $table->decimal('baseline', 8, 1)->default(0);
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['brokerage_challenge_id', 'user_id']);
        });

        // The pod's weekly call in the video rooms.
        Schema::table('video_sessions', function (Blueprint $table): void {
            $table->foreignId('pod_id')->nullable()->constrained()->nullOnDelete();
        });

        RowLevelSecurity::protect('brokerage_challenges');
        // Independent agents' pods have no brokerage (organisation_id null).
        RowLevelSecurity::protect('pod_requests', allowPersonal: true);
        RowLevelSecurity::protect('pods', allowPersonal: true);
    }

    public function down(): void
    {
        Schema::table('video_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pod_id');
        });
        RowLevelSecurity::release('pods');
        RowLevelSecurity::release('pod_requests');
        RowLevelSecurity::release('brokerage_challenges');
        Schema::dropIfExists('challenge_entries');
        Schema::dropIfExists('brokerage_challenges');
        Schema::dropIfExists('pod_goals');
        Schema::dropIfExists('pod_members');
        Schema::dropIfExists('pods');
        Schema::dropIfExists('pod_requests');
    }
};
