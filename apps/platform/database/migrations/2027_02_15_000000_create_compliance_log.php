<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brokerage rules on every channel and the compliance log (B13.2, ADR 0058):
 *
 * - `compliance_events`: every check, fix, approval request, approval and
 *   send-back on any channel, with who did it, when and what was found;
 *   the broker filters it by agent and channel and exports it;
 * - `social_posts.approval_reason`: the agent's reason when they send a
 *   flagged post to the broker instead of fixing it;
 * - `agent_pages.approval_requested_at` and `approval_note`: the public page
 *   waits for the broker when the brokerage approves its agents' ads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_events', function (Blueprint $table): void {
            $table->id();
            // The agent's brokerage; null for independent agents.
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // post, video, page, email or text
            $table->string('channel', 8);
            // checked, blocked, fixed, sent_to_broker, approved, sent_back
            $table->string('action', 16);
            $table->string('subject_type', 32)->nullable();
            $table->string('subject_id', 64)->nullable();
            // [{code, phrase, citation}]
            $table->json('issues');
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at');
            $table->index(['organisation_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('social_posts', function (Blueprint $table): void {
            $table->string('approval_reason', 500)->nullable();
        });

        Schema::table('agent_pages', function (Blueprint $table): void {
            $table->timestamp('approval_requested_at')->nullable();
            $table->string('approval_note', 500)->nullable();
        });

        RowLevelSecurity::protect('compliance_events', allowPersonal: true);
    }

    public function down(): void
    {
        RowLevelSecurity::release('compliance_events');
        Schema::table('agent_pages', function (Blueprint $table): void {
            $table->dropColumn(['approval_requested_at', 'approval_note']);
        });
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->dropColumn('approval_reason');
        });
        Schema::dropIfExists('compliance_events');
    }
};
