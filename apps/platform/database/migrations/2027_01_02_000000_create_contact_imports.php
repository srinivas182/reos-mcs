<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CSV import, the last resort (B8.3b, ADR 0034): an agent's spreadsheet of
 * people, mapped, previewed and imported as a source named "CSV import,
 * date" that can be undone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_name', 191);
            // On the private disk; deleted once imported or undone.
            $table->string('path', 191)->nullable();
            $table->json('headers');
            $table->json('mapping')->nullable();
            $table->unsignedInteger('rows');
            // uploaded | imported | undone
            $table->string('status', 12)->default('uploaded');
            $table->json('counts')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_imports');
    }
};
