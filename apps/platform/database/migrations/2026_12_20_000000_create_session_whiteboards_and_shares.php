<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared whiteboard and quick actions in a session (B6.3b, ADR 0024):
 * the board's drawing (Excalidraw elements) and a picture of it kept with
 * the session, and the lessons and practice questions the host shared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_sessions', function (Blueprint $table): void {
            // Excalidraw elements (JSON), up to 2 MB.
            $table->longText('whiteboard')->nullable();
            // PNG on the content disk, for the phone and the notes.
            $table->string('whiteboard_image', 255)->nullable();
            $table->timestamp('whiteboard_updated_at')->nullable();
        });

        Schema::create('video_session_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // lesson or questions
            $table->string('kind', 10);
            $table->json('payload');
            $table->timestamp('created_at');

            $table->index(['video_session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_session_shares');

        Schema::table('video_sessions', function (Blueprint $table): void {
            $table->dropColumn(['whiteboard', 'whiteboard_image', 'whiteboard_updated_at']);
        });
    }
};
