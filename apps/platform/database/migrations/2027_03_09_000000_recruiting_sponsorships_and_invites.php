<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruiting (A17.2, ADR 0083): AI help a brokerage sponsors in an offer,
 * and invitations to licensed agents open to a move. Both span a brokerage
 * and a person outside it, so the brokerage is `brokerage_id` (checked in
 * the app), as for broker offers (A10.3b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broker_offers', function (Blueprint $table): void {
            // AI credits the brokerage sponsors for this candidate (0 for none).
            $table->unsignedInteger('ai_credits')->default(0);
        });

        Schema::create('candidate_sponsorships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('brokerage_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('broker_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('cap');
            $table->unsignedInteger('used')->default(0);
            $table->timestamp('ended_at')->nullable();
            // passed, other_sponsor or withdrawn
            $table->string('end_reason', 20)->nullable();
            $table->timestamps();
            $table->unique(['brokerage_id', 'candidate_id']);
        });

        Schema::create('recruiting_invites', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('brokerage_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('broker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['brokerage_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruiting_invites');
        Schema::dropIfExists('candidate_sponsorships');
        Schema::table('broker_offers', function (Blueprint $table): void {
            $table->dropColumn('ai_credits');
        });
    }
};
