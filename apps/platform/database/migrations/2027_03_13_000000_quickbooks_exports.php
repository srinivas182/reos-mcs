<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QuickBooks Online for brokerages (B17.3, ADR 0087): the brokerage's
 * company connection, the accounts it maps once, and every journal entry
 * REOS posted (split sheets and payouts), so nothing posts twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('integration_id', 40)->default('quickbooks-online');
            // simulator, sandbox or live, when it was connected
            $table->string('mode', 10);
            $table->string('realm_id', 40);
            $table->string('company_name', 160);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            // brokerage_income, team_payable, agent_payable, clearing, payouts_expense
            $table->json('accounts')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            // connected, needs_reconnect
            $table->string('status', 20)->default('connected');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            // split_sheet or payout
            $table->string('kind', 12);
            $table->string('subject_id', 40);
            $table->unsignedBigInteger('amount_cents');
            // posted or failed
            $table->string('status', 10);
            $table->string('ref', 60)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'kind', 'subject_id']);
        });

        RowLevelSecurity::protect('accounting_connections');
        RowLevelSecurity::protect('accounting_exports');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_exports');
        Schema::dropIfExists('accounting_connections');
    }
};
