<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The agent's AI assistant (A12.3, ADR 0049): chats and their messages
 * (an answer can carry the day's items, a draft email or text, or a link
 * to the virtual broker), and the agent's permission for the assistant to
 * read their CRM, deals and calendar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_chats', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_chat_id')->constrained()->cascadeOnDelete();
            // question or answer.
            $table->string('role', 8);
            $table->text('body');
            // today, prep, deal, draft, policy, general, permission or none.
            $table->string('kind', 10)->nullable();
            // The day's items, the appointment's facts, the deal's dates: [{title, detail, source, at, link}].
            $table->json('items')->nullable();
            // {channel: email|text, to, name, subject, body, href}: never sent by REOS.
            $table->json('draft')->nullable();
            // A virtual broker question this answer came from.
            $table->string('broker_question', 36)->nullable();
            $table->string('engine', 10)->nullable();
            $table->timestamps();
        });

        Schema::table('agent_profiles', function (Blueprint $table): void {
            // When the agent allowed the assistant to read their CRM, deals and calendar (null: not allowed).
            $table->timestamp('assistant_context_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table): void {
            $table->dropColumn('assistant_context_at');
        });
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_chats');
    }
};
