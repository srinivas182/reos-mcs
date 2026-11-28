<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teams (A17.3, ADR 0084): lead routing rules and the leads they route,
 * and split sheets from the team agreement. All the team's, with
 * row-level security.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            // Lead routing rules and the team agreement's split (A17.3).
            $table->json('lead_routing')->nullable();
            $table->json('team_agreement')->nullable();
        });

        Schema::create('lead_routing_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->json('languages')->nullable();
            $table->timestamp('last_assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'user_id']);
        });

        Schema::create('routed_leads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('source', 40);
            $table->string('name', 160);
            $table->string('email', 200)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('language', 5)->nullable();
            $table->string('message', 1000)->nullable();
            // offered, claimed, assigned (round robin or fallback) or leader
            $table->string('status', 12);
            $table->json('offered_to')->nullable();
            $table->timestamp('claim_by')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->string('how', 20)->nullable();
            $table->string('auto_reply', 500)->nullable();
            $table->uuid('contact_uuid')->nullable();
            $table->timestamps();
            $table->index(['organisation_id', 'status']);
        });

        Schema::create('split_sheets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('property', 200);
            $table->date('closing_on');
            $table->string('lead_source', 40)->nullable();
            $table->unsignedBigInteger('gross_cents');
            $table->unsignedBigInteger('brokerage_cents');
            $table->unsignedBigInteger('team_cents');
            $table->unsignedBigInteger('agent_cents');
            // sent, signed or questioned
            $table->string('status', 12);
            $table->timestamp('signed_at')->nullable();
            // When it went to QuickBooks (B17.3)
            $table->timestamp('exported_at')->nullable();
            $table->string('export_ref', 80)->nullable();
            $table->timestamps();
        });

        RowLevelSecurity::protect('lead_routing_members');
        RowLevelSecurity::protect('routed_leads');
        RowLevelSecurity::protect('split_sheets');
    }

    public function down(): void
    {
        Schema::dropIfExists('split_sheets');
        Schema::dropIfExists('routed_leads');
        Schema::dropIfExists('lead_routing_members');
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn(['lead_routing', 'team_agreement']);
        });
    }
};
