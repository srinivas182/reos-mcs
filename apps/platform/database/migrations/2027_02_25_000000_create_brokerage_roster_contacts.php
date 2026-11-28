<?php

declare(strict_types=1);

use App\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring in your agents (A15.1b, ADR 0070): TREC's public file has no email
 * or phone, so a brokerage adds them for its sponsored agents who aren't on
 * REOS yet (pasted or from a CSV), keyed by TREC licence number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brokerage_roster_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('licence_number', 20);
            $table->string('email', 160)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'licence_number']);
        });

        RowLevelSecurity::protect('brokerage_roster_contacts');
    }

    public function down(): void
    {
        RowLevelSecurity::release('brokerage_roster_contacts');
        Schema::dropIfExists('brokerage_roster_contacts');
    }
};
