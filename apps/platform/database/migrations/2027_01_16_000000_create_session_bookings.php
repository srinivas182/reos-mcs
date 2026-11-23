<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking 1-on-1 sessions (A10.2a, ADR 0040; candidate screens 18, tutor
 * live and tutor rate; instructor i14 and i16): each instructor's hours and
 * prices, and each booking with its payment, cancellation, no-show refund,
 * rating and follow-up tasks. A booking spans tenants (a candidate and a
 * school's instructor), so access is checked in the app and neither table
 * has an organisation_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_offers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->json('languages');
            $table->json('topics');
            // Weekly hours in the instructor's time zone: {"mon": [["09:00", "12:00"]], ...}.
            $table->json('hours');
            $table->string('timezone', 40);
            // Whole dollars in cents; 0 means included (free to book).
            $table->unsignedInteger('price_30_cents');
            $table->unsignedInteger('price_60_cents');
            $table->foreignId('product_30_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_60_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('session_bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('video_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('minutes');
            $table->string('topic', 200);
            // held (waiting for payment), confirmed, cancelled, refunded, completed or no_show.
            $table->string('status', 12)->default('held');
            $table->unsignedInteger('price_cents');
            $table->timestamp('held_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->json('rating_tags')->nullable();
            $table->string('rating_comment', 1000)->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamp('tasks_sent_at')->nullable();
            $table->timestamps();
            $table->index(['instructor_id', 'starts_at']);
            $table->index(['student_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_bookings');
        Schema::dropIfExists('session_offers');
    }
};
