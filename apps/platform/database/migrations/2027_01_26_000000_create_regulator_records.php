<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regulators' public licence data (B10.3, decision 8): TREC's daily
 * license-holder files and TDI's licensee data, imported nightly so a
 * licence number is checked in milliseconds. Public records only: TREC
 * withholds phones, emails and addresses (SB 510), and REOS keeps none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulator_records', function (Blueprint $table): void {
            $table->id();
            // TREC or TDI; agents, inspectors or insurance.
            $table->string('authority', 8);
            $table->string('kind', 12);
            // The regulator's own code (SALE, BRK, PRIN… or TDI's licence type).
            $table->string('licence_type', 60);
            $table->string('number', 40);
            // Digits without leading zeros, so "0798765" finds "798765".
            $table->string('number_digits', 40)->index();
            $table->string('name', 200);
            $table->string('status', 40)->nullable();
            $table->boolean('active');
            $table->date('licensed_on')->nullable();
            $table->date('expires_on')->nullable();
            // TREC: 0 none required, 1 outstanding, 2 met.
            $table->unsignedTinyInteger('education_status')->nullable();
            $table->unsignedTinyInteger('mce_status')->nullable();
            $table->boolean('designated_supervisor')->default(false);
            // The sponsoring broker (TREC's related licence).
            $table->string('sponsor_number', 40)->nullable();
            $table->string('sponsor_name', 200)->nullable();
            // TDI: national producer number and lines of authority.
            $table->string('npn', 20)->nullable();
            $table->string('qualification', 300)->nullable();
            $table->timestamp('imported_at');
            $table->unique(['authority', 'licence_type', 'number']);
            $table->index(['authority', 'imported_at']);
        });

        Schema::create('regulator_imports', function (Blueprint $table): void {
            $table->id();
            $table->string('integration', 64);
            // simulator or live.
            $table->string('source', 12);
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('removed')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->index(['integration', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulator_imports');
        Schema::dropIfExists('regulator_records');
    }
};
