<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger (B5.3, ADR 0021): accounts for people, seat and sponsor pools,
 * tenants, platform revenue and gateway clearing, in USD cents, AI credits,
 * seats or points; entries that are never edited (corrections are new
 * entries), each transaction balancing to zero per unit. Refunds from REOS
 * and the daily reconciliation with the gateway's balance transactions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id();
            // Stable name, such as `person:12:credit:monthly` or `platform:usd:revenue`.
            $table->string('key', 120)->unique();
            $table->string('type', 30);
            $table->string('owner_type', 20)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('unit', 10);
            // The running balance, kept with every entry.
            $table->bigInteger('balance')->default(0);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('transaction');
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->string('unit', 10);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('reason', 40);
            $table->string('reference_type', 30)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('memo', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('transaction');
            $table->index(['reference_type', 'reference_id']);
            $table->index(['ledger_account_id', 'id']);
        });

        // A refund REOS made (full or partial), with the policy check it was made under.
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->boolean('full');
            $table->string('reason', 300);
            $table->string('status', 20);
            $table->string('gateway_refund_id', 120)->nullable();
            $table->boolean('within_policy');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('gateway', 40);
            $table->string('environment', 20);
            $table->unsignedInteger('matched')->default(0);
            // Differences between the ledger and the gateway, one per line.
            $table->json('differences')->nullable();
            $table->string('status', 20);
            $table->timestamps();
            $table->unique(['day', 'gateway', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};
