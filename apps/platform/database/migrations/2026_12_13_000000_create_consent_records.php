<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consent records (A6.3): what each person agreed to, which version of the
 * words, when, how, and when they withdrew. Never edited: a withdrawal is a
 * new record. Kept after account deletion as proof, linked to no one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 40);
            // One session or provider for per-thing consents ("session:12", "provider:4").
            $table->string('subject', 80)->nullable();
            $table->boolean('granted');
            $table->string('version', 20);
            $table->string('method', 10);
            // An admin acting on the person's behalf.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'purpose', 'subject', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
