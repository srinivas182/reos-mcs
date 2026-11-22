<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session recordings (B6.3c, ADR 0024): asked for in the room, started only
 * after everyone in it agrees (each agreement is a consent record), stopped
 * by anyone, recorded by LiveKit to REOS's storage, played only to the
 * people in the session and deleted after 90 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_recordings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('video_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // asking, declined, recording, stopping, ready, failed or deleted
            $table->string('status', 10)->index();
            $table->string('egress_id', 64)->nullable()->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 255)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('video_recording_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_recording_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('agreed');
            // The consent record it was saved as (consent_records).
            $table->foreignId('consent_record_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['video_recording_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_recording_consents');
        Schema::dropIfExists('video_recordings');
    }
};
