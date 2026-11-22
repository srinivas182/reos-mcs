<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * States and licences (A5.1, Sprint 5).
 *
 * - `licences`: one per person and state (sales agent or broker), checked
 *   with the state's regulator or approved by a reviewer before it counts.
 *   `broker_role` marks who must take Broker Responsibility (A5.2).
 * - `users.current_state`: the state the person is working in now (the
 *   state switcher). Content, plans and the CE card follow it.
 * - `states.launch_checklist` and `went_live_at`: the launch checklist on
 *   web journey screen 61; a state goes live only when it is complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('state_code', 2);
            $table->foreign('state_code')->references('code')->on('states')->cascadeOnDelete();
            // sales_agent or broker.
            $table->string('type', 20);
            $table->string('number', 40);
            // active, inactive, expired, pending_review, rejected.
            $table->string('status', 20);
            // sponsoring_broker, designated_broker or delegated_supervisor.
            $table->string('broker_role', 30)->nullable();
            // As the regulator holds it (checked against the account name).
            $table->string('holder_name', 160)->nullable();
            $table->date('licensed_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('sponsor_name', 160)->nullable();
            $table->boolean('first_renewal')->default(false);
            // Where the check came from (a regulator lookup, the Simulator, or a reviewer).
            $table->string('verified_by', 40)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'state_code']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->char('current_state', 2)->nullable()->after('target_state');
            $table->foreign('current_state')->references('code')->on('states')->nullOnDelete();
        });

        Schema::table('states', function (Blueprint $table): void {
            $table->json('launch_checklist')->nullable();
            $table->timestamp('went_live_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('states', function (Blueprint $table): void {
            $table->dropColumn(['launch_checklist', 'went_live_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['current_state']);
            $table->dropColumn('current_state');
        });

        Schema::dropIfExists('licences');
    }
};
