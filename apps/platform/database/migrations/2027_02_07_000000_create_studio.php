<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The content studio (B12.1, ADR 0050):
 *
 * - `studio_generations`: what an agent made from a listing, a sale, a
 *   market update or their own words: three captions, designs, carousel
 *   slides and ad copy, with the credits it cost;
 * - `organisations.advertising`: the brokerage's advertising policy
 *   (approval for new agents or everyone, banned phrases);
 * - posts waiting for the broker's approval, and what the broker said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_generations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // {type: listing|sale|market|text, id, text, label}
            $table->json('source');
            // The MLS listing's uuid, for posts that keep the MLS's rules.
            $table->uuid('listing')->nullable();
            $table->json('outputs');
            // [{tone, text}]
            $table->json('captions');
            // [{layout, path}]
            $table->json('designs');
            $table->json('carousel');
            // {headlines, primary, description}
            $table->json('ad')->nullable();
            $table->string('language', 5)->default('en');
            $table->string('engine', 10)->default('simulator');
            $table->unsignedSmallInteger('credits')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('organisations', function (Blueprint $table): void {
            // {approval_new_agents: bool, approval_all: bool, banned: list<string>}
            $table->json('advertising')->nullable();
        });

        Schema::table('social_posts', function (Blueprint $table): void {
            // A post the brokerage asked to see first: when it was sent, who decided, and the note if sent back.
            $table->timestamp('approval_requested_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_note', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approval_requested_at', 'approved_at', 'approval_note']);
        });
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn('advertising');
        });
        Schema::dropIfExists('studio_generations');
    }
};
