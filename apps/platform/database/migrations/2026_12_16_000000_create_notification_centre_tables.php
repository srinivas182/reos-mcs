<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notification centre (B6.2, ADR 0023): the in-app inbox (Laravel's
 * `notifications`), each person's choices by kind and channel, quiet hours
 * and digests, what's being held until morning or gathered for a digest,
 * and a log of what was sent where.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The inbox (the bell on the web and the phone).
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_settings', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('quiet_enabled')->default(true);
            $table->string('quiet_start', 5)->default('21:00');
            $table->string('quiet_end', 5)->default('07:00');
            $table->string('timezone', 64)->default('America/Chicago');
            // kind => channel => on/off, only where the person changed the default.
            $table->json('channels')->nullable();
            // kind => daily or weekly, for kinds gathered into a digest.
            $table->json('digests')->nullable();
            $table->timestamps();
        });

        // Alerts held during quiet hours, sent when they end.
        Schema::create('held_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->json('channels');
            $table->longText('notification');
            $table->timestamp('release_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['released_at', 'release_at']);
        });

        // Items waiting for the person's daily or weekly digest.
        Schema::create('digest_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('frequency', 10);
            $table->string('title', 200);
            $table->string('body', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'sent_at']);
        });

        // What went where (kind and channel only; never the words).
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 40);
            $table->string('channel', 10);
            // sent, held, released, digested, failed
            $table->string('status', 10);
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('digest_items');
        Schema::dropIfExists('held_notifications');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notifications');
    }
};
