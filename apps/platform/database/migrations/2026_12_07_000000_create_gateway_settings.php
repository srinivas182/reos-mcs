<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment gateways (B5.2a, screen 66): which gateway serves which states and
 * kinds of product, in what order, whether tenants may use their own
 * account, and the kill switch. Orders keep the gateway's own reference and
 * the tax calculation used for gateways without built-in tax.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_settings', function (Blueprint $table): void {
            $table->string('gateway', 40)->primary();
            // State codes it serves; null: every state.
            $table->json('states')->nullable();
            // Product kinds it's used for; null: every kind.
            $table->json('used_for')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('tenant_accounts')->default(false);
            // The kill switch: checkout moves to the next allowed gateway.
            $table->boolean('paused')->default(false);
            $table->string('paused_reason', 200)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('gateway_reference', 60)->nullable()->after('gateway_session_id');
            $table->string('postal_code', 10)->nullable()->after('state_code');
            $table->string('tax_calculation_id', 120)->nullable()->after('tax_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['gateway_reference', 'postal_code', 'tax_calculation_id']);
        });
        Schema::dropIfExists('gateway_settings');
    }
};
