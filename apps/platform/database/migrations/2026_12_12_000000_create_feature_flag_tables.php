<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature flags and releases (A6.2, screen 70, ADR 0022): Pennant's own
 * store (`features`), each flag's rollout, its per-minute uses and errors
 * (for the automatic rollback), releases with notes per role, and who has
 *  dismissed "What's new". Flag rows are
 * created from `FlagCatalogue` the first time they are read.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Laravel Pennant's database store: the resolved value per flag and audience.
        Schema::create('features', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('scope');
            $table->text('value');
            $table->timestamps();

            $table->unique(['name', 'scope']);
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('owner', 120)->nullable();
            $table->boolean('tenant_feature')->default(false);
            // off → internal → tenants → states → percent → everyone (each step includes the ones before).
            $table->string('stage', 16)->default('off');
            $table->json('tenant_ids')->nullable();
            $table->json('states')->nullable();
            $table->unsignedTinyInteger('percent')->default(0);
            // Null: every role; otherwise only these roles see it.
            $table->json('roles')->nullable();
            // Errors per 100 uses, over 10 minutes in a row, that step the rollout back.
            $table->unsignedTinyInteger('error_threshold')->default(5);
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('killed_at')->nullable();
            $table->foreignId('killed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable();
            $table->string('rolled_back_from', 16)->nullable();
            $table->timestamps();
        });

        Schema::create('feature_flag_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('feature_flag_id')->constrained()->cascadeOnDelete();
            $table->timestamp('minute');
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedInteger('errors')->default(0);

            $table->unique(['feature_flag_id', 'minute']);
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 32)->unique();
            $table->date('released_on');
            // Plain-words notes per role (role value => text); a role without a note sees nothing.
            $table->json('notes');
            $table->json('flags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('release_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('dismissed_at');

            $table->unique(['release_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_reads');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('feature_flag_metrics');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('features');
    }
};
