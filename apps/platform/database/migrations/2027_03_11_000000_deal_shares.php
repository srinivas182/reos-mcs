<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinators (B17.1, ADR 0085): an agent shares a deal with a transaction
 * coordinator, by part (dates, documents, parties, messages), until
 * closing. Between two people across tenants: the deal's tenant is checked
 * in the app (DealShares), so no organisation_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_shares', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->json('switches');
            // Items the coordinator ticked off (document names and date ids).
            $table->json('checklist')->nullable();
            // active or ended
            $table->string('status', 10)->default('active');
            $table->timestamp('ended_at')->nullable();
            // closed, cancelled or removed
            $table->string('end_reason', 12)->nullable();
            $table->timestamps();
            $table->index(['coordinator_id', 'status']);
            $table->index(['deal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_shares');
    }
};
