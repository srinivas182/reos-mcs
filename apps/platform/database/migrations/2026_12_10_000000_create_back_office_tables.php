<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super admin v1 (A6.1): request metrics and health checks for the platform
 * overview (screen 58) and the status page; provider categories with their
 * flat monthly plan prices per state (screen 68); versioned AI settings
 * (screen 69); when each person was last active (people online now).
 */
return new class extends Migration
{
    public function up(): void
    {
        // One row per minute: how many requests, how many failed, and how long they took (histogram, ms).
        Schema::create('request_metrics', function (Blueprint $table): void {
            $table->timestamp('minute')->primary();
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('errors')->default(0);
            foreach ([50, 100, 200, 300, 500, 1000, 2000] as $bucket) {
                $table->unsignedInteger("under_{$bucket}")->default(0);
            }
            $table->unsignedInteger('over_2000')->default(0);
        });

        Schema::create('health_checks', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('checked_at')->index();
            $table->boolean('ok');
            // Each part (database, cache, queue, storage) and how long it took.
            $table->json('parts');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_active_at')->nullable()->index();
        });

        Schema::create('provider_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            // What REOS checks before a provider goes live: a licence (NMLS, TREC inspector, TDI) or an ID check.
            $table->string('licence_check', 120)->nullable();
            $table->string('verification', 200)->nullable();
            $table->json('profile_fields')->nullable();
            $table->string('special_permissions', 200)->nullable();
            $table->unsignedSmallInteger('areas_on_pro')->default(3);
            // live, draft, requested (providers asked for it) or retired.
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('requests')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // RESPA: flat monthly fees for listing and advertising only.
        Schema::create('provider_category_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_category_id')->constrained()->cascadeOnDelete();
            $table->char('state_code', 2);
            $table->string('tier', 20);
            $table->unsignedInteger('monthly_cents');
            $table->timestamps();
            $table->unique(['provider_category_id', 'state_code', 'tier']);
        });

        // Every save of screen 69 is a new version; the newest is in force.
        Schema::create('ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('settings');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
        Schema::dropIfExists('provider_category_prices');
        Schema::dropIfExists('provider_categories');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['last_active_at']);
            $table->dropColumn('last_active_at');
        });
        Schema::dropIfExists('health_checks');
        Schema::dropIfExists('request_metrics');
    }
};
