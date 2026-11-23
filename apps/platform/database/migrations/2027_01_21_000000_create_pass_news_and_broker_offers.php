<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass announcements and broker offers (A10.3b, ADR 0041; candidate
 * screens f05, f06 and 17). A candidate who passed chooses who hears (never
 * scores); classmates send congrats. Candidates who opt in get offers from
 * brokerages in their state; contact details reach a brokerage only when the
 * candidate accepts its offer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licence_journeys', function (Blueprint $table): void {
            // The day both portions were passed (the week's count of passes).
            $table->date('passed_on')->nullable();
            // After passing: shared or skipped ("Not now"); null until then.
            $table->string('news', 12)->nullable();
            $table->boolean('offers_opt_in')->default(false);
            $table->string('offers_city', 120)->nullable();
            $table->timestamp('offers_opted_at')->nullable();
        });

        Schema::create('pass_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('state_code', 2);
            // First name and city only: never scores, never a surname.
            $table->string('first_name', 60);
            $table->string('city', 120)->nullable();
            $table->string('tip', 280)->nullable();
            $table->boolean('to_group');
            $table->boolean('to_school');
            $table->boolean('to_state');
            $table->unsignedInteger('congrats_count')->default(0);
            $table->timestamps();
            $table->index(['state_code', 'to_state', 'created_at']);
        });

        Schema::create('pass_congrats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pass_announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('message', 140);
            $table->timestamp('created_at');
            $table->unique(['pass_announcement_id', 'user_id']);
        });

        Schema::create('broker_offers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // The brokerage making it. Not `organisation_id`: an offer spans the brokerage and a
            // candidate in another tenant, so access is checked in the app (BrokerOffers).
            $table->foreignId('brokerage_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('made_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('office_city', 120);
            // The agent's share of commission, in percent.
            $table->unsignedTinyInteger('split_percent');
            $table->unsignedInteger('monthly_fee_cents');
            $table->string('fees', 300)->nullable();
            $table->string('training', 500)->nullable();
            $table->boolean('mentoring');
            $table->string('message', 1000)->nullable();
            // open, accepted, declined or withdrawn.
            $table->string('status', 12);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['brokerage_id', 'candidate_id']);
            $table->index(['candidate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broker_offers');
        Schema::dropIfExists('pass_congrats');
        Schema::dropIfExists('pass_announcements');
        Schema::table('licence_journeys', function (Blueprint $table): void {
            $table->dropColumn(['passed_on', 'news', 'offers_opt_in', 'offers_city', 'offers_opted_at']);
        });
    }
};
