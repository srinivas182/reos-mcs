<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Studio videos (B12.2, ADR 0051): a vertical listing video an agent makes
 * from listing photos and their own clips, with captions, the brand frame
 * and REOS-made music. The scenes are kept so the agent can trim, reorder
 * and recaption, then render again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_videos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // {type: listing|own, id, label}
            $table->json('source');
            // The MLS listing's uuid, for posts that keep the MLS's rules.
            $table->uuid('listing')->nullable();
            // The first two seconds' words (the hook).
            $table->string('title', 80);
            // [{media, kind: image|video, start, seconds, length, caption}]
            $table->json('scenes');
            $table->string('music', 12)->default('calm');
            // The brand kit's colour on the frame (the broker's name always shows).
            $table->boolean('brand')->default(true);
            // rendering, ready or failed
            $table->string('status', 10)->default('rendering');
            $table->unsignedSmallInteger('version')->default(0);
            $table->string('path')->nullable();
            $table->string('poster')->nullable();
            $table->decimal('seconds', 5, 1)->nullable();
            $table->string('error', 120)->nullable();
            $table->string('language', 5)->default('en');
            $table->string('engine', 10)->default('simulator');
            $table->unsignedSmallInteger('credits')->default(0);
            $table->timestamp('rendered_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_videos');
    }
};
