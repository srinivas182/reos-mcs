<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payouts (B5.2b, ADR 0021): Stripe Connect accounts for schools,
 * instructors and mentors (Express, or a tenant's own Stripe account), who
 * sells each product and how its sales are split, what each sale earns
 * each payee, and the weekly transfers that pay it out.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Who can be paid: an organisation (school) or a person (instructor, mentor).
        Schema::create('connected_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('owner_type', 20);
            $table->unsignedBigInteger('owner_id');
            $table->string('environment', 20);
            // express: onboarded through REOS; standard: the tenant's own Stripe account.
            $table->string('kind', 20)->default('express');
            $table->string('stripe_account_id', 80);
            $table->boolean('details_submitted')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->json('requirements')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['owner_type', 'owner_id', 'environment']);
            $table->unique(['stripe_account_id', 'environment']);
        });

        // The school that sells a product; null: REOS sells it (all revenue to REOS).
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('seller_organisation_id')->nullable()->after('buyer')->constrained('organisations')->nullOnDelete();
        });

        // Shares of the seller's part that go to instructors or mentors.
        Schema::create('product_splits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            // Percent of what's left after the platform fee.
            $table->unsignedTinyInteger('percent');
            $table->timestamps();
            $table->unique(['product_id', 'payee_user_id']);
        });

        Schema::create('payouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('connected_account_id')->constrained()->cascadeOnDelete();
            $table->string('environment', 20);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('usd');
            $table->string('status', 20);
            $table->string('transfer_id', 80)->nullable();
            $table->string('failure', 200)->nullable();
            $table->timestamps();
        });

        // What one paid order earns one payee. Never edited after it's paid out; refunds reverse it.
        Schema::create('payout_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('payee_type', 20);
            $table->unsignedBigInteger('payee_id');
            $table->string('role', 20);
            $table->unsignedInteger('gross_cents');
            $table->unsignedInteger('platform_fee_cents');
            $table->unsignedInteger('amount_cents');
            $table->string('status', 20);
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['order_id', 'payee_type', 'payee_id']);
            $table->index(['payee_type', 'payee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_lines');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('product_splits');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seller_organisation_id');
        });
        Schema::dropIfExists('connected_accounts');
    }
};
