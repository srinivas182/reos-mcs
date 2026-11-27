<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent learning (A14.2, ADR 0062).
 *
 * - `course_approvals.audio_allowed`: the school confirms that the
 *   regulator accepts audio-only study (spoken check-ins, screen off) as
 *   seat time for this CE or qualifying course. Off until confirmed.
 * - `courses.skills` and `courses.modes`: what a course teaches (the
 *   role-play lesson codes) and which agent modes it suits (launch, grow,
 *   lead, own), for course picks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_approvals', function (Blueprint $table): void {
            $table->boolean('audio_allowed')->default(false)->after('records_live');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->json('skills')->nullable()->after('hours');
            $table->json('modes')->nullable()->after('skills');
        });
    }

    public function down(): void
    {
        Schema::table('course_approvals', function (Blueprint $table): void {
            $table->dropColumn('audio_allowed');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['skills', 'modes']);
        });
    }
};
