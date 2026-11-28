<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent-to-agent referrals and invite and earn (B14.3b, ADR 0067).
 *
 * A referral goes from an agent at one brokerage to an agent at another,
 * so it spans tenants (like a conversation): only the two agents open it,
 * checked in Referrals (CheckTenancy::EXEMPT says why). The fee is agreed
 * between the two brokers; REOS records it and never takes or pays one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Where it started: the sending agent's organization.
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Who it went to, as typed (an agent outside REOS gets an email).
            $table->string('to_name', 120);
            $table->string('to_email', 160);
            $table->string('to_brokerage', 160)->nullable();
            // Both brokers as they were when it was sent: the agreement is theirs.
            $table->string('from_broker', 200)->nullable();
            $table->string('to_broker', 200)->nullable();
            $table->string('client_name', 120);
            $table->string('client_email', 160)->nullable();
            $table->string('client_phone', 30)->nullable();
            $table->string('kind', 10);
            $table->string('area', 120)->nullable();
            $table->text('note')->nullable();
            // The referral fee the brokers agreed, as a whole percent (paid broker to broker).
            $table->unsignedTinyInteger('fee_percent')->nullable();
            $table->string('status', 12);
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['from_user_id', 'created_at']);
            $table->index(['to_user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            // Invite and earn: the person's own link, and who invited them.
            $table->string('invite_code', 12)->nullable()->unique();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invited_by_id');
            $table->dropUnique(['invite_code']);
            $table->dropColumn(['invite_code', 'invited_at']);
        });
        Schema::dropIfExists('referrals');
    }
};
