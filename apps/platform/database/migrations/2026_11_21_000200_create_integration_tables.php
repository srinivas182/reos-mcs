<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration Hub (Sprint 2.4): how each integration runs, and its encrypted
 * keys. Integration ids come from docs/integrations/register.yaml.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table): void {
            $table->string('integration_id', 100)->primary();
            $table->string('mode', 20);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('integration_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('integration_id', 100);
            $table->string('environment', 20);
            $table->text('payload');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['integration_id', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_credentials');
        Schema::dropIfExists('integration_settings');
    }
};
