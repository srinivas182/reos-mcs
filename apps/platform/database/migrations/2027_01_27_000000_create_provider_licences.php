<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service providers' professional licences (B10.3): TREC inspectors and TDI
 * licensees checked against the regulators' imported data; NMLS IDs checked
 * by a REOS reviewer on NMLS Consumer Access (no public API).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_licences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // trec_inspector, tdi or nmls.
            $table->string('kind', 16);
            $table->string('number', 40);
            // verified, inactive, review or rejected.
            $table->string('status', 12);
            $table->string('name_on_record', 200)->nullable();
            $table->string('licence_type', 60)->nullable();
            $table->date('expires_on')->nullable();
            // trec-licence, tdi-lookup or reviewer.
            $table->string('checked_via', 20)->nullable();
            // Why a reviewer is needed, or why it was rejected.
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'kind']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_licences');
    }
};
