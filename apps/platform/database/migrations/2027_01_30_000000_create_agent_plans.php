<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agent's 90-day plan (A11.1b, ADR 0044; agent screen 10): one per goal,
 * from REOS's template for that goal, with the tasks the agent ticks off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('goal', 8);
            $table->date('starts_on');
            $table->date('ends_on');
            // The First-Closing Promise (Launch): when the agent committed.
            $table->timestamp('promised_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'goal']);
        });

        Schema::create('agent_plan_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week');
            $table->string('code', 40);
            $table->timestamp('done_at');
            $table->unique(['agent_plan_id', 'week', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_plan_tasks');
        Schema::dropIfExists('agent_plans');
    }
};
