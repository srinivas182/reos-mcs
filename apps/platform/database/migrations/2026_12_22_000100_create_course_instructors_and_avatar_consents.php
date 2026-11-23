<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instructors (A7.1b, ADR 0025; school admin screen 52): which courses each
 * instructor teaches, and each instructor's avatar consent with their
 * school, with the sample the avatar is made from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('avatar_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            // given, withdrawn (by the instructor) or ended (no longer an instructor there).
            $table->string('status', 12);
            $table->string('version', 20);
            $table->foreignId('consent_record_id')->nullable()->constrained('consent_records')->nullOnDelete();
            $table->string('sample_path')->nullable();
            $table->string('sample_mime', 60)->nullable();
            $table->unsignedBigInteger('sample_size')->nullable();
            // The avatar made at each vendor (A7.3), deleted there on withdrawal.
            $table->json('vendor_avatars')->nullable();
            $table->timestamp('given_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            // Lessons using the avatar are unpublished or re-rendered by this date.
            $table->date('lessons_due_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'organisation_id']);
        });

        RowLevelSecurity::protect('avatar_consents');
    }

    public function down(): void
    {
        RowLevelSecurity::release('avatar_consents');
        Schema::dropIfExists('avatar_consents');
        Schema::dropIfExists('course_instructors');
    }
};
