<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The morning brief (A11.1c, agent screen 184): when it goes out, in the
 * agent's own time zone, what it covers, and the last day it went, so it
 * goes once a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table): void {
            $table->boolean('brief_enabled')->default(true);
            // Local time, HH:MM.
            $table->string('brief_at', 5)->default('07:00');
            $table->string('brief_timezone', 64)->default('America/Chicago');
            // todos, appointments, deadlines, leads, ce.
            $table->json('brief_sections')->nullable();
            $table->date('brief_sent_on')->nullable();
            $table->index(['brief_enabled', 'brief_at']);
        });
    }

    public function down(): void
    {
        Schema::table('agent_profiles', function (Blueprint $table): void {
            $table->dropIndex(['brief_enabled', 'brief_at']);
            $table->dropColumn(['brief_enabled', 'brief_at', 'brief_timezone', 'brief_sections', 'brief_sent_on']);
        });
    }
};
