<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client portal (B14.2, ADR 0065): one private link per client on a
 * deal — no account or password — showing the deal's status and key
 * dates, documents to sign (embedded DocuSign), and a review request after
 * closing. Reviews the client allows are shown on the agent's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_portals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 160)->nullable();
            $table->string('language', 2)->default('en');
            // Only the hash finds the link; the token is kept encrypted so the agent can share it again.
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['deal_id', 'revoked_at']);
        });

        Schema::create('client_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_portal_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            // Shown on the agent's public page only with the client's say-so, as "Rosa L."
            $table->boolean('publish')->default(false);
            $table->string('shown_name', 60);
            $table->timestamps();
            $table->index(['user_id', 'publish']);
        });

        RowLevelSecurity::protect('client_portals', allowPersonal: true);
        RowLevelSecurity::protect('client_reviews', allowPersonal: true);
    }

    public function down(): void
    {
        RowLevelSecurity::release('client_reviews');
        RowLevelSecurity::release('client_portals');
        Schema::dropIfExists('client_reviews');
        Schema::dropIfExists('client_portals');
    }
};
