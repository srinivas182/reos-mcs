<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ask (A10.1, ADR 0039): each person's conversations with their study mate,
 * and the questions sent on to a course's instructors with their answers.
 * A person's own, across organisations; no organisation_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ask_threads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('state_code', 2);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ask_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ask_thread_id')->constrained()->cascadeOnDelete();
            // question, answer or instructor
            $table->string('role', 12);
            $table->text('body');
            // An answer: content (from the course), sponsored (general AI a sponsor paid for), limited (beyond the course, no sponsor).
            $table->string('tier', 12)->nullable();
            $table->string('mode', 10)->nullable();
            $table->json('sources')->nullable();
            $table->json('quiz')->nullable();
            // The math lab kind that practises it ("Try a similar one").
            $table->string('math_kind', 20)->nullable();
            // Who paid for a sponsored answer.
            $table->string('sponsor', 160)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ask_messages');
        Schema::dropIfExists('ask_threads');
    }
};
