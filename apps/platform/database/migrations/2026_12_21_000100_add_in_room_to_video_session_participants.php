<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is in the room right now (B6.3c): recording needs the agreement of
 * everyone present. Set when someone joins and cleared when they leave
 * (their Leave, or LiveKit's participant_left webhook).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_session_participants', function (Blueprint $table): void {
            $table->boolean('in_room')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('video_session_participants', function (Blueprint $table): void {
            $table->dropColumn('in_room');
        });
    }
};
