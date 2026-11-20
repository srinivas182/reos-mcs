<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration Hub checks (Sprint 2.4b): every "Test connection" and every
 * scheduled health check, plus automatic pausing after repeated failures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('integration_id', 100);
            $table->string('environment', 20);
            $table->string('outcome', 20);
            $table->string('message', 100);
            $table->json('parameters');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('trigger', 20);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['integration_id', 'environment', 'id']);
            $table->index('created_at');
        });

        Schema::table('integration_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('mode');
            $table->timestamp('paused_at')->nullable()->after('consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('integration_settings', function (Blueprint $table): void {
            $table->dropColumn(['consecutive_failures', 'paused_at']);
        });

        Schema::dropIfExists('integration_checks');
    }
};
