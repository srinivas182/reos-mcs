<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback from agents (A13.3b, ADR 0056; agent screen 125): after a
 * verified closing each agent rates the other side's agent on
 * communication, responsiveness and professionalism. The average shows
 * only after three ratings, is private unless the agent makes it public,
 * and the agent can respond or report.
 *
 * Feedback crosses brokerages (the other side's agent is often elsewhere),
 * so rows carry no organisation; only the two agents can see them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_feedback', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('communication');
            $table->unsignedTinyInteger('responsiveness');
            $table->unsignedTinyInteger('professionalism');
            $table->text('comment')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            // Reported by the agent rated: left out of the average until REOS support looks.
            $table->timestamp('reported_at')->nullable();
            $table->string('report_reason', 300)->nullable();
            $table->timestamps();
            $table->unique(['deal_id', 'rater_id', 'ratee_id']);
            $table->index(['ratee_id', 'reported_at']);
        });

        Schema::table('agent_profiles', function (Blueprint $table): void {
            // Show the average on the agent's public page (off by default).
            $table->boolean('feedback_public')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table): void {
            $table->dropColumn('feedback_public');
        });
        Schema::dropIfExists('agent_feedback');
    }
};
