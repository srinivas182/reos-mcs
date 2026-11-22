<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phone's Membership screen (B5.1b; candidate screen 6).
 *
 * - `platform_settings`: switches the super admin changes without an app
 *   release, such as how the iPhone app points to buying (decision 4).
 * - `sponsor_codes`: a code a brokerage, school or association shares so its
 *   people get a product's access without paying ("Enter a code").
 * - `sponsor_code_redemptions`: who used each code (once per person).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sponsor_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('sponsor_organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->char('state_code', 2);
            $table->unsignedInteger('seats');
            $table->unsignedInteger('used')->default(0);
            // How long access lasts after redeeming (null: as long as the product's access).
            $table->unsignedSmallInteger('access_months')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sponsor_code_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sponsor_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sponsor_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_code_redemptions');
        Schema::dropIfExists('sponsor_codes');
        Schema::dropIfExists('platform_settings');
    }
};
