<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Farming market updates (B14.3c, ADR 0068): an agent's areas, the monthly
 * update emailed to the contacts tagged for each (from MLS figures, checked
 * by the compliance engine), and the people who asked to stop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_areas', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            // The area in the MLS: a city or a ZIP code.
            $table->string('city', 80)->nullable();
            $table->string('postal_code', 10)->nullable();
            // Contacts with this tag get the update.
            $table->string('tag', 60);
            // The sender's postal address, in every email (CAN-SPAM).
            $table->string('mailing_address', 200);
            $table->timestamps();
        });

        Schema::create('farm_sends', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('farm_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject', 160);
            $table->text('body');
            $table->json('stats');
            $table->unsignedInteger('recipients');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['farm_area_id', 'sent_at']);
        });

        Schema::create('farm_opt_outs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained()->cascadeOnDelete();
            // SHA-256 of the lowercased email: no address is kept for someone who asked to stop.
            $table->char('email_hash', 64);
            $table->timestamps();
            $table->unique(['user_id', 'email_hash']);
        });

        RowLevelSecurity::protect('farm_areas', allowPersonal: true);
        RowLevelSecurity::protect('farm_sends', allowPersonal: true);
        RowLevelSecurity::protect('farm_opt_outs', allowPersonal: true);
    }

    public function down(): void
    {
        foreach (['farm_opt_outs', 'farm_sends', 'farm_areas'] as $table) {
            RowLevelSecurity::release($table);
            Schema::dropIfExists($table);
        }
    }
};
