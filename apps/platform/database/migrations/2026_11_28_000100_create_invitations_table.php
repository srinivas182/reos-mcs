<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations into a tenant (A4.3a): by email or text, with a role and an
 * optional team, a link and a short code, expiring after 14 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            // The tenant whose seat it takes (a team's invites belong to its brokerage).
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            // Where the role is given: the tenant itself or one of its teams.
            $table->foreignId('team_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->string('role', 30);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('name', 120)->nullable();
            $table->string('locale', 5);
            // Looked up by hash; the link and code themselves are kept encrypted
            // only so Resend and the day-7 reminder can send the same ones.
            $table->string('token_hash', 64)->unique();
            $table->string('code_hash', 64)->unique();
            $table->text('secret');
            $table->string('status', 12)->index(); // pending, accepted, revoked
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'email']);
            $table->index(['organisation_id', 'phone']);
        });

        RowLevelSecurity::protect('invitations');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
