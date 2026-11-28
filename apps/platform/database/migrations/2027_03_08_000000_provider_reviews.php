<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Providers near you (B16.3, ADR 0081): agents connect with a provider
 * themselves (`initiated_by`), and review one after 30 days connected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_requests', function (Blueprint $table): void {
            // provider (B16.1, counts toward the plan's limit) or agent (B16.3, never counts)
            $table->string('initiated_by', 10)->default('provider');
        });

        Schema::create('provider_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('body', 1000)->nullable();
            $table->timestamps();
            // One review per agent per provider (decision 5).
            $table->unique(['provider_profile_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_reviews');
        Schema::table('provider_requests', function (Blueprint $table): void {
            $table->dropColumn('initiated_by');
        });
    }
};
