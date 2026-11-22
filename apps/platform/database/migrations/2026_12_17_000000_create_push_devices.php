<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push to the phone (B6.2b, ADR 0023): each signed-in phone's Expo push
 * token, tied to that phone's sign-in so signing out removes it, and the
 * tickets Expo gave back so dead tokens can be removed when the receipts
 * say so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Signing out (or revoking the phone in Devices) deletes the token row.
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 10);
            $table->string('name', 100)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('push_device_id')->constrained()->cascadeOnDelete();
            $table->string('ticket', 64)->unique();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tickets');
        Schema::dropIfExists('push_devices');
    }
};
