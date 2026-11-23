<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CE class report (A10.2c, ADR 0040; instructor i13, web w505): each
 * student's seat time and attendance checks when a CE class ends, earned
 * CE, didn't, or to review; the instructor's decisions on borderline cases
 * with a reason, and Confirm and send to school. Approvals say whether a
 * live class may be recorded for the course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seconds_present');
            $table->unsignedSmallInteger('checks_answered');
            $table->unsignedSmallInteger('checks_total');
            // earned, review or not_earned, by the approval's rules.
            $table->string('outcome', 16);
            // Why it isn't earned: seat_time, checks, not_joined.
            $table->json('reasons');
            // The instructor's decision on a borderline case: accepted or rejected, with a reason.
            $table->string('decision', 16)->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['video_session_id', 'user_id']);
        });

        Schema::table('live_classes', function (Blueprint $table): void {
            // none, draft (ready for the instructor) or sent (to the school).
            $table->string('report_status', 16)->default('none');
            $table->foreignId('report_sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('report_sent_at')->nullable();
        });

        Schema::table('course_approvals', function (Blueprint $table): void {
            // Live classes of the course may be recorded, and the recording goes to the course.
            $table->boolean('records_live')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('course_approvals', function (Blueprint $table): void {
            $table->dropColumn('records_live');
        });
        Schema::table('live_classes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('report_sent_by');
            $table->dropColumn(['report_status', 'report_sent_at']);
        });
        Schema::dropIfExists('class_attendance');
    }
};
