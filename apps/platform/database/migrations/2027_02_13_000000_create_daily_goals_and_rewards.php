<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily goals, points and rewards (A13.3a, ADR 0055; agent screens 111 and 112):
 *
 * - `daily_goal_days`: each agent's day — the goals, what was done, the
 *   points earned and whether every goal was met (the streak);
 * - `daily_goal_settings`: a brokerage's own targets for a mode;
 * - `points_campaigns`: bonus campaigns a brokerage funds (for example double
 *   points on CE in November), with a points budget.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_goal_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The agent's own day (their time zone), Y-m-d.
            $table->string('day', 10);
            $table->string('mode', 8);
            // [{key, done, target, met}]
            $table->json('goals');
            $table->unsignedSmallInteger('met')->default(0);
            $table->unsignedSmallInteger('total')->default(0);
            // Points from REOS rates (capped per day), and brokerage bonus points.
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('bonus')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'day']);
            $table->index(['user_id', 'completed', 'day']);
        });

        Schema::create('daily_goal_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 8);
            // {conversations, ce_lesson, follow_ups, post}: the day's target (0 turns a goal off).
            $table->json('targets');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'mode']);
        });

        Schema::create('points_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            // A goal key, or `all`.
            $table->string('goal', 16);
            // 2 = double points.
            $table->unsignedTinyInteger('multiplier');
            $table->date('starts_on');
            $table->date('ends_on');
            // The most bonus points the brokerage will fund, and what it has funded so far.
            $table->unsignedInteger('budget');
            $table->unsignedInteger('spent')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organisation_id', 'ends_on']);
        });

        RowLevelSecurity::protect('daily_goal_settings');
        RowLevelSecurity::protect('points_campaigns');
    }

    public function down(): void
    {
        RowLevelSecurity::release('points_campaigns');
        RowLevelSecurity::release('daily_goal_settings');
        Schema::dropIfExists('points_campaigns');
        Schema::dropIfExists('daily_goal_settings');
        Schema::dropIfExists('daily_goal_days');
    }
};
