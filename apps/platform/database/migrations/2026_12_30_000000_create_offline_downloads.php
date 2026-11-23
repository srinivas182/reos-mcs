<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline lessons on the phone (A8.2b, ADR 0030): the 720p download Mux makes
 * of each video, a grant per learner, lesson and phone (with the key the phone
 * signs its offline seat time with, valid 30 days between online checks), and
 * the real start and end of each offline stretch so overlaps never count twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_media', function (Blueprint $table) {
            // {"resolution": "720p", "bytes": 123456789}: the download, once ready.
            $table->json('offline')->nullable();
        });

        Schema::create('offline_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            // The phone's own id for itself (kept in its secure storage).
            $table->string('device', 64);
            // HMAC key the phone signs offline seat time with (encrypted at rest).
            $table->text('key');
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id', 'device']);
        });

        Schema::table('seat_time_events', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('seat_time_events', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'started_at']);
            $table->dropColumn(['started_at', 'ended_at']);
        });
        Schema::dropIfExists('offline_grants');
        Schema::table('lesson_media', function (Blueprint $table) {
            $table->dropColumn('offline');
        });
    }
};
