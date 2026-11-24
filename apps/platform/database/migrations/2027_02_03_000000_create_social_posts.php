<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The posting engine (B11, ADR 0046): an agent's post with its text,
 * images or video, the attribution every post carries, and when it goes;
 * and each place it goes (a Facebook Page, an Instagram account…) with its
 * own status, tries, the post's id and link there, and the insights read
 * back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            // [{url, kind: image|video, path?}] in order.
            $table->json('media');
            $table->string('link', 500)->nullable();
            // The agent and broker (and the MLS's line for a listing post), added to every post.
            $table->string('attribution', 500);
            // The listing post this came from (B10.2), if any.
            $table->foreignId('listing_post_id')->nullable()->constrained()->nullOnDelete();
            // draft, scheduled, publishing, published, partly or failed.
            $table->string('status', 12);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'scheduled_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('social_post_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('integration', 40);
            // facebook, instagram, linkedin, google_business, youtube or tiktok.
            $table->string('channel', 20);
            $table->string('target_id', 120);
            $table->string('target_name', 200);
            // queued, publishing, published or failed.
            $table->string('status', 12);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 300)->nullable();
            $table->string('external_id', 200)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            // {reach, views, likes, comments, clicks, shares} as the channel reports them.
            $table->json('insights')->nullable();
            $table->timestamp('insights_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_targets');
        Schema::dropIfExists('social_posts');
    }
};
