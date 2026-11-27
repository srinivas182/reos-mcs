<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-play and the skills assessment (A14.1a, ADR 0060; agent screen 7).
 *
 * An agent practises a listing presentation, an objection, a prospecting
 * call, an appraisal gap or a follow-up with a persona that pushes back,
 * by voice or typing, and gets a score out of 100 and three fixes. The
 * skills assessment is five short rounds, one per type; the weakest skill
 * seeds the 90-day plan.
 *
 * Practice is the agent's own: rows carry no organisation, only the agent
 * sees them, and they move with the agent if they change brokerage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // [{type, score}] once every round is scored, and the weakest types first.
            $table->json('results')->nullable();
            $table->json('weakest')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'completed_at']);
        });

        Schema::create('role_plays', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_assessment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('persona', 30);
            $table->string('language', 2);
            // [{role: persona|agent, text}]
            $table->json('turns');
            $table->string('status', 10)->default('active');
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('scores')->nullable();
            $table->json('fixes')->nullable();
            // Who played the persona and scored it: Claude or REOS's script.
            $table->string('engine', 10)->default('script');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_plays');
        Schema::dropIfExists('skill_assessments');
    }
};
