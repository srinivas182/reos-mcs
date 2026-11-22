<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-serve tenant sign-up (A6.1b, the Sprint 6 gate): a brokerage or
 * school owner names their organisation, picks a plan and pays on the web;
 * the tenant is created when the payment lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_signups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name', 160);
            $table->json('states');
            $table->foreignId('price_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // The tenant, once created.
            $table->foreignId('created_organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            // pending, completed or cancelled.
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_signups');
    }
};
