<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests to connect (B16.1, ADR 0079): a provider asks an agent in their
 * areas to talk. Limited per plan each month; unanswered, it expires after
 * 14 days. Between two people, not tenants: no organisation_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message', 500)->nullable();
            // pending, accepted, declined, expired or withdrawn
            $table->string('status', 12)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['provider_profile_id', 'created_at']);
            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_requests');
    }
};
