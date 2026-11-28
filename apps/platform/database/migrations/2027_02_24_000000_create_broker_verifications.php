<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TREC-verified brokerage setup (A15.1a, ADR 0069): the broker licence a
 * person proved before creating a brokerage, and the TREC number each
 * brokerage tenant stands for, so one brokerage is claimed once.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The person's own check, before any tenant exists: no organisation.
        Schema::create('broker_verifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('licence_number', 20);
            $table->string('licence_name', 160);
            $table->string('brokerage_number', 20);
            $table->string('brokerage_name', 160);
            // entity (an LLC, corporation or partnership broker) or individual (a broker who sponsors agents themselves).
            $table->string('brokerage_kind', 12);
            $table->unsignedInteger('sponsored');
            $table->string('role', 20);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('trec_number', 20)->nullable()->unique();
        });

        Schema::table('tenant_signups', function (Blueprint $table): void {
            $table->foreignId('broker_verification_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_signups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('broker_verification_id');
        });
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropUnique(['trec_number']);
            $table->dropColumn('trec_number');
        });
        Schema::dropIfExists('broker_verifications');
    }
};
