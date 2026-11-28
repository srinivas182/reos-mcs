<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open house sign-in (B14.3a, ADR 0066): visitors sign in from a QR code
 * on their own phone, or on the agent's phone or tablet (which keeps
 * working offline); each visit lands in the agent's CRM with a drafted
 * follow-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_houses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('address', 160);
            $table->string('city', 80)->nullable();
            // The short code in the sign-in link and QR (no account needed).
            $table->string('code', 12)->unique();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('open_house_visits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('open_house_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            // The phone's own id for a sign-in made offline, so a retry never adds it twice.
            $table->string('client_id', 64)->nullable();
            $table->string('name', 120);
            $table->string('email', 160)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('has_agent')->default(false);
            $table->boolean('pre_approved')->default(false);
            $table->string('timeline', 20)->nullable();
            $table->boolean('consent')->default(false);
            $table->string('source', 10);
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('signed_in_at');
            $table->timestamps();
            $table->unique(['open_house_id', 'client_id']);
        });

        RowLevelSecurity::protect('open_houses', allowPersonal: true);
        RowLevelSecurity::protect('open_house_visits', allowPersonal: true);
    }

    public function down(): void
    {
        RowLevelSecurity::release('open_house_visits');
        RowLevelSecurity::release('open_houses');
        Schema::dropIfExists('open_house_visits');
        Schema::dropIfExists('open_houses');
    }
};
