<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The brokerage's mentor program (A13.1a, ADR 0053; broker screens 21–23,
 * agent screens 114, 116 and 117):
 *
 * - `mentor_settings`: what the brokerage sets for each mentor (topics,
 *   prices, points accepted, max mentees, who can book);
 * - `mentor_assignments`: an agent's assigned mentor (free to the agent);
 * - `session_bookings.paid_with` and `points`: a session paid by card,
 *   with points (1 point = 1 cent inside REOS), or free (assigned).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentor_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('topics');
            $table->unsignedInteger('price_30_cents')->default(0);
            $table->unsignedInteger('price_60_cents')->default(0);
            $table->boolean('points')->default(true);
            $table->unsignedSmallInteger('max_mentees')->default(5);
            // everyone at the brokerage, or assigned mentees only
            $table->string('who', 10)->default('everyone');
            $table->timestamps();
            $table->unique(['organisation_id', 'user_id']);
        });

        Schema::create('mentor_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            // Why this match: language, focus, mode, capacity.
            $table->json('reasons');
            $table->timestamp('change_requested_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['agent_id', 'ended_at']);
            $table->index(['mentor_id', 'ended_at']);
        });

        Schema::table('session_bookings', function (Blueprint $table): void {
            // card, points or free (the agent's assigned mentor)
            $table->string('paid_with', 8)->default('card');
            $table->unsignedInteger('points')->nullable();
        });

        RowLevelSecurity::protect('mentor_settings');
        RowLevelSecurity::protect('mentor_assignments');
    }

    public function down(): void
    {
        Schema::table('session_bookings', function (Blueprint $table): void {
            $table->dropColumn(['paid_with', 'points']);
        });
        Schema::dropIfExists('mentor_assignments');
        Schema::dropIfExists('mentor_settings');
    }
};
