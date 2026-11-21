<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants' own domains (A4.2b, screens 41 and 57), verified and given SSL by
 * Cloudflare for SaaS. One per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('hostname', 253)->unique();
            $table->string('provider_id', 100);
            // pending, active, failed (the domain); pending, active (SSL)
            $table->string('status', 20)->index();
            $table->string('ssl', 20);
            $table->json('records');
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        RowLevelSecurity::protect('tenant_domains');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
