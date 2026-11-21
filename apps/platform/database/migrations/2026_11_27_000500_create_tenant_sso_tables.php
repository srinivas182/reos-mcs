<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single sign-on per tenant (A4.2c, screen 57): one SAML 2.0 or OpenID
 * Connect identity provider each, and the people who signed in through it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sso', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('protocol', 10); // saml, oidc
            $table->string('status', 10)->index(); // draft, tested, on
            // Identity provider details (no secrets): issuer and endpoints, or
            // entity ID, sign-in address and certificates.
            $table->json('settings');
            $table->text('client_secret')->nullable(); // OIDC only, encrypted
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_passed')->nullable();
            $table->string('last_test_detail', 500)->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sso_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The identity provider's stable id for the person (NameID or `sub`).
            $table->string('subject', 255);
            $table->string('email')->nullable();
            $table->timestamp('last_signed_in_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'subject']);
            $table->index('user_id');
        });

        RowLevelSecurity::protect('tenant_sso');
        RowLevelSecurity::protect('sso_identities');
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_identities');
        Schema::dropIfExists('tenant_sso');
    }
};
