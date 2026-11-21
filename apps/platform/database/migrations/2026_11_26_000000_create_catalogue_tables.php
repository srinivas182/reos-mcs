<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The integrations catalogue (B3.2, web journey screens 64–65): what people
 * see for each tool and who may use it, set by the super admin. Kept apart
 * from `integration_settings` (how REOS runs the tool: demo, test or live).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_settings', function (Blueprint $table) {
            $table->string('integration_id', 100)->primary();
            // Null: the register's `launch` value.
            $table->string('status', 16)->nullable();
            $table->date('retires_on')->nullable();
            // Null: everyone. Otherwise the allowed state codes, roles and plans.
            $table->json('states')->nullable();
            $table->json('roles')->nullable();
            $table->json('plans')->nullable();
            $table->json('beta_organisations');
            $table->boolean('tenants_may_hide')->default(true);
            $table->boolean('tenants_may_require')->default(false);
            $table->boolean('show_coming_soon')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // "Notify me" and "Join beta" taps: the demand the super admin sees.
        Schema::create('integration_interests', function (Blueprint $table) {
            $table->id();
            $table->string('integration_id', 100);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8);
            $table->timestamp('created_at');

            $table->unique(['integration_id', 'user_id', 'kind']);
        });

        // "My tool isn't listed".
        Schema::create('tool_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at');
        });

        // Tools an organisation hides from its people (they can hide, never enable).
        Schema::create('organisation_hidden_integrations', function (Blueprint $table) {
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('integration_id', 100);
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->primary(['organisation_id', 'integration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_hidden_integrations');
        Schema::dropIfExists('tool_requests');
        Schema::dropIfExists('integration_interests');
        Schema::dropIfExists('catalogue_settings');
    }
};
