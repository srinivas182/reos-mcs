<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provider onboarding (B15.2, ADR 0074): the steps a provider goes through
 * to go live, their service areas, the network rules they accepted, and a
 * photo ID for categories without a licence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table): void {
            // joining, waiting (done, but the network isn't open in the state yet), live or hidden.
            $table->string('status', 10)->default('joining');
            $table->string('hidden_reason', 40)->nullable();
            $table->timestamp('welcomed_at')->nullable();
            // Business details for categories without a licence.
            $table->string('business_name', 160)->nullable();
            $table->string('business_address', 240)->nullable();
            $table->string('state_code', 2)->default('TX');
            // basic (Listed), pro or premium (Partner), from the plan bought.
            $table->string('tier', 10)->nullable();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rules_version', 20)->nullable();
            $table->timestamp('rules_accepted_at')->nullable();
            $table->timestamp('live_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->index(['status', 'provider_category_id']);
        });

        Schema::create('provider_service_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            // city, zip or radius (miles around a ZIP code).
            $table->string('kind', 8);
            $table->string('city', 80)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->unsignedSmallInteger('radius_miles')->nullable();
            $table->timestamps();
            $table->index(['city']);
            $table->index(['postal_code']);
        });

        Schema::table('provider_licences', function (Blueprint $table): void {
            // A photo ID for a business_id check, on the content disk.
            $table->string('document_path', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('provider_licences', function (Blueprint $table): void {
            $table->dropColumn('document_path');
        });
        Schema::dropIfExists('provider_service_areas');
        Schema::table('provider_profiles', function (Blueprint $table): void {
            $table->dropIndex(['status', 'provider_category_id']);
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropColumn(['status', 'hidden_reason', 'welcomed_at', 'business_name', 'business_address', 'state_code', 'tier', 'rules_version', 'rules_accepted_at', 'live_at', 'checked_at']);
        });
    }
};
