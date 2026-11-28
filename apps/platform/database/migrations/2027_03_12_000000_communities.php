<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Communities (B17.2, ADR 0086): a Pro or Partner provider's community of
 * verified agents and providers, with posts, resources and expert
 * sessions. The network spans tenants, so no organisation_id: membership
 * is checked in App\Communities.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('about')->nullable();
            // The link and QR code to join (a new code turns the old one off).
            $table->string('invite_code', 24)->unique();
            $table->timestamps();
        });

        Schema::create('community_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // owner or member
            $table->string('role', 10)->default('member');
            // owner, link or qr
            $table->string('via', 10)->default('link');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->unique(['community_id', 'user_id']);
            $table->index(['user_id', 'left_at']);
        });

        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamps();
            $table->index(['community_id', 'created_at']);
        });

        Schema::create('community_resources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('url', 500);
            $table->timestamps();
        });

        Schema::create('community_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('about')->nullable();
            // online or in_person
            $table->string('format', 10);
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('minutes');
            $table->string('place', 200)->nullable();
            $table->string('join_url', 500)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // A CE course number the host gives for a session approved elsewhere; null: not for CE.
            $table->string('ce_course', 40)->nullable();
            // scheduled, done or cancelled
            $table->string('status', 10)->default('scheduled');
            $table->timestamps();
            $table->index(['community_id', 'starts_at']);
        });

        Schema::create('community_rsvps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // going, held (waiting for payment) or cancelled
            $table->string('status', 10);
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();
            $table->unique(['community_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_rsvps');
        Schema::dropIfExists('community_events');
        Schema::dropIfExists('community_resources');
        Schema::dropIfExists('community_posts');
        Schema::dropIfExists('community_members');
        Schema::dropIfExists('communities');
    }
};
