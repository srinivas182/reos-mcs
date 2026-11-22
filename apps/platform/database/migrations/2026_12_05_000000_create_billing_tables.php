<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans, prices, checkout and entitlements (B5.1, ADR 0021).
 *
 * - `products`: what REOS sells (plan, course, package, credit pack, seat
 *   pack) to one kind of buyer, with the features (entitlement keys) it
 *   grants. Screen 67.
 * - `prices`: a product's price in a state, per month, year or once, in USD
 *   (decision 3: REOS sets every list price per state; sponsors may
 *   discount down to `floor_cents`). Never edited: a change ends one price
 *   and starts the next, so the history stays.
 * - `sponsor_discounts`: a brokerage, association or school pays part of a
 *   product for its people; applied automatically at checkout.
 * - `orders`, `subscriptions`: what was bought through which gateway.
 * - `entitlements`: REOS's own record of who may use what, fed by orders,
 *   subscriptions, sponsors and admins, read by the web and the phone.
 * - `gateway_customers`: the person's customer id at each gateway.
 *
 * Sponsors are kept as `sponsor_organisation_id`: these rows belong to the
 * buyer, not to the sponsor's tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 160);
            // plan, course, package, credit_pack or seat_pack.
            $table->string('kind', 20);
            // candidate, agent, broker, school, provider or association.
            $table->string('buyer', 20);
            $table->string('description', 500)->nullable();
            $table->json('includes')->nullable();
            $table->json('features')->nullable();
            $table->string('ai_help', 160)->nullable();
            // AI credits a month (plans) or in the pack (credit packs).
            $table->unsignedInteger('credits')->nullable();
            $table->unsignedInteger('seats')->nullable();
            // draft, live or retired.
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['buyer', 'status']);
        });

        Schema::create('prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->char('state_code', 2);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('usd');
            // month, year or once.
            $table->string('interval', 10);
            $table->unsignedInteger('floor_cents')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'state_code', 'interval', 'ends_at']);
        });

        Schema::create('sponsor_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['sponsor_organisation_id', 'product_id']);
        });

        Schema::create('gateway_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 30);
            // sandbox or live: test and live customers are different.
            $table->string('environment', 10);
            $table->string('customer_id', 120);
            $table->timestamps();

            $table->unique(['user_id', 'gateway', 'environment']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Kept for tax records when the account is deleted, linked to no one.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('price_id')->constrained();
            $table->char('state_code', 2);
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('discount_cents')->default(0);
            $table->foreignId('sponsor_organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->unsignedInteger('tax_cents')->nullable();
            $table->unsignedInteger('total_cents')->nullable();
            $table->char('currency', 3)->default('usd');
            // pending, paid, expired, refunded.
            $table->string('status', 20)->default('pending');
            $table->string('gateway', 30);
            $table->string('environment', 10);
            $table->string('gateway_session_id', 255)->nullable()->unique();
            $table->string('gateway_payment_id', 255)->nullable();
            $table->string('gateway_subscription_id', 255)->nullable();
            $table->string('receipt_url', 500)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('price_id')->constrained();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 30);
            $table->string('gateway_subscription_id', 255)->unique();
            // active, past_due, canceled.
            $table->string('status', 20);
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 80);
            // order, subscription, sponsor or admin.
            $table->string('source', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->char('state_code', 2)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'feature']);
            $table->index(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('gateway_customers');
        Schema::dropIfExists('sponsor_discounts');
        Schema::dropIfExists('prices');
        Schema::dropIfExists('products');
    }
};
