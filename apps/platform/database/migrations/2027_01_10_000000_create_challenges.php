<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Challenges (A9.3b, screen 52): the same 10 questions for everyone who
 * accepts a share link. Each person's answers are their own test attempt,
 * linked to the challenge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A study group's challenge goes to the whole group (A9.3c).
            $table->unsignedBigInteger('study_group_id')->nullable()->index();
            $table->string('state_code', 2);
            $table->json('question_ids');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::table('test_attempts', function (Blueprint $table): void {
            $table->foreignId('challenge_id')->nullable()->after('topic')->constrained()->cascadeOnDelete();
            $table->unique(['challenge_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('test_attempts', function (Blueprint $table): void {
            $table->dropUnique(['challenge_id', 'user_id']);
            $table->dropConstrainedForeignId('challenge_id');
        });
        Schema::dropIfExists('challenges');
    }
};
