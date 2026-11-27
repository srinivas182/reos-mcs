<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Video practice with an AI review (A14.1b, ADR 0061).
 *
 * The agent records a listing pitch or a video intro of up to two
 * minutes. The video stays on their phone or in their browser; REOS gets
 * only the words (recognised on the device) and the length, and returns
 * the pace, filler words, structure, a score and three fixes, plus what
 * the compliance engine would flag if they posted it. Private to the
 * agent, so no organisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_practices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('language', 2);
            $table->text('transcript');
            $table->unsignedSmallInteger('duration_seconds');
            $table->unsignedSmallInteger('words');
            $table->unsignedSmallInteger('wpm');
            $table->json('fillers');
            $table->unsignedTinyInteger('score');
            $table->json('scores');
            $table->json('fixes');
            $table->json('compliance');
            $table->string('engine', 10)->default('script');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_practices');
    }
};
