<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video sessions (B6.3, ADR 0024): 1-on-1 sessions and live classes in a
 * LiveKit room, scheduled by an instructor or mentor with people they work
 * with. Like conversations, a session spans tenants (a school's instructor
 * and a brokerage's agent), so only its participants can open it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // The host's organisation when it was scheduled (reports and CE later).
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            // one_on_one or live_class
            $table->string('kind', 12);
            $table->string('title', 120);
            $table->text('agenda')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            // scheduled, live, ended or cancelled
            $table->string('status', 10)->default('scheduled')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            // Session notes (screen 16): shared by everyone in the session.
            $table->text('notes')->nullable();
            $table->foreignId('notes_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notes_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('video_session_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // host or guest
            $table->string('role', 5);
            $table->timestamp('first_joined_at')->nullable();
            $table->timestamp('last_joined_at')->nullable();
            $table->unsignedInteger('joins')->default(0);

            $table->unique(['video_session_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_session_participants');
        Schema::dropIfExists('video_sessions');
    }
};
