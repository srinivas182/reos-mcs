<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The connections engine (B3.1, ADR 0013): a person's or an organisation's
 * own account at an outside tool, the one-time vendor sign-ins that create
 * them, sync runs, inbound webhook receipts, and the first synced data
 * (calendar events, read-only until B4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->string('integration_id', 64);
            // Whose account it is: a person or an organisation.
            $table->string('owner_type', 32);
            $table->unsignedBigInteger('owner_id');
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            // The run mode it was made in (simulator, sandbox, live); tokens only work there.
            $table->string('mode', 16);
            $table->string('external_account_id');
            $table->string('account_label')->nullable();
            $table->json('scopes');
            $table->string('status', 24)->default('active');
            // Tokens, encrypted with the Integration Hub's key (REOS_CREDENTIALS_KEY).
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('settings');
            $table->json('cursors');
            $table->json('meta');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamps();

            $table->unique(['integration_id', 'owner_type', 'owner_id', 'external_account_id'], 'connections_account_unique');
            $table->index(['owner_type', 'owner_id']);
            $table->index(['status', 'last_synced_at']);
        });

        Schema::create('oauth_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('integration_id', 64);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('owner_type', 32);
            $table->unsignedBigInteger('owner_id');
            $table->string('mode', 16);
            $table->text('code_verifier');
            // Where to send the person afterwards: the website or the phone app.
            $table->string('return_to', 8);
            $table->json('settings');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->string('stream', 32);
            $table->string('trigger', 16);
            $table->string('outcome', 16)->nullable();
            $table->unsignedInteger('pages')->default(0);
            $table->unsignedInteger('upserted')->default(0);
            $table->unsignedInteger('removed')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->index(['connection_id', 'started_at']);
        });

        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('integration_id', 64);
            $table->string('delivery_key');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();

            $table->unique(['integration_id', 'delivery_key']);
        });

        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('calendar_id');
            $table->string('title')->nullable();
            $table->text('location')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('status', 16)->default('confirmed');
            $table->string('html_link', 2048)->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'external_id']);
            $table->index(['connection_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('oauth_authorizations');
        Schema::dropIfExists('connections');
    }
};
