<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * To-dos and voice capture (B4.3, ADR 0018).
 *
 * - `todos`: a person's own to-dos, typed or spoken. The phone names each
 *   new one (`client_id`), so a capture sent twice after being offline is
 *   saved once. A due date is also an event on their REOS calendar.
 * - `ai_credit_uses`: what each AI call cost (the ledger is a stub until
 *   Sprint 6): the feature and count only, never what was said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->nullable();
            $table->string('title', 255);
            $table->text('notes')->nullable();
            // A moment (with a time), or a date with no time.
            $table->timestamp('due_at')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('remind_at')->nullable();
            // The person it's about: a phone contact today, a CRM contact from Sprint 5.
            $table->string('related_type', 40)->nullable();
            $table->string('related_id', 64)->nullable();
            $table->string('related_label', 120)->nullable();
            // typed or voice.
            $table->string('source', 10)->default('typed');
            $table->timestamp('done_at')->nullable();
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'client_id']);
            $table->index(['user_id', 'done_at', 'due_at']);
        });

        Schema::create('ai_credit_uses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 40);
            $table->string('engine', 20);
            $table->unsignedInteger('credits');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_uses');
        Schema::dropIfExists('todos');
    }
};
