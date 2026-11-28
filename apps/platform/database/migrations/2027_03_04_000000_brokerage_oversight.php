<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent oversight (A16.1, ADR 0076): the brokerage's own "needs help"
 * thresholds (quiet days, days without a deal, the new-agent window).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->json('oversight_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn('oversight_settings');
        });
    }
};
