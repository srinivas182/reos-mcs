<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switching to agent (A10.3c, ADR 0041; candidate screens f03 and 20): the
 * licence number the candidate entered, what the regulator's record said,
 * and whether REOS is still checking daily for it to become active.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licence_journeys', function (Blueprint $table): void {
            $table->string('licence_number', 40)->nullable();
            // verified, inactive, name_mismatch, not_found or no_lookup.
            $table->string('licence_check', 16)->nullable();
            $table->timestamp('licence_checked_at')->nullable();
            // Checked daily until the licence is active in the candidate's name.
            $table->boolean('licence_watch')->default(false);
            $table->timestamp('licence_found_at')->nullable();
            $table->timestamp('switched_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('licence_journeys', function (Blueprint $table): void {
            $table->dropColumn(['licence_number', 'licence_check', 'licence_checked_at', 'licence_watch', 'licence_found_at', 'switched_at']);
        });
    }
};
