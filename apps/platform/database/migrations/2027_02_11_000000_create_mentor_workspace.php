<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mentor's workspace and wrap-up (A13.1b, ADR 0053; mentor screens
 * 22–24 and 27, agent screen 119):
 *
 * - `mentee_notes`: a mentor's private notes on a mentee (the mentor only);
 * - `session_bookings.summary`: the session's wrap-up summary;
 * - `session_bookings.review_status`: a low rating with a comment waits
 *   for the brokerage's review (pending, kept or hidden) before it counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentee_notes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['mentor_id', 'agent_id']);
        });

        Schema::table('session_bookings', function (Blueprint $table): void {
            $table->text('summary')->nullable();
            $table->string('review_status', 8)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
        });

        RowLevelSecurity::protect('mentee_notes');
    }

    public function down(): void
    {
        Schema::table('session_bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['summary', 'review_status', 'reviewed_at']);
        });
        Schema::dropIfExists('mentee_notes');
    }
};
