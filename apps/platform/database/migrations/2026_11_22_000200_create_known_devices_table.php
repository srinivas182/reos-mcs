<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices a person has signed in on before (A3.1). Signing in on one that
 * isn't here sends a "new device" email; signing out doesn't remove it.
 * People without email (phone sign-up) can still sign in by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('known_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id');
            $table->string('name', 100);
            $table->string('platform', 20);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->unique(['user_id', 'device_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            // Phone sign-ups give their name in the 60-second setup (A3.3).
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('known_devices');
    }
};
